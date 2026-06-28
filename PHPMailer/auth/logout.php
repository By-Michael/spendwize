<?php
/**
 * SpendWise — Auth: Logout
 *
 * POST /auth/logout.php
 *
 * Returns: { ok, data: { loggedOut: true } }
 */

declare(strict_types=1);
require_once __DIR__ . '/../api/_bootstrap.php';

try {
    if (sw_method() !== 'POST') {
        sw_fail('Method not allowed.', 405);
    }

    sw_clear_session();
    sw_ok(['loggedOut' => true]);

} catch (Throwable $e) {
    sw_json_response(500, ['ok' => false, 'error' => $e->getMessage()]);
}
