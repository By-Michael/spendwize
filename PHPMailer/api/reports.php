<?php
/**
 * SpendWise — Reports API
 *
 * GET /api/reports.php?type=monthly_trend[&months=12]
 *     Monthly expense totals for the last N months (default 12).
 *
 * GET /api/reports.php?type=category_breakdown[&month=YYYY-MM][&from=YYYY-MM-DD&to=YYYY-MM-DD]
 *     Total and percentage per category.
 *
 * GET /api/reports.php?type=budget_vs_actual[&month=YYYY-MM]
 *     Side-by-side limit vs spent for each budgeted category in the given month.
 *
 * GET /api/reports.php?type=daily_spending[&month=YYYY-MM]
 *     Day-by-day totals for a given month — useful for bar/line charts.
 *
 * GET /api/reports.php?type=top_expenses[&month=YYYY-MM][&limit=10]
 *     The highest individual expenses in the period.
 *
 * GET /api/reports.php?type=recurring_summary
 *     Monthly committed cost from active recurring items.
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

    $type = trim((string) sw_param('type', ''));
    if ($type === '') {
        sw_fail('type query parameter is required. Valid types: monthly_trend, category_breakdown, budget_vs_actual, daily_spending, top_expenses, recurring_summary.');
    }

    switch ($type) {

        // ── Monthly trend ─────────────────────────────────────────────────────
        case 'monthly_trend': {
            $months = min(60, max(1, (int) sw_param('months', 12)));

            // Build a list of YYYY-MM strings going back $months from today
            $periods = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $periods[] = date('Y-m', strtotime("-{$i} months"));
            }

            // One query for all totals at once
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(date, '%Y-%m') AS month,
                        SUM(amount) AS total,
                        COUNT(*) AS `count`
                 FROM expenses
                 WHERE user_id = ?
                   AND DATE_FORMAT(date, '%Y-%m') >= ?
                 GROUP BY month
                 ORDER BY month ASC"
            );
            $oldest = $periods[0];
            $stmt->bind_param('is', $userId, $oldest);
            $stmt->execute();
            $res = $stmt->get_result();

            $byMonth = [];
            while ($row = $res->fetch_assoc()) {
                $byMonth[$row['month']] = ['total' => (float) $row['total'], 'count' => (int) $row['count']];
            }
            $stmt->close();

            // Fill zero for months with no expenses
            $data = [];
            foreach ($periods as $p) {
                $data[] = [
                    'month' => $p,
                    'total' => $byMonth[$p]['total'] ?? 0.0,
                    'count' => $byMonth[$p]['count'] ?? 0,
                ];
            }

            sw_ok(['type' => 'monthly_trend', 'months' => $months, 'data' => $data]);
        }

        // ── Category breakdown ────────────────────────────────────────────────
        case 'category_breakdown': {
            [$monthStart, $monthEnd] = sw_report_date_range();

            $stmt = $db->prepare(
                'SELECT category,
                        SUM(amount) AS total,
                        COUNT(*)    AS `count`
                 FROM expenses
                 WHERE user_id = ? AND date >= ? AND date <= ?
                 GROUP BY category
                 ORDER BY total DESC'
            );
            $stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
            $stmt->execute();
            $res = $stmt->get_result();

            $rows       = [];
            $grandTotal = 0.0;
            while ($row = $res->fetch_assoc()) {
                $t = (float) $row['total'];
                $grandTotal += $t;
                $rows[] = ['category' => (string) $row['category'], 'total' => $t, 'count' => (int) $row['count']];
            }
            $stmt->close();

            // Add percentage
            foreach ($rows as &$r) {
                $r['pct'] = $grandTotal > 0 ? round(($r['total'] / $grandTotal) * 100, 1) : 0.0;
            }
            unset($r);

            sw_ok([
                'type'       => 'category_breakdown',
                'from'       => $monthStart,
                'to'         => $monthEnd,
                'grandTotal' => round($grandTotal, 2),
                'data'       => $rows,
            ]);
        }

        // ── Budget vs actual ──────────────────────────────────────────────────
        case 'budget_vs_actual': {
            $month = sw_coerce_month(sw_param('month', '')) ?: date('Y-m');
            [$y, $m] = explode('-', $month);
            $monthStart = "{$y}-{$m}-01";
            $monthEnd   = date('Y-m-t', strtotime($monthStart));

            // Get budgets for the month
            $stmt = $db->prepare(
                'SELECT category, limit_amount AS `limit`
                 FROM budgets WHERE user_id = ? AND month = ?
                 ORDER BY category ASC'
            );
            $stmt->bind_param('is', $userId, $month);
            $stmt->execute();
            $res     = $stmt->get_result();
            $budgets = [];
            while ($row = $res->fetch_assoc()) {
                $budgets[(string) $row['category']] = (float) $row['limit'];
            }
            $stmt->close();

            // Get actuals for the same month
            $stmt = $db->prepare(
                'SELECT category, SUM(amount) AS total
                 FROM expenses WHERE user_id = ? AND date >= ? AND date <= ?
                 GROUP BY category'
            );
            $stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
            $stmt->execute();
            $res   = $stmt->get_result();
            $spent = [];
            while ($row = $res->fetch_assoc()) {
                $spent[(string) $row['category']] = (float) $row['total'];
            }
            $stmt->close();

            // Merge: include all budgeted categories, plus unbudgeted ones with spend
            $allCats = array_unique(array_merge(array_keys($budgets), array_keys($spent)));
            sort($allCats);

            $data = [];
            foreach ($allCats as $cat) {
                $limit   = $budgets[$cat] ?? null;
                $actual  = $spent[$cat]   ?? 0.0;
                $data[] = [
                    'category'  => $cat,
                    'limit'     => $limit,
                    'spent'     => round($actual, 2),
                    'remaining' => $limit !== null ? round($limit - $actual, 2) : null,
                    'pct'       => ($limit !== null && $limit > 0)
                                      ? round(($actual / $limit) * 100, 1)
                                      : null,
                    'over'      => $limit !== null && $actual > $limit,
                    'budgeted'  => $limit !== null,
                ];
            }

            sw_ok(['type' => 'budget_vs_actual', 'month' => $month, 'data' => $data]);
        }

        // ── Daily spending ────────────────────────────────────────────────────
        case 'daily_spending': {
            $month = sw_coerce_month(sw_param('month', '')) ?: date('Y-m');
            [$y, $mo] = explode('-', $month);
            $monthStart = "{$y}-{$mo}-01";
            $monthEnd   = date('Y-m-t', strtotime($monthStart));
            $daysInMonth = (int) date('t', strtotime($monthStart));

            $stmt = $db->prepare(
                'SELECT date, SUM(amount) AS total, COUNT(*) AS `count`
                 FROM expenses
                 WHERE user_id = ? AND date >= ? AND date <= ?
                 GROUP BY date
                 ORDER BY date ASC'
            );
            $stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
            $stmt->execute();
            $res = $stmt->get_result();

            $byDay = [];
            while ($row = $res->fetch_assoc()) {
                $byDay[$row['date']] = ['total' => (float) $row['total'], 'count' => (int) $row['count']];
            }
            $stmt->close();

            $data = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%s-%02d', $month, $d);
                $data[] = [
                    'date'  => $dateStr,
                    'day'   => $d,
                    'total' => $byDay[$dateStr]['total'] ?? 0.0,
                    'count' => $byDay[$dateStr]['count'] ?? 0,
                ];
            }

            sw_ok(['type' => 'daily_spending', 'month' => $month, 'data' => $data]);
        }

        // ── Top expenses ──────────────────────────────────────────────────────
        case 'top_expenses': {
            [$monthStart, $monthEnd] = sw_report_date_range();
            $limit = min(50, max(1, (int) sw_param('limit', 10)));

            $stmt = $db->prepare(
                'SELECT external_id AS id, amount, category, date, note
                 FROM expenses
                 WHERE user_id = ? AND date >= ? AND date <= ?
                 ORDER BY amount DESC
                 LIMIT ?'
            );
            $stmt->bind_param('issi', $userId, $monthStart, $monthEnd, $limit);
            $stmt->execute();
            $res = $stmt->get_result();

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    'id'       => $row['id'],
                    'amount'   => (float)  $row['amount'],
                    'category' => (string) $row['category'],
                    'date'     => (string) $row['date'],
                    'note'     => $row['note'] !== null ? (string) $row['note'] : null,
                ];
            }
            $stmt->close();

            sw_ok([
                'type' => 'top_expenses',
                'from' => $monthStart,
                'to'   => $monthEnd,
                'data' => $data,
            ]);
        }

        // ── Recurring summary ─────────────────────────────────────────────────
        case 'recurring_summary': {
            $stmt = $db->prepare(
                'SELECT external_id AS id, name, amount, category, frequency, next_due AS nextDue
                 FROM recurring_items
                 WHERE user_id = ? AND active = 1
                 ORDER BY amount DESC'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();

            $items       = [];
            $monthlyEquivalent = 0.0;

            // Frequency → approximate monthly multiplier
            $multipliers = [
                'daily'       => 30.44,
                'weekly'      => 4.33,
                'fortnightly' => 2.17,
                'monthly'     => 1.0,
                'quarterly'   => 1 / 3,
                'yearly'      => 1 / 12,
            ];

            while ($row = $res->fetch_assoc()) {
                $freq   = (string) $row['frequency'];
                $amount = (float)  $row['amount'];
                $mult   = $multipliers[$freq] ?? 1.0;
                $monthly = round($amount * $mult, 2);

                $items[] = [
                    'id'              => $row['id'],
                    'name'            => (string) $row['name'],
                    'amount'          => $amount,
                    'category'        => (string) $row['category'],
                    'frequency'       => $freq,
                    'nextDue'         => (string) $row['nextDue'],
                    'monthlyEquivalent' => $monthly,
                ];
                $monthlyEquivalent += $monthly;
            }
            $stmt->close();

            sw_ok([
                'type'                    => 'recurring_summary',
                'activeCount'             => count($items),
                'monthlyEquivalent'       => round($monthlyEquivalent, 2),
                'annualEquivalent'        => round($monthlyEquivalent * 12, 2),
                'data'                    => $items,
            ]);
        }

        default:
            sw_fail('Unknown report type. Valid types: monthly_trend, category_breakdown, budget_vs_actual, daily_spending, top_expenses, recurring_summary.', 400);
    }

} catch (Throwable $e) {
    // [SEC F-06] Never expose internal exception details to the client.
    error_log('[SpendWise] API error in : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sw_json_response(500, ['ok' => false, 'error' => 'An unexpected error occurred. Please try again.']);
}

// ── Private helpers ───────────────────────────────────────────────────────────

/**
 * Resolve the from/to date range from query params.
 * Priority: ?from & ?to > ?month > current month.
 *
 * @return array{0: string, 1: string}  [from YYYY-MM-DD, to YYYY-MM-DD]
 */
function sw_report_date_range(): array
{
    $from = sw_coerce_date(sw_param('from', ''));
    $to   = sw_coerce_date(sw_param('to', ''));
    if ($from !== '' && $to !== '') {
        return [$from, $to];
    }

    $month = sw_coerce_month(sw_param('month', '')) ?: date('Y-m');
    [$y, $m] = explode('-', $month);
    $start = "{$y}-{$m}-01";
    $end   = date('Y-m-t', strtotime($start));
    return [$start, $end];
}
