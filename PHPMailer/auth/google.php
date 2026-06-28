<?php
/**
 * SpendWise — Auth: Google Login
 *
 * POST /auth/google.php
 * Body: { credential }  (Google One-Tap JWT)
 *
 * Returns: { ok, data: { user, state } }
 */

declare(strict_types=1);
require_once __DIR__ . '/../api/_bootstrap.php';

try {
    if (sw_method() !== 'POST') {
        sw_fail('Method not allowed.', 405);
    }

    $body       = sw_body();
    $credential = trim((string) ($body['credential'] ?? ''));

    if ($credential === '') {
        sw_fail('Missing Google credential.');
    }

    $db         = sw_db();
    $googleUser = sw_verify_google_credential($credential);
    $userRow    = sw_sync_google_user($db, $googleUser);
    $user       = sw_user_payload($userRow);
    sw_store_session($user);

    sw_ok([
        'user'  => $user,
        'state' => sw_load_state($db, (int) $userRow['id']),
    ]);

} catch (Throwable $e) {
    sw_json_response(500, ['ok' => false, 'error' => $e->getMessage()]);
}
