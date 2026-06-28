<?php
/**
 * SpendWise — Expenses API
 *
 * GET    /api/expenses.php              List expenses (filterable)
 * GET    /api/expenses.php?id=<ext_id>  Get single expense
 * POST   /api/expenses.php              Create expense
 * PUT    /api/expenses.php?id=<ext_id>  Update expense
 * DELETE /api/expenses.php?id=<ext_id>  Delete expense
 *
 * Query filters for GET list:
 *   ?category=<str>   filter by category (exact)
 *   ?month=<YYYY-MM>  filter by month
 *   ?from=<YYYY-MM-DD>&to=<YYYY-MM-DD>  date range
 *   ?limit=<int>      max rows (default 500, max 2000)
 *   ?offset=<int>     pagination offset (default 0)
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

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
            $stmt = $db->prepare(
                'SELECT external_id AS id, amount, category, date, note, receipt
                 FROM expenses
                 WHERE user_id = ? AND external_id = ?
                 LIMIT 1'
            );
            $stmt->bind_param('is', $userId, $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row === null) {
                sw_fail('Expense not found.', 404);
            }

            sw_ok(['expense' => sw_format_expense($row)]);
        }

        // List with optional filters
        $where  = ['e.user_id = ?'];
        $types  = 'i';
        $params = [$userId];

        $category = trim((string) sw_param('category', ''));
        if ($category !== '') {
            $where[]  = 'e.category = ?';
            $types   .= 's';
            $params[] = $category;
        }

        $month = sw_coerce_month(sw_param('month', ''));
        if ($month !== '') {
            $where[]  = "DATE_FORMAT(e.date, '%Y-%m') = ?";
            $types   .= 's';
            $params[] = $month;
        }

        $from = sw_coerce_date(sw_param('from', ''));
        if ($from !== '') {
            $where[]  = 'e.date >= ?';
            $types   .= 's';
            $params[] = $from;
        }

        $to = sw_coerce_date(sw_param('to', ''));
        if ($to !== '') {
            $where[]  = 'e.date <= ?';
            $types   .= 's';
            $params[] = $to;
        }

        $limit  = min(2000, max(1, (int) sw_param('limit', 500)));
        $offset = max(0, (int) sw_param('offset', 0));

        $sql = 'SELECT e.external_id AS id, e.amount, e.category, e.date, e.note, e.receipt
                FROM expenses e
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY e.date DESC, e.id DESC
                LIMIT ? OFFSET ?';

        $types   .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $expenses = [];
        while ($row = $res->fetch_assoc()) {
            $expenses[] = sw_format_expense($row);
        }
        $stmt->close();

        // Total count for pagination
        $countSql = 'SELECT COUNT(*) AS total FROM expenses e WHERE ' . implode(' AND ', $where);
        // Remove the last two params (limit/offset) for the count query
        $countTypes  = substr($types, 0, -2);
        $countParams = array_slice($params, 0, -2);
        $stmt = $db->prepare($countSql);
        $stmt->bind_param($countTypes, ...$countParams);
        $stmt->execute();
        $totalRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        sw_ok([
            'expenses' => $expenses,
            'total'    => (int) ($totalRow['total'] ?? 0),
            'limit'    => $limit,
            'offset'   => $offset,
        ]);
    }

    // ── POST — create ─────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $body   = sw_body();
        $extId  = sw_coerce_ext_id($body['id'] ?? '');
        $amount = (float) ($body['amount'] ?? 0);
        $cat    = trim((string) ($body['category'] ?? ''));
        $date   = sw_coerce_date($body['date'] ?? '');
        $note   = isset($body['note']) && $body['note'] !== null ? (string) $body['note'] : null;
        $receipt = isset($body['receipt']) && $body['receipt'] !== '' && $body['receipt'] !== null
                       ? (string) $body['receipt'] : null;

        if ($extId === '') {
            sw_fail('id is required.');
        }
        if ($amount <= 0) {
            sw_fail('amount must be a positive number.');
        }
        if ($date === '') {
            sw_fail('date must be YYYY-MM-DD.');
        }
        // [SEC F-05] Enforce length limits on free-text fields.
        if ($note !== null && strlen($note) > 500) {
            sw_fail('Note too long. Maximum 500 characters.');
        }
        // [SEC F-09] Enforce category length limit (allowlist validation is recommended for production).
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        // [SEC F-04] Enforce receipt size and MIME type server-side.
        if ($receipt !== null) {
            if (strlen($receipt) > 819200) { // ~600 KB raw image
                sw_fail('Receipt image too large. Maximum 600 KB.');
            }
            if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $receipt)) {
                sw_fail('Invalid receipt format. Must be a JPEG, PNG, or WebP image.');
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO expenses
             (user_id, external_id, amount, category, date, note, receipt)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               amount     = VALUES(amount),
               category   = VALUES(category),
               date       = VALUES(date),
               note       = VALUES(note),
               receipt    = VALUES(receipt),
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->bind_param('isdssss', $userId, $extId, $amount, $cat, $date, $note, $receipt);
        $stmt->execute();
        $stmt->close();

        $created = sw_fetch_expense($db, $userId, $extId);
        sw_created(['expense' => $created]);
    }

    // ── PUT — update ──────────────────────────────────────────────────────────
    if ($method === 'PUT' || $method === 'PATCH') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $existing = sw_fetch_expense_raw($db, $userId, $id);
        if ($existing === null) {
            sw_fail('Expense not found.', 404);
        }

        $body    = sw_body();
        $amount  = isset($body['amount'])   ? (float)  $body['amount']   : (float)  $existing['amount'];
        $cat     = isset($body['category']) ? (string) $body['category'] : (string) $existing['category'];
        $date    = isset($body['date'])     ? sw_coerce_date($body['date']) : (string) $existing['date'];
        $note    = array_key_exists('note', $body)
                       ? (($body['note'] !== null && $body['note'] !== '') ? (string) $body['note'] : null)
                       : $existing['note'];
        $receipt = array_key_exists('receipt', $body)
                       ? (($body['receipt'] !== null && $body['receipt'] !== '') ? (string) $body['receipt'] : null)
                       : $existing['receipt'];

        if ($amount <= 0) {
            sw_fail('amount must be a positive number.');
        }
        if ($date === '') {
            sw_fail('date must be YYYY-MM-DD.');
        }
        // [SEC F-05] Enforce length limits.
        if ($note !== null && strlen($note) > 500) {
            sw_fail('Note too long. Maximum 500 characters.');
        }
        // [SEC F-09] Category length limit.
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        // [SEC F-04] Receipt size and MIME type validation.
        if ($receipt !== null) {
            if (strlen($receipt) > 819200) {
                sw_fail('Receipt image too large. Maximum 600 KB.');
            }
            if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $receipt)) {
                sw_fail('Invalid receipt format. Must be a JPEG, PNG, or WebP image.');
            }
        }
        $stmt->bind_param('dssssis', $amount, $cat, $date, $note, $receipt, $userId, $id);
        $stmt->execute();
        $stmt->close();

        sw_ok(['expense' => sw_fetch_expense($db, $userId, $id)]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $stmt = $db->prepare(
            'DELETE FROM expenses WHERE user_id = ? AND external_id = ?'
        );
        $stmt->bind_param('is', $userId, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            sw_fail('Expense not found.', 404);
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

function sw_format_expense(array $row): array
{
    return [
        'id'       => $row['id'],
        'amount'   => (float) $row['amount'],
        'category' => (string) $row['category'],
        'date'     => (string) $row['date'],
        'note'     => $row['note'] !== null ? (string) $row['note'] : null,
        'receipt'  => $row['receipt'] !== null ? (string) $row['receipt'] : null,
    ];
}

function sw_fetch_expense(mysqli $db, int $userId, string $extId): ?array
{
    $row = sw_fetch_expense_raw($db, $userId, $extId);
    return $row !== null ? sw_format_expense($row) : null;
}

function sw_fetch_expense_raw(mysqli $db, int $userId, string $extId): ?array
{
    $stmt = $db->prepare(
        'SELECT external_id AS id, amount, category, date, note, receipt
         FROM expenses WHERE user_id = ? AND external_id = ? LIMIT 1'
    );
    $stmt->bind_param('is', $userId, $extId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
