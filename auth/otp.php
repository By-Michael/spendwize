<?php
/**
 * SpendWise — Auth: OTP (send + verify)
 *
 * POST /auth/otp.php?action=send
 * Body: { email }
 * Returns: { ok, data: { expiresIn, maskedEmail, message } }
 *
 * POST /auth/otp.php?action=verify
 * Body: { email, code }
 * Returns: { ok, data: { verified: true } }
 */

declare(strict_types=1);
require_once __DIR__ . '/../api/_bootstrap.php';

try {
    if (sw_method() !== 'POST') {
        sw_fail('Method not allowed.', 405);
    }

    $action = trim((string) sw_param('action', ''));
    $body   = sw_body();
    $db     = sw_db();

    // ── Send OTP ──────────────────────────────────────────────────────────────
    if ($action === 'send') {
        $email = sw_normalize_email((string) ($body['email'] ?? ''));
        if ($email === '') {
            sw_fail('Enter your email.');
        }

        // [SEC] Email format + length validation
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sw_fail('Please enter a valid email address.');
        }

        $userRow = sw_find_user_by_email($db, $email);

        // [SEC] Account enumeration protection: always return an identical success
        // response whether or not the email belongs to a real account. An attacker
        // watching responses can no longer tell which addresses are registered.
        if ($userRow === null) {
            sw_ok([
                'expiresIn'   => SW_OTP_EXPIRES_IN,
                'maskedEmail' => sw_mask_email($email),
                'message'     => 'If that email is registered, we sent a code to ' . sw_mask_email($email) . '.',
            ]);
        }

        $code      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + SW_OTP_EXPIRES_IN);
        $userId    = (int) $userRow['id'];

        $stmt = $db->prepare(
            'INSERT INTO password_reset_codes (user_id, email, code, attempts, expires_at)
             VALUES (?, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE
               email = VALUES(email), code = VALUES(code),
               attempts = 0,
               expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP'
        );
        $stmt->bind_param('isss', $userId, $email, $code, $expiresAt);
        $stmt->execute();
        $stmt->close();

        sw_forget_verified_reset($email);

        try {
            sw_send_password_reset_email(
                (string) ($userRow['name'] ?? ''),
                $email,
                $code,
                SW_OTP_EXPIRES_IN
            );
        } catch (Throwable $e) {
            sw_delete_password_reset_code($db, $userId);
            sw_fail($e->getMessage(), 500);
        }

        sw_ok([
            'expiresIn'   => SW_OTP_EXPIRES_IN,
            'maskedEmail' => sw_mask_email($email),
            'message'     => 'If that email is registered, we sent a code to ' . sw_mask_email($email) . '.',
        ]);
    }

    // ── Verify OTP ────────────────────────────────────────────────────────────
    if ($action === 'verify') {
        $email = sw_normalize_email((string) ($body['email'] ?? ''));
        $code  = trim((string) ($body['code'] ?? ''));

        if ($email === '' || $code === '') {
            sw_fail('Email and code are required.');
        }

        // [SEC] Enforce exactly 6 digits
        if (!preg_match('/^\d{6}$/', $code)) {
            sw_fail('Please enter the full 6-digit code.');
        }

        $userRow = sw_find_user_by_email($db, $email);
        if ($userRow === null) {
            sw_fail('No account found.');
        }

        $userId = (int) $userRow['id'];
        $stmt   = $db->prepare(
            'SELECT code, expires_at, attempts FROM password_reset_codes WHERE user_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $otpRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($otpRow === null) {
            sw_fail('No OTP found. Request a new one.');
        }
        if (strtotime((string) $otpRow['expires_at']) < time()) {
            sw_forget_verified_reset($email);
            sw_fail('OTP expired. Request a new one.');
        }

        // [SEC] Attempt limit — invalidate after 5 wrong guesses
        $attempts = (int) ($otpRow['attempts'] ?? 0);
        if ($attempts >= 5) {
            // Wipe the code so they must request a fresh one
            $del = $db->prepare('DELETE FROM password_reset_codes WHERE user_id = ?');
            $del->bind_param('i', $userId);
            $del->execute();
            $del->close();
            sw_forget_verified_reset($email);
            sw_fail('Too many incorrect attempts. Please request a new code.');
        }

        // [SEC] Timing-safe comparison — prevents code-character timing attacks
        if (!hash_equals((string) $otpRow['code'], $code)) {
            // Increment attempt counter
            $upd = $db->prepare(
                'UPDATE password_reset_codes SET attempts = attempts + 1 WHERE user_id = ?'
            );
            $upd->bind_param('i', $userId);
            $upd->execute();
            $upd->close();
            $remaining = 4 - $attempts;
            sw_fail($remaining > 0
                ? 'Incorrect code. ' . $remaining . ' attempt' . ($remaining !== 1 ? 's' : '') . ' remaining.'
                : 'Incorrect code. No more attempts — please request a new code.'
            );
        }

        sw_mark_reset_verified($email);
        sw_ok(['verified' => true]);
    }

    sw_fail('action must be "send" or "verify".');

} catch (Throwable $e) {
    sw_json_response(500, ['ok' => false, 'error' => $e->getMessage()]);
}
