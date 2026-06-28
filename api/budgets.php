<?php
/**
 * SpendWise — Budgets API
 *
 * GET    /api/budgets.php                 List all budgets
 * GET    /api/budgets.php?id=<ext_id>     Get single budget
 * GET    /api/budgets.php?month=<YYYY-MM> Filter by month
 * POST   /api/budgets.php                 Create budget
 * PUT    /api/budgets.php?id=<ext_id>     Update budget
 * DELETE /api/budgets.php?id=<ext_id>     Delete budget
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
                'SELECT external_id AS id, category, month, limit_amount AS `limit`
                 FROM budgets WHERE user_id = ? AND external_id = ? LIMIT 1'
            );
            $stmt->bind_param('is', $userId, $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row === null) {
                sw_fail('Budget not found.', 404);
            }

            sw_ok(['budget' => sw_format_budget($row)]);
        }

        // List — optional month filter
        $where  = ['user_id = ?'];
        $types  = 'i';
        $params = [$userId];

        $month = sw_coerce_month(sw_param('month', ''));
        if ($month !== '') {
            $where[]  = 'month = ?';
            $types   .= 's';
            $params[] = $month;
        }

        $category = trim((string) sw_param('category', ''));
        if ($category !== '') {
            $where[]  = 'category = ?';
            $types   .= 's';
            $params[] = $category;
        }

        $stmt = $db->prepare(
            'SELECT external_id AS id, category, month, limit_amount AS `limit`
             FROM budgets
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY month DESC, id ASC'
        );
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $budgets = [];
        while ($row = $res->fetch_assoc()) {
            $budgets[] = sw_format_budget($row);
        }
        $stmt->close();

        sw_ok(['budgets' => $budgets]);
    }

    // ── POST — create ─────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $body  = sw_body();
        $extId = sw_coerce_ext_id($body['id'] ?? '');
        $cat   = trim((string) ($body['category'] ?? ''));
        $month = sw_coerce_month($body['month'] ?? '');
        $limit = (float) ($body['limit'] ?? 0);

        if ($extId === '') {
            sw_fail('id is required.');
        }
        if ($cat === '') {
            sw_fail('category is required.');
        }
        // [SEC F-05 / F-09] Enforce category length limit.
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        if ($month === '') {
            sw_fail('month must be YYYY-MM.');
        }
        if ($limit <= 0) {
            sw_fail('limit must be a positive number.');
        }

        $stmt = $db->prepare(
            'INSERT INTO budgets (user_id, external_id, category, month, limit_amount)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               category     = VALUES(category),
               month        = VALUES(month),
               limit_amount = VALUES(limit_amount),
               updated_at   = CURRENT_TIMESTAMP'
        );
        $stmt->bind_param('isssd', $userId, $extId, $cat, $month, $limit);
        $stmt->execute();
        $stmt->close();

        sw_created(['budget' => sw_fetch_budget($db, $userId, $extId)]);
    }

    // ── PUT — update ──────────────────────────────────────────────────────────
    if ($method === 'PUT' || $method === 'PATCH') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $existing = sw_fetch_budget_raw($db, $userId, $id);
        if ($existing === null) {
            sw_fail('Budget not found.', 404);
        }

        $body  = sw_body();
        $cat   = isset($body['category']) ? trim((string) $body['category']) : (string) $existing['category'];
        $month = isset($body['month'])    ? sw_coerce_month($body['month'])  : (string) $existing['month'];
        $limit = isset($body['limit'])    ? (float) $body['limit']           : (float)  $existing['limit'];

        if ($cat === '') {
            sw_fail('category cannot be empty.');
        }
        // [SEC F-05 / F-09] Enforce category length limit.
        if (strlen($cat) > 60) {
            sw_fail('Category too long. Maximum 60 characters.');
        }
        if ($month === '') {
            sw_fail('month must be YYYY-MM.');
        }
        if ($limit <= 0) {
            sw_fail('limit must be a positive number.');
        }

        $stmt = $db->prepare(
            'UPDATE budgets
             SET category = ?, month = ?, limit_amount = ?, updated_at = CURRENT_TIMESTAMP
             WHERE user_id = ? AND external_id = ?'
        );
        $stmt->bind_param('ssdis', $cat, $month, $limit, $userId, $id);
        $stmt->execute();
        $stmt->close();

        sw_ok(['budget' => sw_fetch_budget($db, $userId, $id)]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id = sw_coerce_ext_id(sw_param('id', ''));
        if ($id === '') {
            sw_fail('id query parameter is required.');
        }

        $stmt = $db->prepare(
            'DELETE FROM budgets WHERE user_id = ? AND external_id = ?'
        );
        $stmt->bind_param('is', $userId, $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            sw_fail('Budget not found.', 404);
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

function sw_format_budget(array $row): array
{
    return [
        'id'       => $row['id'],
        'category' => (string) $row['category'],
        'month'    => (string) $row['month'],
        'limit'    => (float) $row['limit'],
    ];
}

function sw_fetch_budget(mysqli $db, int $userId, string $extId): ?array
{
    $row = sw_fetch_budget_raw($db, $userId, $extId);
    return $row !== null ? sw_format_budget($row) : null;
}

function sw_fetch_budget_raw(mysqli $db, int $userId, string $extId): ?array
{
    $stmt = $db->prepare(
        'SELECT external_id AS id, category, month, limit_amount AS `limit`
         FROM budgets WHERE user_id = ? AND external_id = ? LIMIT 1'
    );
    $stmt->bind_param('is', $userId, $extId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
