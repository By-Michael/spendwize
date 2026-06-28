<?php
/**
 * SpendWise — Auth: Signup
 *
 * POST /auth/signup.php
 * Body: { name, email, password }
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
    $name     = trim((string) ($body['name'] ?? ''));
    $email    = sw_normalize_email((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($name === '') {
        sw_fail('Please enter your full name.');
    }
    // [SEC] Name length + character restriction
    if (strlen($name) > 100) {
        sw_fail('Name too long. Maximum 100 characters.');
    }
    if (!preg_match('/^[\p{L}\s\.\-\']{1,100}$/u', $name)) {
        sw_fail('Name may only contain letters, spaces, hyphens, and apostrophes.');
    }
    if ($email === '') {
        sw_fail('Please enter your email.');
    }
    // [SEC] Email format + length validation
    if (strlen($email) > 190) {
        sw_fail('Please enter a valid email address.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sw_fail('Please enter a valid email address.');
    }
    if (strlen($password) < 8) {
        sw_fail('Password must be at least 8 characters.');
    }
    // [SEC] Prevent bcrypt silent truncation at 72 bytes
    if (strlen($password) > 128) {
        sw_fail('Password too long. Maximum 128 characters.');
    }

    $db = sw_db();

    if (sw_find_user_by_email($db, $email) !== null) {
        sw_fail('Account already exists with this email.');
    }

    $hash     = password_hash($password, PASSWORD_DEFAULT);
    $provider = 'email';
    $phone    = '';
    $avatar   = null;

    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, phone, avatar, provider)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssss', $name, $email, $hash, $phone, $avatar, $provider);
    $stmt->execute();
    $stmt->close();

    $userRow = sw_find_user_by_id($db, (int) $db->insert_id);
    if ($userRow === null) {
        throw new RuntimeException('Could not load the newly created user.');
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
