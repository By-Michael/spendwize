<?php
/**
 * SpendWise — Bills API
 *
 * GET    /api/bills.php                 List bills
 * GET    /api/bills.php?id=<ext_id>     Get single bill
 * GET    /api/bills.php?status=upcoming|overdue|paid   Filter by status
 * POST   /api/bills.php                 Create bill
 * PUT    /api/bills.php?id=<ext_id>     Update bill
 * DELETE /api/bills.php?id=<ext_id>     Delete bill
 *
 * POST   /api/bills.php?action=pay      Mark as paid   (body: { id, paidDate? })
 * POST   /api/bills.php?action=unpay    Mark as unpaid (body: { id })
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

const SW_VALID_STATUSES = ['upcoming', 'overdue', 'paid'];

try {
    $user   = sw_api_require_auth();
    $userId = (int) $user['id'];
    $db     = sw_db();
    $method = sw_method();

    // ── GET ───────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $id = sw_coerce_ext_id(sw_param('id', ''));

        if ($id !== '') {
            $row = sw_fetch_bill_raw($db, $userId, $id);
            if ($row === null) {
                sw_fail('Bill not found.', 404);
            }
            sw_ok(['bill' => sw_format_bill($row)]);
        }

        // List with optional filters
        $where  = ['user_id = ?'];
        $types  = 'i';
        $params = [$userId];

        $status = trim((string) sw_param('status', ''));
        if ($status !== '' && in_array($status, SW_VALID_STATUSES, true)) {
            $where[]  = 'status = ?';
            $types   .= 's';
            $params[] = $status;
        }

        // date range on due_date
        $from = sw_coerce_date(sw_param('from', ''));
        if ($from !== '') {
            $where[]  = 'due_date >= ?';
            $types   .= 's';
            $params[] = $from;
        }
        $to = sw_coerce_date(sw_param('to', ''));
        if ($to !== '') {
            $where[]  = 'due_date <= ?';
            $types   .= 's';
            $params[] = $to;
        }

        $stmt = $db->prepare(
            'SELECT external_id AS id, name, amount, category,
                    due_date AS dueDate, status, paid_date AS paidDate, reference
             FROM bills
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY due_date ASC, id ASC'
        );
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $bills = [];
        while ($row = $res->fetch_assoc()) {
            $bills[] = sw_format_bill($row);
        }
        $stmt->close();

        sw_ok(['bills' => $bills]);
    }

    // ── POST — create or action ───────────────────────────────────────────────
    if ($method === 'POST') {
        $action = trim((string) sw_param('action', ''));
        $body   = sw_body();

        // -- pay / unpay convenience actions --
        if ($action === 'pay' || $action === 'unpay') {
            $id = sw_coerce_ext_id($body['id'] ?? '');
            if ($id === '') {
                sw_fail('id is required.');
            }

            if (sw_fetch_bill_raw($db, $userId, $id) === null) {
                sw_fail('Bill not found.', 404);
            }

            if ($action === 'pay') {
                $paidDate = sw_coerce_date_nullable($body['paidDate'] ?? null) ?? date('Y-m-d');
                $stmt = $db->prepare(
                    'UPDATE bills SET status = "paid", paid_date = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE user_id = ? AND external_id = ?'
                );
                $stmt->bind_param('sis', $paidDate, $userId, $id);
            } else {
                $stmt = $db->prepare(
                    'UPDATE bills SET status = "upcoming", paid_date = NULL, updated_at = CURRENT_TIMESTAMP
                     WHERE user_id = ? AND external_id = ?'
                );
                $stmt->bind_param('is', $userId, $id);
            }
            $stmt->execute();
            $stmt->close();

            sw_ok(['bill' => sw_fetch_bill($db, $userId, $id)]);
        }

        // -- create --
        $extId     = sw_coerce_ext_id($body['id'] ?? '');
        $name      = trim((string) ($body['name'] ?? ''));
        $amount    = (float) ($body['amount'] ?? 0);
        $cat       = trim((string) ($body['category'] ?? ''));
        $dueDate   = sw_coerce_date($body['dueDate'] ?? '');
        $status    = trim((string) ($body['status'] ?? 'upcoming'));
        $paidDate  = sw_coerce_date_nullable($body['paidDate'] ?? null);
        $reference = isset($body['reference']) && $body['reference'] !== '' && $body['reference'] !== null
                         ? (string) $body['reference'] : null;

        if ($extId === '') {
            sw_fail('id is required.');
        }
        if ($name === '') {
            sw_fail('name is required.');
        }
        // [SEC F-05] Enforce field length limits.
        if (strlen($name) > 200) {
            sw_fail('Bill name too long. Maximum 200 characters.');
        }
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        if ($reference !== null && strlen($reference) > 100) {
            sw_fail('Reference too long. Maximum 100 characters.');
        }
        if ($amount <= 0) {
            sw_fail('amount must be a positive number.');
        }
        if ($dueDate === '') {
            sw_fail('dueDate must be YYYY-MM-DD.');
        }
        if (!in_array($status, SW_VALID_STATUSES, true)) {
            sw_fail('status must be one of: ' . implode(', ', SW_VALID_STATUSES) . '.');
        }

        $stmt = $db->prepare(
            'INSERT INTO bills
             (user_id, external_id, name, amount, category, due_date, status, paid_date, reference)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               name       = VALUES(name),
               amount     = VALUES(amount),
               category   = VALUES(category),
               due_date   = VALUES(due_date),
               status     = VALUES(status),
               paid_date  = VALUES(paid_date),
               reference  = VALUES(reference),
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->bind_param('issdsssss', $userId, $extId, $name, $amount, $cat, $dueDate, $status, $paidDate, $reference);
        $stmt->execute();
        $stmt->close();

        sw_created(['bill' => sw_fetch_bill($db, $userId, $extId)]);
    }

    // ── PUT — update ──────────────────────────────────────────────────────────
    if ($method === 'PUT' || $method === 'PATCH') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $existing = sw_fetch_bill_raw($db, $userId, $id);
        if ($existing === null) {
            sw_fail('Bill not found.', 404);
        }

        $body      = sw_body();
        $name      = isset($body['name'])      ? trim((string) $body['name'])        : (string)  $existing['name'];
        $amount    = isset($body['amount'])    ? (float) $body['amount']             : (float)   $existing['amount'];
        $cat       = isset($body['category'])  ? trim((string) $body['category'])    : (string)  $existing['category'];
        $dueDate   = isset($body['dueDate'])   ? sw_coerce_date($body['dueDate'])    : (string)  $existing['dueDate'];
        $status    = isset($body['status'])    ? trim((string) $body['status'])      : (string)  $existing['status'];
        $paidDate  = array_key_exists('paidDate', $body)
                       ? sw_coerce_date_nullable($body['paidDate'])
                       : $existing['paidDate'];
        $reference = array_key_exists('reference', $body)
                       ? (($body['reference'] !== null && $body['reference'] !== '') ? (string) $body['reference'] : null)
                       : $existing['reference'];

        if ($name === '') {
            sw_fail('name cannot be empty.');
        }
        // [SEC F-05] Enforce field length limits.
        if (strlen($name) > 200) {
            sw_fail('Bill name too long. Maximum 200 characters.');
        }
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        if ($reference !== null && strlen($reference) > 100) {
            sw_fail('Reference too long. Maximum 100 characters.');
        }
        if ($amount <= 0) {
            sw_fail('amount must be a positive number.');
        }
        if ($dueDate === '') {
            sw_fail('dueDate must be YYYY-MM-DD.');
        }
        if (!in_array($status, SW_VALID_STATUSES, true)) {
            sw_fail('status must be one of: ' . implode(', ', SW_VALID_STATUSES) . '.');
        }

        $stmt = $db->prepare(
            'UPDATE bills
             SET name = ?, amount = ?, category = ?, due_date = ?, status = ?,
                 paid_date = ?, reference = ?, updated_at = CURRENT_TIMESTAMP
             WHERE user_id = ? AND external_id = ?'
        );
        $stmt->bind_param('sdsssssis', $name, $amount, $cat, $dueDate, $status, $paidDate, $reference, $userId, $id);
        $stmt->execute();
        $stmt->close();

        sw_ok(['bill' => sw_fetch_bill($db, $userId, $id)]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $stmt = $db->prepare('DELETE FROM bills WHERE user_id = ? AND external_id = ?');
        $stmt->bind_param('is', $userId, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            sw_fail('Bill not found.', 404);
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

function sw_format_bill(array $row): array
{
    return [
        'id'        => $row['id'],
        'name'      => (string) $row['name'],
        'amount'    => (float) $row['amount'],
        'category'  => (string) $row['category'],
        'dueDate'   => (string) $row['dueDate'],
        'status'    => (string) $row['status'],
        'paidDate'  => $row['paidDate'] !== null ? (string) $row['paidDate'] : null,
        'reference' => $row['reference'] !== null ? (string) $row['reference'] : null,
    ];
}

function sw_fetch_bill(mysqli $db, int $userId, string $extId): ?array
{
    $row = sw_fetch_bill_raw($db, $userId, $extId);
    return $row !== null ? sw_format_bill($row) : null;
}

function sw_fetch_bill_raw(mysqli $db, int $userId, string $extId): ?array
{
    $stmt = $db->prepare(
        'SELECT external_id AS id, name, amount, category,
                due_date AS dueDate, status, paid_date AS paidDate, reference
         FROM bills WHERE user_id = ? AND external_id = ? LIMIT 1'
    );
    $stmt->bind_param('is', $userId, $extId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
