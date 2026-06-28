<?php
declare(strict_types=1);

/**
 * SpendWise Notification Mailer
 * ─────────────────────────────
 * Run via cron once per day, e.g.:
 *   0 8 * * * php /path/to/spendwise/scripts/send-notifications.php >> /var/log/spendwise-notif.log 2>&1
 *
 * Email rules:
 *  • Due-date reminders (bills & recurring): one email per day for each
 *    of the 4 days leading up to the due date (days 4, 3, 2, 1 before due).
 *  • General engagement email: once every 4 days per user (brings them back
 *    to review spending / budgets).
 *  • All sends are deduplicated via __notifications_meta stored in user state.
 */

require_once __DIR__ . '/../functions.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$db  = sw_db();
$now = new DateTimeImmutable('now');
$todayStr = $now->format('Y-m-d');

// ── helpers ──────────────────────────────────────────────────────────────────

function metaLastSent(array $meta, string $key): ?DateTimeImmutable
{
    $raw = $meta[$key]['last_sent'] ?? null;
    if ($raw === null) return null;
    $dt = DateTimeImmutable::createFromFormat(DateTime::ATOM, $raw);
    return $dt instanceof DateTimeImmutable ? $dt : null;
}

function sentToday(array $meta, string $key, DateTimeImmutable $now): bool
{
    $last = metaLastSent($meta, $key);
    return $last !== null && $last->format('Y-m-d') === $now->format('Y-m-d');
}

function daysSinceLastSent(array $meta, string $key, DateTimeImmutable $now): int
{
    $last = metaLastSent($meta, $key);
    if ($last === null) return PHP_INT_MAX;
    return (int) $now->setTime(0,0,0)->diff($last->setTime(0,0,0))->format('%a');
}

function daysUntil(string $dueDateStr, DateTimeImmutable $now): int
{
    try {
        $due = new DateTimeImmutable($dueDateStr);
    } catch (Throwable $e) {
        return PHP_INT_MAX;
    }
    $diff = $due->setTime(0,0,0)->diff($now->setTime(0,0,0));
    // positive = future, negative = past
    return $diff->invert === 0 ? -(int)$diff->format('%a') : (int)$diff->format('%a');
}

function fmtAmount(?float $amount): string
{
    if ($amount === null) return '';
    return 'ETB ' . number_format($amount, 2);
}

// ── load all users ────────────────────────────────────────────────────────────

$res = $db->query('SELECT id, name, email FROM users');
if (!$res) {
    echo "DB query failed.\n";
    exit(1);
}

while ($user = $res->fetch_assoc()) {
    $userId    = (int)    $user['id'];
    $userName  = (string) $user['name'];
    $userEmail = (string) $user['email'];
    $firstName = explode(' ', trim($userName))[0] ?: 'there';

    try {
        $state = sw_load_state($db, $userId) ?? [];
    } catch (Throwable $e) {
        continue;
    }

    // Check user has email notifications enabled
    $notifSettings = $state['notifications'] ?? [];
    $emailEnabled  = isset($notifSettings['email']) ? (bool) $notifSettings['email'] : true;
    if (!$emailEnabled) continue;

    $meta    = $state['__notifications_meta'] ?? [];
    $updated = false;

    // ── 1. DUE-DATE REMINDERS (bills + recurring) ─────────────────────────────
    // Send one email per day for each of the 4 days before the due date.

    $dueItems = [];

    foreach (($state['bills'] ?? []) as $bill) {
        if (!is_array($bill)) continue;
        $due = $bill['dueDate'] ?? $bill['nextDue'] ?? null;
        $id  = $bill['id'] ?? ($bill['name'] ?? null);
        if ($due === null || $id === null) continue;
        if (($bill['status'] ?? '') === 'paid') continue;
        $dueItems[] = [
            'metaKey' => 'bill_' . (string) $id,
            'name'    => $bill['name'] ?? 'Bill',
            'due'     => (string) $due,
            'amount'  => isset($bill['amount']) ? (float) $bill['amount'] : null,
            'type'    => 'bill',
        ];
    }

    foreach (($state['recurring'] ?? []) as $rec) {
        if (!is_array($rec)) continue;
        if (empty($rec['active'])) continue;
        $due = $rec['nextDue'] ?? null;
        $id  = $rec['id'] ?? ($rec['name'] ?? null);
        if ($due === null || $id === null) continue;
        $dueItems[] = [
            'metaKey' => 'rec_' . (string) $id,
            'name'    => $rec['name'] ?? 'Recurring',
            'due'     => (string) $due,
            'amount'  => isset($rec['amount']) ? (float) $rec['amount'] : null,
            'type'    => 'recurring',
        ];
    }

    foreach ($dueItems as $item) {
        $days = daysUntil($item['due'], $now);

        // Only send reminders for days 1–4 before due (inclusive)
        if ($days < 1 || $days > 4) continue;

        // Send at most once per calendar day per item
        if (sentToday($meta, $item['metaKey'], $now)) continue;

        $dayWord  = $days === 1 ? '1 day' : "{$days} days";
        $typeLabel = $item['type'] === 'recurring' ? 'recurring payment' : 'bill';
        $amtLine   = $item['amount'] !== null
            ? "<p style='margin:0 0 12px'>Amount due: <strong>" . htmlspecialchars(fmtAmount($item['amount']), ENT_QUOTES, 'UTF-8') . "</strong></p>"
            : '';

        $subject = "⏰ Reminder: {$item['name']} is due in {$dayWord}";

        $html = "
<div style='font-family:sans-serif;max-width:520px;margin:0 auto;background:#0f172a;color:#e2e8f0;border-radius:12px;overflow:hidden'>
  <div style='background:linear-gradient(135deg,#0d9488,#059669);padding:28px 32px'>
    <p style='margin:0;font-size:22px;font-weight:700;color:#fff'>⏰ Payment Reminder</p>
    <p style='margin:6px 0 0;font-size:14px;color:rgba(255,255,255,.8)'>SpendWise</p>
  </div>
  <div style='padding:28px 32px'>
    <p style='margin:0 0 16px'>Hi <strong>" . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
    <p style='margin:0 0 12px'>Your <strong>" . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . "</strong> {$typeLabel} is due in <strong>{$dayWord}</strong> on <strong>" . htmlspecialchars($item['due'], ENT_QUOTES, 'UTF-8') . "</strong>.</p>
    {$amtLine}
    <p style='margin:0 0 24px;color:#94a3b8;font-size:14px'>Open SpendWise to review or mark it as paid.</p>
    <p style='margin:0;color:#64748b;font-size:12px'>If you have already handled this, you can ignore this email.</p>
  </div>
</div>";

        $text = "Hi {$firstName},\n\nReminder: {$item['name']} is due in {$dayWord} on {$item['due']}."
              . ($item['amount'] !== null ? "\nAmount: " . fmtAmount($item['amount']) : '')
              . "\n\nOpen SpendWise to review.\n";

        try {
            sw_send_notification_email($userName, $userEmail, $subject, $html, $text);
            $meta[$item['metaKey']] = ['last_sent' => $now->format(DateTime::ATOM)];
            $updated = true;
            echo "[{$todayStr}] Due reminder → {$userEmail}: {$item['name']} (in {$days}d)\n";
        } catch (Throwable $e) {
            error_log("Due reminder failed uid={$userId} item={$item['name']}: " . $e->getMessage());
        }
    }

    // ── 2. GENERAL ENGAGEMENT EMAIL (every 4 days) ────────────────────────────
    $engKey  = '__engagement';
    $daysSince = daysSinceLastSent($meta, $engKey, $now);

    if ($daysSince >= 4) {
        // Build a quick summary for the email
        $expenseCount  = count($state['expenses'] ?? []);
        $budgetCount   = count($state['budgets'] ?? []);
        $billsDue      = array_filter($state['bills'] ?? [], fn($b) =>
            isset($b['dueDate']) && daysUntil($b['dueDate'], $now) <= 7 && daysUntil($b['dueDate'], $now) >= 0
            && ($b['status'] ?? '') !== 'paid'
        );
        $upcomingBills = count($billsDue);

        $summaryLine = '';
        if ($upcomingBills > 0) {
            $summaryLine = "<li style='margin-bottom:6px'>📅 You have <strong>{$upcomingBills} bill" . ($upcomingBills > 1 ? 's' : '') . "</strong> due in the next 7 days</li>";
        }
        if ($budgetCount > 0) {
            $summaryLine .= "<li style='margin-bottom:6px'>🎯 You have <strong>{$budgetCount} active budget" . ($budgetCount > 1 ? 's' : '') . "</strong> — check how you're tracking</li>";
        }
        if ($expenseCount > 0) {
            $summaryLine .= "<li style='margin-bottom:6px'>💸 You've logged <strong>{$expenseCount} expense" . ($expenseCount > 1 ? 's' : '') . "</strong> so far — great job staying on top of it</li>";
        }
        if ($summaryLine === '') {
            $summaryLine = "<li style='margin-bottom:6px'>📊 Log your first expense and start tracking your spending</li>";
        }

        $subject = "👋 Your SpendWise check-in, {$firstName}";

        $html = "
<div style='font-family:sans-serif;max-width:520px;margin:0 auto;background:#0f172a;color:#e2e8f0;border-radius:12px;overflow:hidden'>
  <div style='background:linear-gradient(135deg,#0d9488,#059669);padding:28px 32px'>
    <p style='margin:0;font-size:22px;font-weight:700;color:#fff'>👋 Stay on top of your finances</p>
    <p style='margin:6px 0 0;font-size:14px;color:rgba(255,255,255,.8)'>SpendWise</p>
  </div>
  <div style='padding:28px 32px'>
    <p style='margin:0 0 16px'>Hi <strong>" . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
    <p style='margin:0 0 16px'>Here's a quick look at what's going on with your SpendWise account:</p>
    <ul style='margin:0 0 24px;padding-left:20px;color:#cbd5e1;font-size:15px'>
      {$summaryLine}
    </ul>
    <p style='margin:0 0 24px;color:#94a3b8;font-size:14px'>Open SpendWise to review your budget, check upcoming bills, and keep your spending on track.</p>
    <p style='margin:0;color:#64748b;font-size:12px'>You're receiving this because email notifications are enabled in your SpendWise account.</p>
  </div>
</div>";

        $text = "Hi {$firstName},\n\nHere's your SpendWise check-in:\n"
              . "- Expenses logged: {$expenseCount}\n"
              . "- Active budgets: {$budgetCount}\n"
              . ($upcomingBills > 0 ? "- Bills due in 7 days: {$upcomingBills}\n" : '')
              . "\nOpen SpendWise to stay on track.\n";

        try {
            sw_send_notification_email($userName, $userEmail, $subject, $html, $text);
            $meta[$engKey] = ['last_sent' => $now->format(DateTime::ATOM)];
            $updated = true;
            echo "[{$todayStr}] Engagement email → {$userEmail}\n";
        } catch (Throwable $e) {
            error_log("Engagement email failed uid={$userId}: " . $e->getMessage());
        }
    }

    // ── Save updated meta ─────────────────────────────────────────────────────
    if ($updated) {
        $state['__notifications_meta'] = $meta;
        try {
            sw_save_state($db, $userId, $state);
        } catch (Throwable $e) {
            error_log("Could not save notification meta uid={$userId}: " . $e->getMessage());
        }
    }
}

echo "[{$todayStr}] Notification scan complete.\n";
