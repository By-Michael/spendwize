<?php
/**
 * SpendWise — Dashboard API
 *
 * GET /api/dashboard.php              Current-month summary
 * GET /api/dashboard.php?month=YYYY-MM  Specific month summary
 *
 * Returns a single pre-aggregated payload so the dashboard page
 * makes ONE request instead of four.
 *
 * Response shape:
 * {
 *   "ok": true,
 *   "data": {
 *     "month": "2025-06",
 *     "totalExpenses": 1234.56,
 *     "expensesByCategory": [
 *       { "category": "Food", "total": 400.00, "count": 12 }, ...
 *     ],
 *     "budgetStatus": [
 *       {
 *         "id": "...", "category": "Food", "month": "2025-06",
 *         "limit": 500.00, "spent": 400.00, "remaining": 100.00,
 *         "pct": 80.0, "over": false
 *       }, ...
 *     ],
 *     "upcomingBills": [ <bill objects due in next 30 days, not paid> ],
 *     "overdueBills":  [ <bill objects past due, not paid> ],
 *     "recurringDueSoon": [ <recurring items with nextDue within 7 days> ],
 *     "recentExpenses":   [ <last 5 expenses> ]
 *   }
 * }
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

try {
    $user   = sw_api_require_auth();
    $userId = (int) $user['id'];
    $db     = sw_db();

    if (sw_method() !== 'GET') {
        sw_fail('Method not allowed.', 405);
    }

    // Determine target month
    $month = sw_coerce_month(sw_param('month', ''));
    if ($month === '') {
        $month = date('Y-m');
    }
    [$year, $mon] = explode('-', $month);
    $monthStart = "{$year}-{$mon}-01";
    $monthEnd   = date('Y-m-t', strtotime($monthStart)); // last day of month

    $today = date('Y-m-d');
    $in30  = date('Y-m-d', strtotime('+30 days'));
    $in7   = date('Y-m-d', strtotime('+7 days'));

    // ── 1. Total expenses this month ──────────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS total
         FROM expenses
         WHERE user_id = ? AND date >= ? AND date <= ?'
    );
    $stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
    $stmt->execute();
    $totalExpenses = (float) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // ── 2. Expenses by category this month ───────────────────────────────────
    $stmt = $db->prepare(
        'SELECT category, SUM(amount) AS total, COUNT(*) AS `count`
         FROM expenses
         WHERE user_id = ? AND date >= ? AND date <= ?
         GROUP BY category
         ORDER BY total DESC'
    );
    $stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
    $stmt->execute();
    $res = $stmt->get_result();

    $expensesByCategory = [];
    $spentByCategory    = []; // for budget status lookup
    while ($row = $res->fetch_assoc()) {
        $cat = (string) $row['category'];
        $tot = (float)  $row['total'];
        $expensesByCategory[] = [
            'category' => $cat,
            'total'    => $tot,
            'count'    => (int) $row['count'],
        ];
        $spentByCategory[$cat] = $tot;
    }
    $stmt->close();

    // ── 3. Budget status for this month ──────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT external_id AS id, category, month, limit_amount AS `limit`
         FROM budgets
         WHERE user_id = ? AND month = ?
         ORDER BY category ASC'
    );
    $stmt->bind_param('is', $userId, $month);
    $stmt->execute();
    $res = $stmt->get_result();

    $budgetStatus = [];
    while ($row = $res->fetch_assoc()) {
        $cat     = (string) $row['category'];
        $limit   = (float)  $row['limit'];
        $spent   = $spentByCategory[$cat] ?? 0.0;
        $remaining = $limit - $spent;
        $pct       = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0.0;

        $budgetStatus[] = [
            'id'        => $row['id'],
            'category'  => $cat,
            'month'     => $row['month'],
            'limit'     => $limit,
            'spent'     => round($spent, 2),
            'remaining' => round($remaining, 2),
            'pct'       => $pct,
            'over'      => $spent > $limit,
        ];
    }
    $stmt->close();

    // ── 4. Upcoming bills (due within 30 days, not paid) ─────────────────────
    $stmt = $db->prepare(
        'SELECT external_id AS id, name, amount, category,
                due_date AS dueDate, status, paid_date AS paidDate, reference
         FROM bills
         WHERE user_id = ? AND status != "paid" AND due_date >= ? AND due_date <= ?
         ORDER BY due_date ASC
         LIMIT 20'
    );
    $stmt->bind_param('iss', $userId, $today, $in30);
    $stmt->execute();
    $res = $stmt->get_result();

    $upcomingBills = [];
    while ($row = $res->fetch_assoc()) {
        $upcomingBills[] = sw_dash_format_bill($row);
    }
    $stmt->close();

    // ── 5. Overdue bills ─────────────────────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT external_id AS id, name, amount, category,
                due_date AS dueDate, status, paid_date AS paidDate, reference
         FROM bills
         WHERE user_id = ? AND status = "overdue"
         ORDER BY due_date ASC
         LIMIT 20'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $overdueBills = [];
    while ($row = $res->fetch_assoc()) {
        $overdueBills[] = sw_dash_format_bill($row);
    }
    $stmt->close();

    // ── 6. Recurring items due within 7 days ─────────────────────────────────
    $stmt = $db->prepare(
        'SELECT external_id AS id, name, amount, category, frequency,
                start_date AS startDate, end_date AS endDate,
                next_due AS nextDue, active
         FROM recurring_items
         WHERE user_id = ? AND active = 1 AND next_due <= ?
         ORDER BY next_due ASC
         LIMIT 10'
    );
    $stmt->bind_param('is', $userId, $in7);
    $stmt->execute();
    $res = $stmt->get_result();

    $recurringDueSoon = [];
    while ($row = $res->fetch_assoc()) {
        $recurringDueSoon[] = [
            'id'        => $row['id'],
            'name'      => (string) $row['name'],
            'amount'    => (float)  $row['amount'],
            'category'  => (string) $row['category'],
            'frequency' => (string) $row['frequency'],
            'startDate' => (string) $row['startDate'],
            'endDate'   => $row['endDate'] !== null ? (string) $row['endDate'] : null,
            'nextDue'   => (string) $row['nextDue'],
            'active'    => (bool)   $row['active'],
        ];
    }
    $stmt->close();

    // ── 7. Recent expenses (last 5) ───────────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT external_id AS id, amount, category, date, note, receipt
         FROM expenses
         WHERE user_id = ?
         ORDER BY date DESC, id DESC
         LIMIT 5'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $recentExpenses = [];
    while ($row = $res->fetch_assoc()) {
        $recentExpenses[] = [
            'id'       => $row['id'],
            'amount'   => (float)  $row['amount'],
            'category' => (string) $row['category'],
            'date'     => (string) $row['date'],
            'note'     => $row['note'] !== null ? (string) $row['note'] : null,
            // receipt intentionally omitted here — heavyweight field
        ];
    }
    $stmt->close();

    // ── Assemble and return ───────────────────────────────────────────────────
    sw_ok([
        'month'             => $month,
        'totalExpenses'     => round($totalExpenses, 2),
        'expensesByCategory' => $expensesByCategory,
        'budgetStatus'      => $budgetStatus,
        'upcomingBills'     => $upcomingBills,
        'overdueBills'      => $overdueBills,
        'recurringDueSoon'  => $recurringDueSoon,
        'recentExpenses'    => $recentExpenses,
    ]);

} catch (Throwable $e) {
    // [SEC F-06] Never expose internal exception details to the client.
    error_log('[SpendWise] API error in : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sw_json_response(500, ['ok' => false, 'error' => 'An unexpected error occurred. Please try again.']);
}

function sw_dash_format_bill(array $row): array
{
    return [
        'id'        => $row['id'],
        'name'      => (string) $row['name'],
        'amount'    => (float)  $row['amount'],
        'category'  => (string) $row['category'],
        'dueDate'   => (string) $row['dueDate'],
        'status'    => (string) $row['status'],
        'paidDate'  => $row['paidDate']  !== null ? (string) $row['paidDate']  : null,
        'reference' => $row['reference'] !== null ? (string) $row['reference'] : null,
    ];
}
