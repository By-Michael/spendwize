<?php
/**
 * SpendWise — Auth: Reset Password
 *
 * POST /auth/reset-password.php
 * Body: { email, password }   (requires prior OTP verification)
 *
 * Returns: { ok, data: { updated: true } }
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
        sw_fail('Email and password are required.');
    }
    if (strlen($password) < 8) {
        sw_fail('Minimum 8 characters.');
    }
    // [SEC] Prevent bcrypt silent truncation at 72 bytes
    if (strlen($password) > 128) {
        sw_fail('Password too long. Maximum 128 characters.');
    }
    if (!sw_reset_is_verified($email)) {
        sw_fail('Verify your OTP before resetting the password.');
    }

    $db      = sw_db();
    $userRow = sw_find_user_by_email($db, $email);
    if ($userRow === null) {
        sw_fail('No account found.');
    }

    $hash   = password_hash($password, PASSWORD_DEFAULT);
    $userId = (int) $userRow['id'];

    $stmt = $db->prepare(
        'UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    $stmt->close();

    sw_delete_password_reset_code($db, $userId);
    sw_forget_verified_reset($email);

    sw_ok(['updated' => true]);

} catch (Throwable $e) {
    sw_json_response(500, ['ok' => false, 'error' => $e->getMessage()]);
}
