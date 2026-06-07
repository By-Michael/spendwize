<?php
declare(strict_types=1);

// Run from CLI: php scripts/send-notifications.php

require_once __DIR__ . '/../functions.php';

if (php_sapi_name() !== 'cli') {
    echo "This script should be run from the command line.\n";
    exit(1);
}

$db = sw_db();

$now = new DateTimeImmutable('now');

// Load all users
$res = $db->query('SELECT id, name, email FROM users');
while ($user = $res->fetch_assoc()) {
    $userId = (int) $user['id'];
    $userName = (string) $user['name'];
    $userEmail = (string) $user['email'];

    try {
        $state = sw_load_state($db, $userId) ?? [];
    } catch (Throwable $e) {
        // skip
        continue;
    }

    $notificationsMeta = $state['__notifications_meta'] ?? [];
    $updated = false;

    $candidates = [];
    if (!empty($state['bills']) && is_array($state['bills'])) {
        foreach ($state['bills'] as $bill) {
            if (!is_array($bill)) continue;
            $due = $bill['dueDate'] ?? $bill['nextDue'] ?? null;
            $id = $bill['id'] ?? ($bill['name'] ?? null);
            if ($due === null || $id === null) continue;
            $candidates[] = ['id' => (string) $id, 'name' => $bill['name'] ?? 'Bill', 'due' => $due, 'amount' => $bill['amount'] ?? null];
        }
    }

    if (!empty($state['recurring']) && is_array($state['recurring'])) {
        foreach ($state['recurring'] as $rec) {
            if (!is_array($rec)) continue;
            $due = $rec['nextDue'] ?? null;
            $id = $rec['id'] ?? ($rec['name'] ?? null);
            if ($due === null || $id === null) continue;
            $candidates[] = ['id' => 'r_' . (string) $id, 'name' => $rec['name'] ?? 'Recurring', 'due' => $due, 'amount' => $rec['amount'] ?? null];
        }
    }

    foreach ($candidates as $item) {
        $dueStr = (string) $item['due'];
        try {
            $dueDate = new DateTimeImmutable($dueStr);
        } catch (Throwable $e) {
            continue;
        }

        $diff = $dueDate->setTime(0,0,0)->diff($now->setTime(0,0,0));
        $daysUntil = (int) $diff->format('%r%a');

        if ($daysUntil < 0) {
            // already past due — skip for now
            continue;
        }

        $metaKey = (string) $item['id'];
        $lastSent = isset($notificationsMeta[$metaKey]['last_sent']) ? DateTimeImmutable::createFromFormat(DateTime::ATOM, $notificationsMeta[$metaKey]['last_sent']) : null;

        $shouldSend = false;

        if ($daysUntil <= 4) {
            // Send daily (once per calendar day)
            if ($lastSent === null || $lastSent->format('Y-m-d') !== $now->format('Y-m-d')) {
                $shouldSend = true;
            }
        } else {
            // For items further away, send once every 3 days
            if ($lastSent === null) {
                $shouldSend = true;
            } else {
                $diffDays = (int) $now->diff($lastSent)->format('%a');
                if ($diffDays >= 3) {
                    $shouldSend = true;
                }
            }
        }

        if (!$shouldSend) {
            continue;
        }

        // Build message
        $subject = "Reminder: {$item['name']} due in {$daysUntil} day" . ($daysUntil === 1 ? '' : 's');
        $amountText = $item['amount'] !== null ? ('Amount: ' . (string) $item['amount']) : '';
        $html = "<p>Hi " . htmlspecialchars($userName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ",</p>";
        $html .= "<p>This is a reminder that <strong>" . htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</strong> is due on <strong>" . htmlspecialchars($dueDate->format('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</strong> (in {$daysUntil} day" . ($daysUntil === 1 ? '' : 's') . ").</p>";
        if ($amountText !== '') {
            $html .= "<p>{$amountText}</p>";
        }
        $html .= "<p>If you have already paid, please ignore this message.</p>";
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        try {
            sw_send_notification_email($userName, $userEmail, $subject, $html, $text);
            $notificationsMeta[$metaKey] = ['last_sent' => $now->format(DateTime::ATOM)];
            $updated = true;
            echo "Sent notification to {$userEmail} for {$item['name']} (due {$dueDate->format('Y-m-d')})\n";
        } catch (Throwable $e) {
            // Log and continue
            error_log('Notification send failed for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    if ($updated) {
        $state['__notifications_meta'] = $notificationsMeta;
        try {
            sw_save_state($db, $userId, $state);
        } catch (Throwable $e) {
            error_log('Could not save notification meta for user ' . $userId . ': ' . $e->getMessage());
        }
    }
}

echo "Notifications scan complete.\n";
