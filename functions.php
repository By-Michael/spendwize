<?php
declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const SW_DB_HOST = '127.0.0.1';
const SW_DB_USER = 'root';
const SW_DB_PASS = '';
const SW_DB_NAME = 'spendwise_app';
const SW_SESSION_KEY = 'spendwise_user';
const SW_RESET_KEY = 'spendwise_verified_resets';
const SW_OTP_EXPIRES_IN = 300;
const SW_GOOGLE_CLIENT_ID = '717478416920-72ranir6avbt3u3ouviq9rgk64n6b3p0.apps.googleusercontent.com';
const SW_GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';
const SW_GOOGLE_CERT_CACHE_TTL = 3600;
const SW_SMTP_HOST = 'smtp.gmail.com';
const SW_SMTP_PORT = 587;
const SW_SMTP_SECURE = 'tls';
const SW_SMTP_TIMEOUT = 20;
const SW_SMTP_USERNAME = '';
const SW_SMTP_PASSWORD = '';
const SW_SMTP_FROM_NAME = 'SpendWise';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function sw_json_response(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    exit;
}

function sw_error(string $message, int $status = 400): never
{
    sw_json_response($status, ['ok' => false, 'error' => $message]);
}

function sw_read_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sw_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function sw_mailer_override_candidates(): array
{
    return [
        __DIR__ . '/mailer.local.php',
        __DIR__ . '/mail.local.php',
        __DIR__ . '/PHPMailer/mailer.local.php',
        __DIR__ . '/PHPMailer/credentials.php',
        __DIR__ . '/PHPMailer/config.php',
    ];
}

function sw_mailer_overrides(): array
{
    static $overrides = null;
    if (is_array($overrides)) {
        return $overrides;
    }

    $overrides = [];

    foreach (sw_mailer_override_candidates() as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }

        $loaded = require $candidate;
        if (is_array($loaded)) {
            $overrides = $loaded;
            break;
        }
    }

    $envMap = [
        'host' => getenv('SW_SMTP_HOST') ?: null,
        'port' => getenv('SW_SMTP_PORT') ?: null,
        'secure' => getenv('SW_SMTP_SECURE') ?: null,
        'username' => getenv('SW_SMTP_USERNAME') ?: null,
        'password' => getenv('SW_SMTP_PASSWORD') ?: null,
        'from_email' => getenv('SW_SMTP_FROM_EMAIL') ?: null,
        'from_name' => getenv('SW_SMTP_FROM_NAME') ?: null,
    ];

    foreach ($envMap as $key => $value) {
        if ($value !== null && $value !== '') {
            $overrides[$key] = $value;
        }
    }

    return $overrides;
}

function sw_mailer_settings(): array
{
    $overrides = sw_mailer_overrides();
    $host = trim((string) ($overrides['host'] ?? SW_SMTP_HOST));
    $port = (int) ($overrides['port'] ?? SW_SMTP_PORT);
    $secure = strtolower(trim((string) ($overrides['secure'] ?? SW_SMTP_SECURE)));
    $username = trim((string) ($overrides['username'] ?? SW_SMTP_USERNAME));
    $password = trim((string) ($overrides['password'] ?? SW_SMTP_PASSWORD));
    if (($host === 'smtp.gmail.com' || str_ends_with(strtolower($username), '@gmail.com')) && preg_match('/\s+/', $password)) {
        $password = preg_replace('/\s+/', '', $password) ?? $password;
    }
    $fromEmail = trim((string) ($overrides['from_email'] ?? $username));
    $fromName = trim((string) ($overrides['from_name'] ?? SW_SMTP_FROM_NAME));

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
        throw new RuntimeException(
            'SMTP sender is not configured. Add credentials to mailer.local.php or SW_SMTP_* environment variables.'
        );
    }

    if (!in_array($secure, ['tls', 'starttls', 'ssl', 'smtps', ''], true)) {
        throw new RuntimeException('Unsupported SMTP security mode. Use tls, starttls, ssl, or smtps.');
    }

    return [
        'host' => $host,
        'port' => $port,
        'secure' => $secure,
        'username' => $username,
        'password' => $password,
        'from_email' => $fromEmail,
        'from_name' => $fromName !== '' ? $fromName : 'SpendWise',
        'timeout' => SW_SMTP_TIMEOUT,
    ];
}

function sw_connect_server(): mysqli
{
    $db = new mysqli(SW_DB_HOST, SW_DB_USER, SW_DB_PASS);
    $db->set_charset('utf8mb4');
    return $db;
}

function sw_db(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    $server = sw_connect_server();
    $server->query(
        'CREATE DATABASE IF NOT EXISTS `' . SW_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $server->close();

    $db = new mysqli(SW_DB_HOST, SW_DB_USER, SW_DB_PASS, SW_DB_NAME);
    $db->set_charset('utf8mb4');
    sw_ensure_schema($db);

    return $db;
}

function sw_ensure_schema(mysqli $db): void
{
    $db->query(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(40) NOT NULL DEFAULT "",
            avatar LONGTEXT NULL,
            provider VARCHAR(20) NOT NULL DEFAULT "email",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if (!sw_column_exists($db, 'users', 'provider_uid')) {
        $db->query('ALTER TABLE users ADD COLUMN provider_uid VARCHAR(191) NULL DEFAULT NULL AFTER provider');
    }

    if (!sw_index_exists($db, 'users', 'uniq_users_provider_uid')) {
        $db->query('ALTER TABLE users ADD UNIQUE KEY uniq_users_provider_uid (provider_uid)');
    }

    $db->query(
        'CREATE TABLE IF NOT EXISTS user_states (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            state_json LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_states_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS password_reset_codes (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            code VARCHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_password_reset_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function sw_session_user(): ?array
{
    $user = $_SESSION[SW_SESSION_KEY] ?? null;
    return is_array($user) ? $user : null;
}

function sw_store_session(array $user): void
{
    $_SESSION[SW_SESSION_KEY] = $user;
}

function sw_clear_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function sw_require_session_user(): array
{
    $user = sw_session_user();
    if ($user === null) {
        sw_error('Authentication required.', 401);
    }

    return $user;
}

function sw_user_payload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'email' => (string) $row['email'],
        'phone' => (string) ($row['phone'] ?? ''),
        'avatar' => ($row['avatar'] ?? '') !== '' ? $row['avatar'] : null,
        'provider' => (string) ($row['provider'] ?? 'email'),
        'hasPassword' => sw_user_has_password($row),
    ];
}

function sw_user_has_password(array $row): bool
{
    return trim((string) ($row['password_hash'] ?? '')) !== '';
}

function sw_column_exists(mysqli $db, string $table, string $column): bool
{
    $schema = SW_DB_NAME;
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('sss', $schema, $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return ((int) ($row['total'] ?? 0)) > 0;
}

function sw_index_exists(mysqli $db, string $table, string $index): bool
{
    $schema = SW_DB_NAME;
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->bind_param('sss', $schema, $table, $index);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return ((int) ($row['total'] ?? 0)) > 0;
}

function sw_find_user_by_email(mysqli $db, string $email): ?array
{
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function sw_find_user_by_id(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function sw_find_user_by_provider_uid(mysqli $db, string $provider, string $providerUid): ?array
{
    $stmt = $db->prepare('SELECT * FROM users WHERE provider = ? AND provider_uid = ? LIMIT 1');
    $stmt->bind_param('ss', $provider, $providerUid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function sw_load_state(mysqli $db, int $userId): ?array
{
    $stmt = $db->prepare('SELECT state_json FROM user_states WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $decoded = json_decode((string) $row['state_json'], true);
    return is_array($decoded) ? $decoded : null;
}

function sw_save_state(mysqli $db, int $userId, array $state): void
{
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Could not encode application state.');
    }

    $stmt = $db->prepare(
        'INSERT INTO user_states (user_id, state_json) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->bind_param('is', $userId, $json);
    $stmt->execute();
    $stmt->close();
}

function sw_clear_state(mysqli $db, int $userId): void
{
    $stmt = $db->prepare('DELETE FROM user_states WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function sw_delete_password_reset_code(mysqli $db, int $userId): void
{
    $stmt = $db->prepare('DELETE FROM password_reset_codes WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function sw_mask_email(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }

    [$local, $domain] = $parts;
    $localLength = strlen($local);
    if ($localLength <= 2) {
        $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(1, $localLength - 1));
    } else {
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(2, $localLength - 2));
    }

    return $maskedLocal . '@' . $domain;
}

function sw_google_enabled(): bool
{
    return SW_GOOGLE_CLIENT_ID !== '';
}

function sw_google_bootstrap(): array
{
    return [
        'enabled' => sw_google_enabled(),
        'clientId' => SW_GOOGLE_CLIENT_ID,
    ];
}

function sw_base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid token encoding.');
    }

    return $decoded;
}

function sw_ca_bundle_path(): ?string
{
    static $resolved = false;
    static $bundle = null;

    if ($resolved) {
        return $bundle;
    }

    $candidates = array_filter([
        ini_get('curl.cainfo') ?: null,
        ini_get('openssl.cafile') ?: null,
        __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem',
        'C:\Program Files\Git\mingw64\etc\ssl\certs\ca-bundle.crt',
        'C:\Program Files\Git\mingw64\etc\ssl\cert.pem',
        'C:\Program Files\Git\usr\ssl\certs\ca-bundle.crt',
        'C:\Program Files\Git\usr\ssl\cert.pem',
    ]);

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            $bundle = $candidate;
            break;
        }
    }

    $resolved = true;
    return $bundle;
}

function sw_send_password_reset_email(string $recipientName, string $recipientEmail, string $code, int $expiresIn): void
{
    $settings = sw_mailer_settings();
    $caBundle = sw_ca_bundle_path();
    $minutes = max(1, (int) ceil($expiresIn / 60));
    $displayName = trim($recipientName) !== '' ? trim($recipientName) : 'there';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $settings['host'];
        $mail->Port = $settings['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = $settings['password'];
        $mail->Timeout = $settings['timeout'];
        $mail->CharSet = 'UTF-8';

        if (in_array($settings['secure'], ['ssl', 'smtps'], true)) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        if ($caBundle !== null) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'cafile' => $caBundle,
                ],
            ];
        }

        $mail->setFrom($settings['from_email'], $settings['from_name']);
        $mail->addReplyTo($settings['from_email'], $settings['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->Subject = 'Your SpendWise OTP Code';
        $mail->isHTML(true);
        $mail->Body =
            '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#0f172a">' .
            '<p>Hello ' . htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>' .
            '<p>Use the verification code below to reset your SpendWise password:</p>' .
            '<p style="font-size:28px;font-weight:700;letter-spacing:6px;color:#0d9488;margin:20px 0">' .
            htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            '</p>' .
            '<p>This code expires in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.</p>' .
            '<p>If you did not request this code, you can ignore this email.</p>' .
            '</div>';
        $mail->AltBody =
            "Hello {$displayName},\n\n" .
            "Use this SpendWise verification code to reset your password: {$code}\n\n" .
            "This code expires in {$minutes} minute" . ($minutes === 1 ? '' : 's') . ".\n\n" .
            "If you did not request this code, you can ignore this email.";
        $mail->send();
    } catch (MailerException $e) {
        throw new RuntimeException('Could not send the verification code email. Check your SMTP credentials and mail access.');
    }
}

function sw_http_get(string $url): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    $caBundle = sw_ca_bundle_path();

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize the Google verification request.');
        }

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($caBundle !== null) {
            $curlOptions[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($ch, $curlOptions);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            $message = $error !== '' ? $error : ('HTTP ' . $status);
            throw new RuntimeException('Could not fetch Google verification data from ' . $host . ': ' . $message);
        }

        return (string) $body;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => $caBundle,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $statusLine = (string) ($headers[0] ?? '');

    if ($body === false) {
        throw new RuntimeException('Could not fetch Google verification data from ' . $host . '.');
    }

    if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches) || (int) $matches[1] >= 400) {
        throw new RuntimeException('Google verification request failed for ' . $host . '.');
    }

    return $body;
}

function sw_google_cert_cache_file(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'spendwise_google_certs.json';
}

function sw_google_certificates(): array
{
    $cacheFile = sw_google_cert_cache_file();
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (
            is_array($cached)
            && isset($cached['expiresAt'], $cached['certs'])
            && (int) $cached['expiresAt'] > time()
            && is_array($cached['certs'])
        ) {
            return $cached['certs'];
        }
    }

    $body = sw_http_get(SW_GOOGLE_CERTS_URL);
    $certs = json_decode($body, true);
    if (!is_array($certs) || $certs === []) {
        throw new RuntimeException('Google signing certificates could not be loaded.');
    }

    @file_put_contents($cacheFile, json_encode([
        'expiresAt' => time() + SW_GOOGLE_CERT_CACHE_TTL,
        'certs' => $certs,
    ], JSON_UNESCAPED_SLASHES));

    return $certs;
}

function sw_verify_google_credential(string $credential): array
{
    if (!sw_google_enabled()) {
        sw_error('Google Sign-In is not configured.', 503);
    }

    $parts = explode('.', $credential);
    if (count($parts) !== 3) {
        sw_error('Invalid Google credential.', 401);
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

    try {
        $header = json_decode(sw_base64url_decode($encodedHeader), true);
        $payload = json_decode(sw_base64url_decode($encodedPayload), true);
        $signature = sw_base64url_decode($encodedSignature);
    } catch (RuntimeException $e) {
        sw_error($e->getMessage(), 401);
    }

    if (!is_array($header) || !is_array($payload)) {
        sw_error('Invalid Google credential.', 401);
    }

    $kid = (string) ($header['kid'] ?? '');
    if ((string) ($header['alg'] ?? '') !== 'RS256' || $kid === '') {
        sw_error('Invalid Google credential.', 401);
    }

    $certificates = sw_google_certificates();
    $certificate = $certificates[$kid] ?? null;
    if (!is_string($certificate) || $certificate === '') {
        sw_error('Google signing certificate not found.', 401);
    }

    $signedPayload = $encodedHeader . '.' . $encodedPayload;
    if (openssl_verify($signedPayload, $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
        sw_error('Google credential could not be verified.', 401);
    }

    $issuer = (string) ($payload['iss'] ?? '');
    if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
        sw_error('Unexpected Google token issuer.', 401);
    }

    if ((string) ($payload['aud'] ?? '') !== SW_GOOGLE_CLIENT_ID) {
        sw_error('Google token audience mismatch.', 401);
    }

    if ((int) ($payload['exp'] ?? 0) < (time() - 30)) {
        sw_error('Google credential expired.', 401);
    }

    $emailVerified = $payload['email_verified'] ?? false;
    if ($emailVerified !== true && $emailVerified !== 'true') {
        sw_error('Google account email is not verified.', 401);
    }

    $providerUid = trim((string) ($payload['sub'] ?? ''));
    $email = sw_normalize_email((string) ($payload['email'] ?? ''));
    if ($providerUid === '' || $email === '') {
        sw_error('Google account details are incomplete.', 401);
    }

    $name = trim((string) ($payload['name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($payload['given_name'] ?? ''));
    }
    if ($name === '') {
        $name = strstr($email, '@', true) ?: 'Google User';
    }

    $avatar = trim((string) ($payload['picture'] ?? ''));

    return [
        'provider' => 'google',
        'provider_uid' => $providerUid,
        'email' => $email,
        'name' => $name,
        'avatar' => $avatar !== '' ? $avatar : null,
    ];
}

function sw_sync_google_user(mysqli $db, array $googleUser): array
{
    $provider = 'google';
    $providerUid = (string) $googleUser['provider_uid'];
    $email = sw_normalize_email((string) $googleUser['email']);
    $name = trim((string) $googleUser['name']);
    $avatar = $googleUser['avatar'] ?? null;
    $phone = '';

    $userRow = sw_find_user_by_provider_uid($db, $provider, $providerUid);
    if ($userRow !== null) {
        $emailOwner = sw_find_user_by_email($db, $email);
        if ($emailOwner !== null && (int) $emailOwner['id'] !== (int) $userRow['id']) {
            sw_error('This Google email is already linked to another account.');
        }

        $nextName = trim((string) ($userRow['name'] ?? '')) !== '' ? (string) $userRow['name'] : $name;
        $nextAvatar = (($userRow['avatar'] ?? '') !== '' || $avatar === null) ? $userRow['avatar'] : $avatar;

        $stmt = $db->prepare(
            'UPDATE users
             SET email = ?, name = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $userId = (int) $userRow['id'];
        $stmt->bind_param('sssi', $email, $nextName, $nextAvatar, $userId);
        $stmt->execute();
        $stmt->close();

        $freshRow = sw_find_user_by_id($db, $userId);
        if ($freshRow === null) {
            throw new RuntimeException('Could not load Google account.');
        }

        return $freshRow;
    }

    $emailMatch = sw_find_user_by_email($db, $email);
    if ($emailMatch !== null) {
        if ((string) ($emailMatch['provider'] ?? 'email') !== 'google') {
            sw_error('An email/password account already exists with this email. Sign in with email/password instead.');
        }

        $nextName = trim((string) ($emailMatch['name'] ?? '')) !== '' ? (string) $emailMatch['name'] : $name;
        $nextAvatar = (($emailMatch['avatar'] ?? '') !== '' || $avatar === null) ? $emailMatch['avatar'] : $avatar;
        $stmt = $db->prepare(
            'UPDATE users
             SET provider_uid = ?, email = ?, name = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $userId = (int) $emailMatch['id'];
        $stmt->bind_param('ssssi', $providerUid, $email, $nextName, $nextAvatar, $userId);
        $stmt->execute();
        $stmt->close();

        $freshRow = sw_find_user_by_id($db, $userId);
        if ($freshRow === null) {
            throw new RuntimeException('Could not load Google account.');
        }

        return $freshRow;
    }

    $emptyPassword = '';
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, phone, avatar, provider, provider_uid)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssssss', $name, $email, $emptyPassword, $phone, $avatar, $provider, $providerUid);
    $stmt->execute();
    $userId = (int) $db->insert_id;
    $stmt->close();

    $freshRow = sw_find_user_by_id($db, $userId);
    if ($freshRow === null) {
        throw new RuntimeException('Could not create the Google account.');
    }

    return $freshRow;
}

function sw_get_bootstrap_payload(): array
{
    try {
        $sessionUser = sw_session_user();
        if ($sessionUser === null) {
            return ['user' => null, 'state' => null, 'google' => sw_google_bootstrap()];
        }

        $db = sw_db();
        $row = sw_find_user_by_id($db, (int) $sessionUser['id']);
        if ($row === null) {
            sw_clear_session();
            return ['user' => null, 'state' => null, 'google' => sw_google_bootstrap()];
        }

        $user = sw_user_payload($row);
        sw_store_session($user);

        return [
            'user' => $user,
            'state' => sw_load_state($db, (int) $row['id']),
            'google' => sw_google_bootstrap(),
        ];
    } catch (Throwable $e) {
        return [
            'user' => null,
            'state' => null,
            'google' => sw_google_bootstrap(),
            'bootError' => $e->getMessage(),
        ];
    }
}

function sw_verified_reset_map(): array
{
    $map = $_SESSION[SW_RESET_KEY] ?? [];
    return is_array($map) ? $map : [];
}

function sw_mark_reset_verified(string $email): void
{
    $map = sw_verified_reset_map();
    $map[$email] = time() + 600;
    $_SESSION[SW_RESET_KEY] = $map;
}

function sw_reset_is_verified(string $email): bool
{
    $map = sw_verified_reset_map();
    return isset($map[$email]) && (int) $map[$email] >= time();
}

function sw_forget_verified_reset(string $email): void
{
    $map = sw_verified_reset_map();
    unset($map[$email]);
    $_SESSION[SW_RESET_KEY] = $map;
}

function sw_handle_api_request(): never
{
    try {
        $action = trim((string) ($_GET['action'] ?? ''));
        if ($action === '') {
            sw_error('Missing action.');
        }

        $db = sw_db();
        $input = sw_read_input();

        switch ($action) {
            case 'login':
                $email = sw_normalize_email((string) ($input['email'] ?? ''));
                $password = (string) ($input['password'] ?? '');

                if ($email === '' || $password === '') {
                    sw_error('Please fill in all fields.');
                }

                $userRow = sw_find_user_by_email($db, $email);
                if ($userRow === null) {
                    sw_error('No account found with this email.', 404);
                }

                if (!sw_user_has_password($userRow)) {
                    if (($userRow['provider'] ?? 'email') === 'google') {
                        sw_error('This Google account does not have a password yet. Use Forgot Password to create one or continue with Google.');
                    }

                    sw_error('This account does not have a password yet.');
                }

                if (!password_verify($password, (string) $userRow['password_hash'])) {
                    sw_error('Incorrect password.');
                }

                $user = sw_user_payload($userRow);
                sw_store_session($user);

                sw_json_response(200, [
                    'ok' => true,
                    'data' => [
                        'user' => $user,
                        'state' => sw_load_state($db, (int) $userRow['id']),
                    ],
                ]);

            case 'google_login':
                $credential = trim((string) ($input['credential'] ?? ''));
                if ($credential === '') {
                    sw_error('Missing Google credential.');
                }

                $googleUser = sw_verify_google_credential($credential);
                $userRow = sw_sync_google_user($db, $googleUser);
                $user = sw_user_payload($userRow);
                sw_store_session($user);

                sw_json_response(200, [
                    'ok' => true,
                    'data' => [
                        'user' => $user,
                        'state' => sw_load_state($db, (int) $userRow['id']),
                    ],
                ]);

            case 'signup':
                $name = trim((string) ($input['name'] ?? ''));
                $email = sw_normalize_email((string) ($input['email'] ?? ''));
                $password = (string) ($input['password'] ?? '');

                if ($name === '') {
                    sw_error('Please enter your full name.');
                }
                if ($email === '') {
                    sw_error('Please enter your email.');
                }
                if (strlen($password) < 8) {
                    sw_error('Password must be at least 8 characters.');
                }
                if (sw_find_user_by_email($db, $email) !== null) {
                    sw_error('Account already exists with this email.');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $provider = 'email';
                $phone = '';
                $avatar = null;

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

                sw_json_response(200, [
                    'ok' => true,
                    'data' => [
                        'user' => $user,
                        'state' => sw_load_state($db, (int) $userRow['id']),
                    ],
                ]);

            case 'logout':
                sw_clear_session();
                sw_json_response(200, ['ok' => true, 'data' => ['loggedOut' => true]]);

            case 'send_otp':
                $email = sw_normalize_email((string) ($input['email'] ?? ''));
                if ($email === '') {
                    sw_error('Enter your email.');
                }

                $userRow = sw_find_user_by_email($db, $email);
                if ($userRow === null) {
                    sw_error('No account found.');
                }

                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = date('Y-m-d H:i:s', time() + SW_OTP_EXPIRES_IN);

                $stmt = $db->prepare(
                    'INSERT INTO password_reset_codes (user_id, email, code, expires_at)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE email = VALUES(email), code = VALUES(code), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP'
                );
                $userId = (int) $userRow['id'];
                $stmt->bind_param('isss', $userId, $email, $code, $expiresAt);
                $stmt->execute();
                $stmt->close();

                sw_forget_verified_reset($email);
                try {
                    sw_send_password_reset_email((string) ($userRow['name'] ?? ''), $email, $code, SW_OTP_EXPIRES_IN);
                } catch (Throwable $e) {
                    sw_delete_password_reset_code($db, $userId);
                    sw_error($e->getMessage(), 500);
                }

                sw_json_response(200, [
                    'ok' => true,
                    'data' => [
                        'expiresIn' => SW_OTP_EXPIRES_IN,
                        'maskedEmail' => sw_mask_email($email),
                        'message' => 'We sent a 6-digit code to ' . sw_mask_email($email) . '.',
                    ],
                ]);

            case 'verify_otp':
                $email = sw_normalize_email((string) ($input['email'] ?? ''));
                $code = trim((string) ($input['code'] ?? ''));

                if ($email === '' || $code === '') {
                    sw_error('Email and code are required.');
                }

                $userRow = sw_find_user_by_email($db, $email);
                if ($userRow === null) {
                    sw_error('No account found.');
                }

                $stmt = $db->prepare('SELECT code, expires_at FROM password_reset_codes WHERE user_id = ? LIMIT 1');
                $userId = (int) $userRow['id'];
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $otpRow = $result->fetch_assoc();
                $stmt->close();

                if ($otpRow === null) {
                    sw_error('No OTP found. Request a new one.');
                }
                if (strtotime((string) $otpRow['expires_at']) < time()) {
                    sw_forget_verified_reset($email);
                    sw_error('OTP expired. Request a new one.');
                }
                if ((string) $otpRow['code'] !== $code) {
                    sw_error('Incorrect code.');
                }

                sw_mark_reset_verified($email);
                sw_json_response(200, ['ok' => true, 'data' => ['verified' => true]]);

            case 'reset_password':
                $email = sw_normalize_email((string) ($input['email'] ?? ''));
                $password = (string) ($input['password'] ?? '');

                if ($email === '' || $password === '') {
                    sw_error('Email and password are required.');
                }
                if (strlen($password) < 8) {
                    sw_error('Minimum 8 characters.');
                }
                if (!sw_reset_is_verified($email)) {
                    sw_error('Verify your OTP before resetting the password.');
                }

                $userRow = sw_find_user_by_email($db, $email);
                if ($userRow === null) {
                    sw_error('No account found.');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $userId = (int) $userRow['id'];
                $stmt->bind_param('si', $hash, $userId);
                $stmt->execute();
                $stmt->close();

                sw_delete_password_reset_code($db, $userId);

                sw_forget_verified_reset($email);
                sw_json_response(200, ['ok' => true, 'data' => ['updated' => true]]);

            case 'update_profile':
                $sessionUser = sw_require_session_user();
                $userRow = sw_find_user_by_id($db, (int) $sessionUser['id']);
                if ($userRow === null) {
                    sw_error('User not found.', 404);
                }

                $name = array_key_exists('name', $input) ? trim((string) $input['name']) : (string) $userRow['name'];
                $phone = array_key_exists('phone', $input) ? trim((string) $input['phone']) : (string) ($userRow['phone'] ?? '');
                $avatar = array_key_exists('avatar', $input) ? $input['avatar'] : $userRow['avatar'];
                $avatar = $avatar === null || $avatar === '' ? null : (string) $avatar;

                if ($name === '') {
                    sw_error('Name cannot be empty.');
                }

                $stmt = $db->prepare(
                    'UPDATE users SET name = ?, phone = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
                );
                $userId = (int) $userRow['id'];
                $stmt->bind_param('sssi', $name, $phone, $avatar, $userId);
                $stmt->execute();
                $stmt->close();

                $freshRow = sw_find_user_by_id($db, $userId);
                if ($freshRow === null) {
                    sw_error('User not found.', 404);
                }

                $user = sw_user_payload($freshRow);
                sw_store_session($user);

                sw_json_response(200, ['ok' => true, 'data' => ['user' => $user]]);

            case 'save_state':
                $sessionUser = sw_require_session_user();
                $state = $input['state'] ?? null;
                if (!is_array($state)) {
                    sw_error('Invalid state payload.');
                }

                sw_save_state($db, (int) $sessionUser['id'], $state);
                sw_json_response(200, ['ok' => true, 'data' => ['saved' => true]]);

            case 'clear_state':
                $sessionUser = sw_require_session_user();
                sw_clear_state($db, (int) $sessionUser['id']);
                sw_json_response(200, ['ok' => true, 'data' => ['state' => null]]);

            default:
                sw_error('Unknown action.', 404);
        }
    } catch (Throwable $e) {
        sw_json_response(500, ['ok' => false, 'error' => $e->getMessage()]);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    sw_handle_api_request();
}
