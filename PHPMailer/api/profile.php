<?php
/**
 * SpendWise — Profile API
 *
 * GET   /api/profile.php                 Get current user + preferences
 * PUT   /api/profile.php                 Update profile fields (name, phone, avatar)
 * PUT   /api/profile.php?section=prefs   Update preferences (darkMode, language, notifications, categories)
 * PUT   /api/profile.php?section=password  Change password (requires current_password)
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
        $userRow = sw_find_user_by_id($db, $userId);
        if ($userRow === null) {
            sw_fail('User not found.', 404);
        }

        $prefs = sw_load_prefs($db, $userId);

        sw_ok([
            'user'  => sw_user_payload($userRow),
            'prefs' => $prefs,
        ]);
    }

    // ── PUT ───────────────────────────────────────────────────────────────────
    if ($method === 'PUT' || $method === 'PATCH') {
        $section = trim((string) sw_param('section', 'profile'));
        $body    = sw_body();

        // -- Password change --
        if ($section === 'password') {
            $userRow = sw_find_user_by_id($db, $userId);
            if ($userRow === null) {
                sw_fail('User not found.', 404);
            }

            $current = (string) ($body['current_password'] ?? '');
            $newPass = (string) ($body['new_password'] ?? '');

            if ($current === '' || $newPass === '') {
                sw_fail('current_password and new_password are required.');
            }
            if (strlen($newPass) < 8) {
                sw_fail('New password must be at least 8 characters.');
            }

            // Google-only accounts might have no hash
            $hash = (string) ($userRow['password_hash'] ?? '');
            if ($hash === '') {
                sw_fail('This account has no password. Use Forgot Password to set one.');
            }
            if (!password_verify($current, $hash)) {
                sw_fail('Current password is incorrect.', 403);
            }

            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt    = $db->prepare(
                'UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            );
            $stmt->bind_param('si', $newHash, $userId);
            $stmt->execute();
            $stmt->close();

            sw_ok(['updated' => true]);
        }

        // -- Preferences --
        if ($section === 'prefs') {
            $userRow = sw_find_user_by_id($db, $userId);
            if ($userRow === null) {
                sw_fail('User not found.', 404);
            }

            $current = sw_load_prefs($db, $userId);

            $darkMode   = array_key_exists('darkMode', $body)
                              ? (int)(bool) $body['darkMode']
                              : (int) $current['darkMode'];
            $notifEmail = array_key_exists('notifications', $body) && isset($body['notifications']['email'])
                              ? (int)(bool) $body['notifications']['email']
                              : (int) $current['notifications']['email'];
            $language   = isset($body['language']) && in_array($body['language'], ['en', 'am'], true)
                              ? (string) $body['language']
                              : (string) $current['language'];
            $categories = isset($body['categories']) && is_array($body['categories'])
                              ? $body['categories']
                              : $current['categories'];
            $notifSeenAt = array_key_exists('notifSeenAt', $body)
                               ? sw_coerce_datetime_nullable($body['notifSeenAt'])
                               : sw_coerce_datetime_nullable($current['notifSeenAt'] ?? null);

            $catJson = json_encode(
                array_values(array_filter($categories, 'is_string')),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $stmt = $db->prepare(
                'INSERT INTO user_preferences
                 (user_id, dark_mode, notifications_email, language, categories, notif_seen_at)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   dark_mode           = VALUES(dark_mode),
                   notifications_email = VALUES(notifications_email),
                   language            = VALUES(language),
                   categories          = VALUES(categories),
                   notif_seen_at       = VALUES(notif_seen_at),
                   updated_at          = CURRENT_TIMESTAMP'
            );
            $stmt->bind_param('iiisss', $userId, $darkMode, $notifEmail, $language, $catJson, $notifSeenAt);
            $stmt->execute();
            $stmt->close();

            sw_ok([
                'user'  => sw_user_payload($userRow),
                'prefs' => sw_load_prefs($db, $userId),
            ]);
        }

        // -- Profile (default) --
        $userRow = sw_find_user_by_id($db, $userId);
        if ($userRow === null) {
            sw_fail('User not found.', 404);
        }

        $name   = array_key_exists('name', $body)   ? trim((string) $body['name'])   : (string) $userRow['name'];
        $phone  = array_key_exists('phone', $body)  ? trim((string) $body['phone'])  : (string) ($userRow['phone'] ?? '');
        $avatar = array_key_exists('avatar', $body) ? $body['avatar']               : $userRow['avatar'];
        $avatar = ($avatar === null || $avatar === '') ? null : (string) $avatar;

        if ($name === '') {
            sw_fail('name cannot be empty.');
        }
        // [SEC] Mirror auth/signup.php — reject injection chars and enforce length
        if (strlen($name) > 100) {
            sw_fail('Name too long. Maximum 100 characters.');
        }
        if (!preg_match('/^[\p{L}\s\.\-\']{1,100}$/u', $name)) {
            sw_fail('Name may only contain letters, spaces, hyphens, and apostrophes.');
        }
        // [SEC F-10] Validate phone format server-side.
        if ($phone !== '' && !preg_match('/^[0-9\s+\-().]{1,20}$/', $phone)) {
            sw_fail('Invalid phone number. Use digits, spaces, +, -, (, ) only (max 20 characters).');
        }
        // [SEC F-03] Enforce server-side avatar size and MIME type — client-side check is bypassable.
        if ($avatar !== null) {
            if (strlen($avatar) > 204800) { // ~150 KB raw
                sw_fail('Avatar too large. Maximum 150 KB.');
            }
            if (!preg_match('/^data:image\/(jpeg|png|webp|gif);base64,/', $avatar)) {
                sw_fail('Invalid avatar format. Must be a JPEG, PNG, WebP, or GIF image.');
            }
        }

        $stmt = $db->prepare(
            'UPDATE users SET name = ?, phone = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $stmt->bind_param('sssi', $name, $phone, $avatar, $userId);
        $stmt->execute();
        $stmt->close();

        $freshRow = sw_find_user_by_id($db, $userId);
        if ($freshRow === null) {
            sw_fail('User not found.', 404);
        }

        $updatedUser = sw_user_payload($freshRow);
        sw_store_session($updatedUser);

        sw_ok([
            'user'  => $updatedUser,
            'prefs' => sw_load_prefs($db, $userId),
        ]);
    }

    sw_fail('Method not allowed.', 405);

} catch (Throwable $e) {
    // [SEC F-06] Never expose internal exception details to the client.
    error_log('[SpendWise] API error in : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sw_json_response(500, ['ok' => false, 'error' => 'An unexpected error occurred. Please try again.']);
}

// ── Private helpers ───────────────────────────────────────────────────────────

function sw_load_prefs(mysqli $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT dark_mode, notifications_email, language, categories, notif_seen_at
         FROM user_preferences WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null) {
        return [
            'darkMode'      => false,
            'notifications' => ['email' => true],
            'language'      => 'en',
            'categories'    => [],
            'notifSeenAt'   => null,
        ];
    }

    $categories = [];
    if ($row['categories'] !== null) {
        $decoded = json_decode((string) $row['categories'], true);
        if (is_array($decoded)) {
            $categories = $decoded;
        }
    }

    return [
        'darkMode'      => (bool) $row['dark_mode'],
        'notifications' => ['email' => (bool) $row['notifications_email']],
        'language'      => (string) $row['language'],
        'categories'    => $categories,
        'notifSeenAt'   => $row['notif_seen_at'] !== null ? (string) $row['notif_seen_at'] : null,
    ];
}
