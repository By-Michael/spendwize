<?php
/**
 * SpendWise — Auth: Login
 *
 * POST /auth/login.php
 * Body: { email, password }
 *
 * Returns: { ok, data: { user, state } }
 */

declare(strict_types=1);
require_once __DIR__ . '/../api/_bootstrap.php';

try {
    if (sw_method() !== 'POST') {
        sw_fail('Method not allowed.', 405);
    }

    $body     = sw_body();
    $email    = sw_normalize_email((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($email === '' || $password === '') {
        sw_fail('Please fill in all fields.');
    }

    // [SEC] Email format + length validation (prevents malformed strings reaching the DB)
    if (strlen($email) > 190) {
        sw_fail('Please enter a valid email address.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sw_fail('Please enter a valid email address.');
    }

    // [SEC] Prevent bcrypt silent truncation at 72 bytes
    if (strlen($password) > 128) {
        sw_fail('Password too long. Maximum 128 characters.');
    }

    $db      = sw_db();
    $userRow = sw_find_user_by_email($db, $email);

    if ($userRow === null) {
        sw_fail('No account found with this email.', 404);
    }

    if (!sw_user_has_password($userRow)) {
        if (($userRow['provider'] ?? 'email') === 'google') {
            sw_fail('This Google account does not have a password yet. Use Forgot Password to create one or continue with Google.');
        }
        sw_fail('This account does not have a password yet.');
    }

    if (!password_verify($password, (string) $userRow['password_hash'])) {
        sw_fail('Incorrect password.');
    }

    $user = sw_user_payload($userRow);
    sw_store_session($user);

    sw_ok([
        'user'  => $user,
        'state' => sw_load_state($db, (int) $userRow['id']),
    ]);

} catch (Throwable $e) {
    sw_json_response(500, ['ok' => false, 'error' => $e->getMessage()]);
}
