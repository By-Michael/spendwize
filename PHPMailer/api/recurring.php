<?php
/**
 * SpendWise — Recurring Items API
 *
 * GET    /api/recurring.php                  List all recurring items
 * GET    /api/recurring.php?id=<ext_id>      Get single item
 * GET    /api/recurring.php?active=1|0       Filter by active status
 * POST   /api/recurring.php                  Create item
 * PUT    /api/recurring.php?id=<ext_id>      Update item
 * DELETE /api/recurring.php?id=<ext_id>      Delete item
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

const SW_VALID_FREQUENCIES = ['daily', 'weekly', 'fortnightly', 'monthly', 'quarterly', 'yearly'];

try {
    $user   = sw_api_require_auth();
    $userId = (int) $user['id'];
    $db     = sw_db();
    $method = sw_method();

    // ── GET ───────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $id = sw_coerce_ext_id(sw_param('id', ''));

        // Single item
        if ($id !== '') {
            $row = sw_fetch_recurring_raw($db, $userId, $id);
            if ($row === null) {
                sw_fail('Recurring item not found.', 404);
            }
            sw_ok(['recurring' => sw_format_recurring($row)]);
        }

        // List — optional active filter
        $where  = ['user_id = ?'];
        $types  = 'i';
        $params = [$userId];

        $activeParam = sw_param('active', null);
        if ($activeParam !== null) {
            $where[]  = 'active = ?';
            $types   .= 'i';
            $params[] = $activeParam === '0' || $activeParam === 'false' ? 0 : 1;
        }

        // Optional: only items due on or before a given date
        $dueBefore = sw_coerce_date(sw_param('due_before', ''));
        if ($dueBefore !== '') {
            $where[]  = 'next_due <= ?';
            $types   .= 's';
            $params[] = $dueBefore;
        }

        $stmt = $db->prepare(
            'SELECT external_id AS id, name, amount, category, frequency,
                    start_date AS startDate, end_date AS endDate,
                    next_due AS nextDue, active
             FROM recurring_items
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY next_due ASC, id ASC'
        );
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = sw_format_recurring($row);
        }
        $stmt->close();

        sw_ok(['recurring' => $items]);
    }

    // ── POST — create ─────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $body      = sw_body();
        $extId     = sw_coerce_ext_id($body['id'] ?? '');
        $name      = trim((string) ($body['name'] ?? ''));
        $amount    = (float) ($body['amount'] ?? 0);
        $cat       = trim((string) ($body['category'] ?? ''));
        $freq      = trim((string) ($body['frequency'] ?? 'monthly'));
        $startDate = sw_coerce_date($body['startDate'] ?? '');
        $endDate   = sw_coerce_date_nullable($body['endDate'] ?? null);
        $nextDue   = sw_coerce_date($body['nextDue'] ?? $startDate);
        $active    = (int) (bool) ($body['active'] ?? true);

        if ($extId === '') {
            sw_fail('id is required.');
        }
        if ($name === '') {
            sw_fail('name is required.');
        }
        // [SEC F-05] Enforce field length limits.
        if (strlen($name) > 200) {
            sw_fail('Recurring item name too long. Maximum 200 characters.');
        }
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        if ($amount <= 0) {
            sw_fail('amount must be a positive number.');
        }
        if ($startDate === '') {
            sw_fail('startDate must be YYYY-MM-DD.');
        }
        if ($nextDue === '') {
            sw_fail('nextDue must be YYYY-MM-DD.');
        }
        if (!in_array($freq, SW_VALID_FREQUENCIES, true)) {
            sw_fail('frequency must be one of: ' . implode(', ', SW_VALID_FREQUENCIES) . '.');
        }

        $stmt = $db->prepare(
            'INSERT INTO recurring_items
             (user_id, external_id, name, amount, category, frequency,
              start_date, end_date, next_due, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               name       = VALUES(name),
               amount     = VALUES(amount),
               category   = VALUES(category),
               frequency  = VALUES(frequency),
               start_date = VALUES(start_date),
               end_date   = VALUES(end_date),
               next_due   = VALUES(next_due),
               active     = VALUES(active),
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->bind_param(
            'issdsssssi',
            $userId, $extId, $name, $amount, $cat, $freq,
            $startDate, $endDate, $nextDue, $active
        );
        $stmt->execute();
        $stmt->close();

        sw_created(['recurring' => sw_fetch_recurring($db, $userId, $extId)]);
    }

    // ── PUT — update ──────────────────────────────────────────────────────────
    if ($method === 'PUT' || $method === 'PATCH') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $existing = sw_fetch_recurring_raw($db, $userId, $id);
        if ($existing === null) {
            sw_fail('Recurring item not found.', 404);
        }

        $body = sw_body();

        $name      = isset($body['name'])      ? trim((string) $body['name'])          : (string) $existing['name'];
        $amount    = isset($body['amount'])    ? (float) $body['amount']               : (float)  $existing['amount'];
        $cat       = isset($body['category'])  ? trim((string) $body['category'])      : (string) $existing['category'];
        $freq      = isset($body['frequency']) ? trim((string) $body['frequency'])     : (string) $existing['frequency'];
        $startDate = isset($body['startDate']) ? sw_coerce_date($body['startDate'])    : (string) $existing['startDate'];
        $endDate   = array_key_exists('endDate', $body)
                       ? sw_coerce_date_nullable($body['endDate'])
                       : $existing['endDate'];
        $nextDue   = isset($body['nextDue'])   ? sw_coerce_date($body['nextDue'])      : (string) $existing['nextDue'];
        $active    = isset($body['active'])    ? (int)(bool) $body['active']           : (int) $existing['active'];

        if ($name === '') {
            sw_fail('name cannot be empty.');
        }
        // [SEC F-05] Enforce field length limits.
        if (strlen($name) > 200) {
            sw_fail('Recurring item name too long. Maximum 200 characters.');
        }
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        if ($amount <= 0) {
            sw_fail('amount must be a positive number.');
        }
        if ($startDate === '') {
            sw_fail('startDate must be YYYY-MM-DD.');
        }
        if ($nextDue === '') {
            sw_fail('nextDue must be YYYY-MM-DD.');
        }
        if (!in_array($freq, SW_VALID_FREQUENCIES, true)) {
            sw_fail('frequency must be one of: ' . implode(', ', SW_VALID_FREQUENCIES) . '.');
        }

        $stmt = $db->prepare(
            'UPDATE recurring_items
             SET name = ?, amount = ?, category = ?, frequency = ?,
                 start_date = ?, end_date = ?, next_due = ?, active = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = ? AND external_id = ?'
        );
        $stmt->bind_param(
            'sdsssssiis',
            $name, $amount, $cat, $freq,
            $startDate, $endDate, $nextDue, $active,
            $userId, $id
        );
        $stmt->execute();
        $stmt->close();

        sw_ok(['recurring' => sw_fetch_recurring($db, $userId, $id)]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $stmt = $db->prepare(
            'DELETE FROM recurring_items WHERE user_id = ? AND external_id = ?'
        );
        $stmt->bind_param('is', $userId, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            sw_fail('Recurring item not found.', 404);
        }

        sw_ok(['deleted' => true, 'id' => $id]);
    }

    sw_fail('Method not allowed.', 405);

} catch (Throwable $e) {
    // [SEC F-06] Never expose internal exception details to the client.
    error_log('[SpendWise] API error in : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sw_json_response(500, ['ok' => false, 'error' => 'An unexpected error occurred. Please try again.']);
}

// ── Private helpers ───────────────────────────────────────────────────────────

function sw_format_recurring(array $row): array
{
    return [
        'id'        => $row['id'],
        'name'      => (string) $row['name'],
        'amount'    => (float) $row['amount'],
        'category'  => (string) $row['category'],
        'frequency' => (string) $row['frequency'],
        'startDate' => (string) $row['startDate'],
        'endDate'   => $row['endDate'] !== null ? (string) $row['endDate'] : null,
        'nextDue'   => (string) $row['nextDue'],
        'active'    => (bool) $row['active'],
    ];
}

function sw_fetch_recurring(mysqli $db, int $userId, string $extId): ?array
{
    $row = sw_fetch_recurring_raw($db, $userId, $extId);
    return $row !== null ? sw_format_recurring($row) : null;
}

function sw_fetch_recurring_raw(mysqli $db, int $userId, string $extId): ?array
{
    $stmt = $db->prepare(
        'SELECT external_id AS id, name, amount, category, frequency,
                start_date AS startDate, end_date AS endDate,
                next_due AS nextDue, active
         FROM recurring_items WHERE user_id = ? AND external_id = ? LIMIT 1'
    );
    $stmt->bind_param('is', $userId, $extId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
