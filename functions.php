<?php
declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Load config — local dev takes priority, then production, then fail loudly.
// Neither file is committed to git (both are in .gitignore).
// On your server, upload config.production.php manually via FTP.
if (file_exists(__DIR__ . '/config.php')) {
    $_cfg = require __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/config.production.php')) {
    $_cfg = require __DIR__ . '/config.production.php';
} else {
    die('Missing config file. Copy config.example.php to config.php and fill in your values.');
}

define('SW_DB_HOST',  (string) ($_cfg['db']['host'] ?? ''));
define('SW_DB_USER',  (string) ($_cfg['db']['user'] ?? ''));
define('SW_DB_PASS',  (string) ($_cfg['db']['pass'] ?? ''));
define('SW_DB_NAME',  (string) ($_cfg['db']['name'] ?? ''));
define('SW_GOOGLE_CLIENT_ID', (string) ($_cfg['google_client_id'] ?? ''));
define('SW_GROQ_API_KEY', (string) ($_cfg['groq_api_key'] ?? getenv('SW_GROQ_API_KEY') ?: ''));
define('SW_OCRSPACE_API_KEY', (string) ($_cfg['ocrspace_api_key'] ?? getenv('SW_OCRSPACE_API_KEY') ?: ''));

// SMTP — read from config, fall back to safe empty defaults
define('SW_SMTP_HOST',       (string) ($_cfg['smtp']['host']       ?? 'smtp.gmail.com'));
define('SW_SMTP_PORT',       (int)    ($_cfg['smtp']['port']       ?? 587));
define('SW_SMTP_SECURE',     (string) ($_cfg['smtp']['secure']     ?? 'tls'));
define('SW_SMTP_USERNAME',   (string) ($_cfg['smtp']['username']   ?? ''));
define('SW_SMTP_PASSWORD',   (string) ($_cfg['smtp']['password']   ?? ''));
define('SW_SMTP_FROM_EMAIL', (string) ($_cfg['smtp']['from_email'] ?? ''));
define('SW_SMTP_FROM_NAME',  (string) ($_cfg['smtp']['from_name']  ?? 'SpendWise'));
unset($_cfg);

const SW_SESSION_KEY           = 'spendwise_user';
const SW_RESET_KEY             = 'spendwise_verified_resets';
const SW_OTP_EXPIRES_IN        = 300;
const SW_SMTP_TIMEOUT          = 20;
const SW_GOOGLE_CERTS_URL      = 'https://www.googleapis.com/oauth2/v1/certs';
const SW_GOOGLE_CERT_CACHE_TTL = 3600;

if (session_status() !== PHP_SESSION_ACTIVE) {
    // [SEC F-11] Harden session cookie before starting the session.
    // HttpOnly: blocks JS from reading the cookie (mitigates XSS-based session theft).
    // Secure:   only transmit cookie over HTTPS.
    // SameSite: prevents the cookie from being sent on cross-site requests (mitigates CSRF).
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_lifetime', '86400'); // 24-hour session lifetime
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
    // ── Core auth tables ──────────────────────────────────────────────────────

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

    // ── Phase 1: Relational tables ────────────────────────────────────────────
    // See database.sql for the canonical DDL with full comments.

    $db->query(
        'CREATE TABLE IF NOT EXISTS user_migration_log (
            user_id         INT UNSIGNED NOT NULL,
            migrated        TINYINT(1)   NOT NULL DEFAULT 0,
            migrated_at     TIMESTAMP    NULL,
            expense_count   INT UNSIGNED NOT NULL DEFAULT 0,
            budget_count    INT UNSIGNED NOT NULL DEFAULT 0,
            recurring_count INT UNSIGNED NOT NULL DEFAULT 0,
            bill_count      INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id),
            CONSTRAINT fk_migration_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS expenses (
            id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            user_id     INT UNSIGNED  NOT NULL,
            external_id VARCHAR(64)   NOT NULL,
            amount      DECIMAL(15,2) NOT NULL,
            category    VARCHAR(100)  NOT NULL DEFAULT "",
            date        DATE          NOT NULL,
            note        TEXT          NULL,
            receipt     LONGTEXT      NULL,
            created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_expense_user_ext   (user_id, external_id),
            KEY        idx_expense_user_date   (user_id, date),
            KEY        idx_expense_user_cat    (user_id, category),
            CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS budgets (
            id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            user_id      INT UNSIGNED  NOT NULL,
            external_id  VARCHAR(64)   NOT NULL,
            category     VARCHAR(100)  NOT NULL,
            month        VARCHAR(7)    NOT NULL,
            limit_amount DECIMAL(15,2) NOT NULL,
            created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_budget_user_ext    (user_id, external_id),
            UNIQUE KEY uniq_budget_cat_month   (user_id, category, month),
            KEY        idx_budget_user_month   (user_id, month),
            CONSTRAINT fk_budgets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS recurring_items (
            id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            user_id     INT UNSIGNED  NOT NULL,
            external_id VARCHAR(64)   NOT NULL,
            name        VARCHAR(255)  NOT NULL,
            amount      DECIMAL(15,2) NOT NULL,
            category    VARCHAR(100)  NOT NULL DEFAULT "",
            frequency   VARCHAR(20)   NOT NULL DEFAULT "monthly",
            start_date  DATE          NOT NULL,
            end_date    DATE          NULL,
            next_due    DATE          NOT NULL,
            active      TINYINT(1)    NOT NULL DEFAULT 1,
            created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_recurring_user_ext      (user_id, external_id),
            KEY        idx_recurring_user_active     (user_id, active),
            KEY        idx_recurring_next_due        (user_id, next_due),
            CONSTRAINT fk_recurring_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS bills (
            id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            user_id     INT UNSIGNED  NOT NULL,
            external_id VARCHAR(64)   NOT NULL,
            name        VARCHAR(255)  NOT NULL,
            amount      DECIMAL(15,2) NOT NULL,
            category    VARCHAR(100)  NOT NULL DEFAULT "",
            due_date    DATE          NOT NULL,
            status      VARCHAR(20)   NOT NULL DEFAULT "upcoming",
            paid_date   DATE          NULL,
            reference   VARCHAR(255)  NULL,
            created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bill_user_ext      (user_id, external_id),
            KEY        idx_bill_user_status    (user_id, status),
            KEY        idx_bill_due_date       (user_id, due_date),
            CONSTRAINT fk_bills_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->query(
        'CREATE TABLE IF NOT EXISTS user_preferences (
            user_id             INT UNSIGNED NOT NULL,
            dark_mode           TINYINT(1)   NOT NULL DEFAULT 0,
            notifications_email TINYINT(1)   NOT NULL DEFAULT 1,
            language            VARCHAR(5)   NOT NULL DEFAULT "en",
            categories          TEXT         NOT NULL DEFAULT "[]",
            notif_seen_at       DATETIME     NULL,
            extra_json          MEDIUMTEXT   NULL,
            created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            CONSTRAINT fk_user_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

// =============================================================================
// Phase 1 — Relational state storage
// =============================================================================
// These functions replace the single-blob approach.  sw_load_state() and
// sw_save_state() keep the same signatures so no call-sites need changing yet.
//
// Migration strategy:
//   • On first sw_load_state() for a user we check user_migration_log.
//   • If the user hasn't been migrated we read their old JSON blob, decompose
//     it into the five relational tables, and mark them migrated.
//   • After that, all reads come from the relational tables.
//   • sw_save_state() writes to relational tables AND keeps the old blob in
//     sync (belt-and-suspenders for Phase 1; the blob writes will be removed
//     in Phase 2 once the frontend calls per-resource endpoints directly).
// =============================================================================

// ── Migration status ──────────────────────────────────────────────────────────

function sw_is_migrated(mysqli $db, int $userId): bool
{
    $stmt = $db->prepare(
        'SELECT migrated FROM user_migration_log WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row !== null && (int) $row['migrated'] === 1;
}

/**
 * Decompose a user's old state_json blob into the five relational tables.
 * Safe to call multiple times — uses INSERT IGNORE so duplicate external_ids
 * are silently skipped rather than causing errors.
 */
function sw_migrate_user_state(mysqli $db, int $userId): void
{
    // Load raw blob (may be null for brand-new users who never saved state).
    $stmt = $db->prepare(
        'SELECT state_json FROM user_states WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $blobRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $state = [];
    if ($blobRow !== null) {
        $decoded = json_decode((string) $blobRow['state_json'], true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }

    $db->begin_transaction();

    try {
        // -- expenses --
        $expCount = 0;
        foreach ((array) ($state['expenses'] ?? []) as $e) {
            $extId    = sw_coerce_ext_id($e['id'] ?? '');
            $amount   = (float) ($e['amount'] ?? 0);
            $category = (string) ($e['category'] ?? '');
            $date     = sw_coerce_date($e['date'] ?? '');
            $note     = isset($e['note']) ? (string) $e['note'] : null;
            $receipt  = isset($e['receipt']) && $e['receipt'] !== '' && $e['receipt'] !== null
                            ? (string) $e['receipt'] : null;

            if ($extId === '' || $date === '') {
                continue; // skip malformed rows
            }

            $stmt = $db->prepare(
                'INSERT IGNORE INTO expenses
                 (user_id, external_id, amount, category, date, note, receipt)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isdssss', $userId, $extId, $amount, $category, $date, $note, $receipt);
            $stmt->execute();
            $expCount += $stmt->affected_rows;
            $stmt->close();
        }

        // -- budgets --
        $budgCount = 0;
        foreach ((array) ($state['budgets'] ?? []) as $b) {
            $extId  = sw_coerce_ext_id($b['id'] ?? '');
            $cat    = (string) ($b['category'] ?? '');
            $month  = sw_coerce_month($b['month'] ?? '');
            $limit  = (float) ($b['limit'] ?? 0);

            if ($extId === '' || $month === '') {
                continue;
            }

            $stmt = $db->prepare(
                'INSERT IGNORE INTO budgets
                 (user_id, external_id, category, month, limit_amount)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssd', $userId, $extId, $cat, $month, $limit);
            $stmt->execute();
            $budgCount += $stmt->affected_rows;
            $stmt->close();
        }

        // -- recurring_items --
        $recCount = 0;
        foreach ((array) ($state['recurring'] ?? []) as $r) {
            $extId     = sw_coerce_ext_id($r['id'] ?? '');
            $name      = (string) ($r['name'] ?? '');
            $amount    = (float) ($r['amount'] ?? 0);
            $cat       = (string) ($r['category'] ?? '');
            $freq      = (string) ($r['frequency'] ?? 'monthly');
            $startDate = sw_coerce_date($r['startDate'] ?? '');
            $endDate   = sw_coerce_date_nullable($r['endDate'] ?? null);
            $nextDue   = sw_coerce_date($r['nextDue'] ?? $startDate);
            $active    = isset($r['active']) ? (int) (bool) $r['active'] : 1;

            if ($extId === '' || $startDate === '' || $nextDue === '') {
                continue;
            }

            $stmt = $db->prepare(
                'INSERT IGNORE INTO recurring_items
                 (user_id, external_id, name, amount, category, frequency,
                  start_date, end_date, next_due, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issdsssssi',
                $userId, $extId, $name, $amount, $cat, $freq,
                $startDate, $endDate, $nextDue, $active
            );
            $stmt->execute();
            $recCount += $stmt->affected_rows;
            $stmt->close();
        }

        // -- bills --
        $billCount = 0;
        foreach ((array) ($state['bills'] ?? []) as $b) {
            $extId     = sw_coerce_ext_id($b['id'] ?? '');
            $name      = (string) ($b['name'] ?? '');
            $amount    = (float) ($b['amount'] ?? 0);
            $cat       = (string) ($b['category'] ?? '');
            $dueDate   = sw_coerce_date($b['dueDate'] ?? '');
            $status    = (string) ($b['status'] ?? 'upcoming');
            $paidDate  = sw_coerce_date_nullable($b['paidDate'] ?? null);
            $reference = isset($b['reference']) && $b['reference'] !== '' && $b['reference'] !== null
                            ? (string) $b['reference'] : null;

            if ($extId === '' || $dueDate === '') {
                continue;
            }

            $stmt = $db->prepare(
                'INSERT IGNORE INTO bills
                 (user_id, external_id, name, amount, category,
                  due_date, status, paid_date, reference)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issdsssss',
                $userId, $extId, $name, $amount, $cat,
                $dueDate, $status, $paidDate, $reference
            );
            $stmt->execute();
            $billCount += $stmt->affected_rows;
            $stmt->close();
        }

        // -- user_preferences --
        $darkMode  = isset($state['darkMode']) ? (int) (bool) $state['darkMode'] : 0;
        $notifEmail = (int) (($state['notifications']['email'] ?? true) !== false);
        $language   = in_array($state['language'] ?? '', ['am', 'en'], true)
                        ? (string) $state['language'] : 'en';

        $categories = $state['categories'] ?? [];
        $catJson    = json_encode(
            is_array($categories) ? $categories : [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $notifSeenAt = sw_coerce_datetime_nullable($state['notifSeenAt'] ?? null);

        // Collect any unrecognised keys into extra_json for safe-keeping.
        $knownKeys = [
            'expenses', 'budgets', 'recurring', 'bills',
            'darkMode', 'notifications', 'language', 'categories', 'notifSeenAt',
            'user',
        ];
        $extra = [];
        foreach ($state as $k => $v) {
            if (!in_array($k, $knownKeys, true)) {
                $extra[$k] = $v;
            }
        }
        $extraJson = $extra !== []
            ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $stmt = $db->prepare(
            'INSERT INTO user_preferences
             (user_id, dark_mode, notifications_email, language,
              categories, notif_seen_at, extra_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               dark_mode           = VALUES(dark_mode),
               notifications_email = VALUES(notifications_email),
               language            = VALUES(language),
               categories          = VALUES(categories),
               notif_seen_at       = VALUES(notif_seen_at),
               extra_json          = VALUES(extra_json),
               updated_at          = CURRENT_TIMESTAMP'
        );
        $stmt->bind_param(
            'iiissss',
            $userId, $darkMode, $notifEmail, $language,
            $catJson, $notifSeenAt, $extraJson
        );
        $stmt->execute();
        $stmt->close();

        // Mark migrated.
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare(
            'INSERT INTO user_migration_log
             (user_id, migrated, migrated_at, expense_count,
              budget_count, recurring_count, bill_count)
             VALUES (?, 1, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               migrated        = 1,
               migrated_at     = VALUES(migrated_at),
               expense_count   = VALUES(expense_count),
               budget_count    = VALUES(budget_count),
               recurring_count = VALUES(recurring_count),
               bill_count      = VALUES(bill_count)'
        );
        $stmt->bind_param(
            'isiiii',
            $userId, $now, $expCount, $budgCount, $recCount, $billCount
        );
        $stmt->execute();
        $stmt->close();

        $db->commit();

    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

// ── Coercion helpers ──────────────────────────────────────────────────────────

/** Ensures external_id is a non-empty string under 64 chars. */
function sw_coerce_ext_id(mixed $v): string
{
    $s = trim((string) $v);
    return strlen($s) > 0 && strlen($s) <= 64 ? $s : '';
}

/** Returns a DATE string (YYYY-MM-DD) or '' if the value is not a valid date. */
function sw_coerce_date(mixed $v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $s = trim((string) $v);
    // Accept YYYY-MM-DD only.
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }
    return '';
}

/** Same as sw_coerce_date but returns null instead of ''. */
function sw_coerce_date_nullable(mixed $v): ?string
{
    $d = sw_coerce_date($v);
    return $d !== '' ? $d : null;
}

/** Returns a YYYY-MM string or '' for budget month fields. */
function sw_coerce_month(mixed $v): string
{
    $s = trim((string) $v);
    if (preg_match('/^\d{4}-\d{2}$/', $s)) {
        return $s;
    }
    return '';
}

/** Returns a MySQL DATETIME string or null if the input is not parseable. */
function sw_coerce_datetime_nullable(mixed $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    $ts = strtotime((string) $v);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

// ── Load from relational tables ───────────────────────────────────────────────

/**
 * Reads all five relational tables for $userId and assembles the state array
 * that the frontend expects — same shape as the old JSON blob.
 */
function sw_load_state_from_tables(mysqli $db, int $userId): array
{
    // expenses — newest first (matches original frontend sort)
    $expenses = [];
    $stmt = $db->prepare(
        'SELECT external_id AS id, amount, category, date, note, receipt
         FROM expenses
         WHERE user_id = ?
         ORDER BY date DESC, id DESC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $expenses[] = [
            'id'       => $row['id'],
            'amount'   => (float) $row['amount'],
            'category' => $row['category'],
            'date'     => $row['date'],
            'note'     => $row['note'],
            'receipt'  => $row['receipt'],
        ];
    }
    $stmt->close();

    // budgets
    $budgets = [];
    $stmt = $db->prepare(
        'SELECT external_id AS id, category, month, limit_amount AS `limit`
         FROM budgets
         WHERE user_id = ?
         ORDER BY month DESC, id ASC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $budgets[] = [
            'id'       => $row['id'],
            'category' => $row['category'],
            'month'    => $row['month'],
            'limit'    => (float) $row['limit'],
        ];
    }
    $stmt->close();

    // recurring_items
    $recurring = [];
    $stmt = $db->prepare(
        'SELECT external_id AS id, name, amount, category, frequency,
                start_date AS startDate, end_date AS endDate,
                next_due AS nextDue, active
         FROM recurring_items
         WHERE user_id = ?
         ORDER BY id ASC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $recurring[] = [
            'id'        => $row['id'],
            'name'      => $row['name'],
            'amount'    => (float) $row['amount'],
            'category'  => $row['category'],
            'frequency' => $row['frequency'],
            'startDate' => $row['startDate'],
            'endDate'   => $row['endDate'],
            'nextDue'   => $row['nextDue'],
            'active'    => (bool) $row['active'],
        ];
    }
    $stmt->close();

    // bills
    $bills = [];
    $stmt = $db->prepare(
        'SELECT external_id AS id, name, amount, category,
                due_date AS dueDate, status,
                paid_date AS paidDate, reference
         FROM bills
         WHERE user_id = ?
         ORDER BY due_date ASC, id ASC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $bills[] = [
            'id'        => $row['id'],
            'name'      => $row['name'],
            'amount'    => (float) $row['amount'],
            'category'  => $row['category'],
            'dueDate'   => $row['dueDate'],
            'status'    => $row['status'],
            'paidDate'  => $row['paidDate'],
            'reference' => $row['reference'],
        ];
    }
    $stmt->close();

    // user_preferences
    $stmt = $db->prepare(
        'SELECT dark_mode, notifications_email, language,
                categories, notif_seen_at, extra_json
         FROM user_preferences
         WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $prefs = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $darkMode     = $prefs !== null ? (bool) $prefs['dark_mode']           : false;
    $notifEmail   = $prefs !== null ? (bool) $prefs['notifications_email'] : true;
    $language     = $prefs !== null ? (string) $prefs['language']           : 'en';
    $categories   = [];
    if ($prefs !== null && $prefs['categories'] !== null) {
        $decoded = json_decode((string) $prefs['categories'], true);
        if (is_array($decoded)) {
            $categories = $decoded;
        }
    }
    $notifSeenAt = $prefs !== null ? ($prefs['notif_seen_at'] ?? null) : null;

    // Restore any extra keys (e.g. __notifications_meta).
    $extra = [];
    if ($prefs !== null && $prefs['extra_json'] !== null) {
        $decoded = json_decode((string) $prefs['extra_json'], true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }

    return array_merge($extra, [
        'expenses'      => $expenses,
        'budgets'       => $budgets,
        'recurring'     => $recurring,
        'bills'         => $bills,
        'darkMode'      => $darkMode,
        'notifications' => ['email' => $notifEmail],
        'language'      => $language,
        'categories'    => $categories,
        'notifSeenAt'   => $notifSeenAt,
    ]);
}

// ── Save to relational tables ─────────────────────────────────────────────────

/**
 * Full-sync save: replaces all entity rows for $userId with whatever is in
 * $state, then upserts preferences.
 *
 * Strategy: collect external_ids from the incoming state, delete any rows
 * whose external_id is NOT in that set (i.e. the frontend deleted them),
 * then INSERT ... ON DUPLICATE KEY UPDATE for the rest.
 */
function sw_save_state_to_tables(mysqli $db, int $userId, array $state): void
{
    $db->begin_transaction();

    try {
        // ── expenses ──────────────────────────────────────────────────────────
        $incomingExpIds = [];
        foreach ((array) ($state['expenses'] ?? []) as $e) {
            $extId    = sw_coerce_ext_id($e['id'] ?? '');
            $amount   = (float) ($e['amount'] ?? 0);
            $category = (string) ($e['category'] ?? '');
            $date     = sw_coerce_date($e['date'] ?? '');
            $note     = isset($e['note']) && $e['note'] !== null ? (string) $e['note'] : null;
            $receipt  = isset($e['receipt']) && $e['receipt'] !== '' && $e['receipt'] !== null
                            ? (string) $e['receipt'] : null;

            if ($extId === '' || $date === '') {
                continue;
            }

            $incomingExpIds[] = $extId;

            $stmt = $db->prepare(
                'INSERT INTO expenses
                 (user_id, external_id, amount, category, date, note, receipt)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   amount    = VALUES(amount),
                   category  = VALUES(category),
                   date      = VALUES(date),
                   note      = VALUES(note),
                   receipt   = VALUES(receipt),
                   updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->bind_param('isdssss', $userId, $extId, $amount, $category, $date, $note, $receipt);
            $stmt->execute();
            $stmt->close();
        }
        sw_delete_stale_rows($db, $userId, 'expenses', $incomingExpIds);

        // ── budgets ───────────────────────────────────────────────────────────
        $incomingBudgIds = [];
        foreach ((array) ($state['budgets'] ?? []) as $b) {
            $extId = sw_coerce_ext_id($b['id'] ?? '');
            $cat   = (string) ($b['category'] ?? '');
            $month = sw_coerce_month($b['month'] ?? '');
            $limit = (float) ($b['limit'] ?? 0);

            if ($extId === '' || $month === '') {
                continue;
            }

            $incomingBudgIds[] = $extId;

            $stmt = $db->prepare(
                'INSERT INTO budgets
                 (user_id, external_id, category, month, limit_amount)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   category     = VALUES(category),
                   month        = VALUES(month),
                   limit_amount = VALUES(limit_amount),
                   updated_at   = CURRENT_TIMESTAMP'
            );
            $stmt->bind_param('isssd', $userId, $extId, $cat, $month, $limit);
            $stmt->execute();
            $stmt->close();
        }
        sw_delete_stale_rows($db, $userId, 'budgets', $incomingBudgIds);

        // ── recurring_items ───────────────────────────────────────────────────
        $incomingRecIds = [];
        foreach ((array) ($state['recurring'] ?? []) as $r) {
            $extId     = sw_coerce_ext_id($r['id'] ?? '');
            $name      = (string) ($r['name'] ?? '');
            $amount    = (float) ($r['amount'] ?? 0);
            $cat       = (string) ($r['category'] ?? '');
            $freq      = (string) ($r['frequency'] ?? 'monthly');
            $startDate = sw_coerce_date($r['startDate'] ?? '');
            $endDate   = sw_coerce_date_nullable($r['endDate'] ?? null);
            $nextDue   = sw_coerce_date($r['nextDue'] ?? $startDate);
            $active    = (int) (bool) ($r['active'] ?? true);

            if ($extId === '' || $startDate === '' || $nextDue === '') {
                continue;
            }

            $incomingRecIds[] = $extId;

            $stmt = $db->prepare(
                'INSERT INTO recurring_items
                 (user_id, external_id, name, amount, category, frequency,
                  start_date, end_date, next_due, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   name       = VALUES(name),
                   amount     = VALUES(amount),
                   category   = VALUES(category),
                   frequency  = VALUES(frequency),
                   start_date = VALUES(start_date),
                   end_date   = VALUES(end_date),
                   next_due   = VALUES(next_due),
                   active     = VALUES(active),
                   updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->bind_param(
                'issdsssssi',
                $userId, $extId, $name, $amount, $cat, $freq,
                $startDate, $endDate, $nextDue, $active
            );
            $stmt->execute();
            $stmt->close();
        }
        sw_delete_stale_rows($db, $userId, 'recurring_items', $incomingRecIds);

        // ── bills ─────────────────────────────────────────────────────────────
        $incomingBillIds = [];
        foreach ((array) ($state['bills'] ?? []) as $b) {
            $extId     = sw_coerce_ext_id($b['id'] ?? '');
            $name      = (string) ($b['name'] ?? '');
            $amount    = (float) ($b['amount'] ?? 0);
            $cat       = (string) ($b['category'] ?? '');
            $dueDate   = sw_coerce_date($b['dueDate'] ?? '');
            $status    = (string) ($b['status'] ?? 'upcoming');
            $paidDate  = sw_coerce_date_nullable($b['paidDate'] ?? null);
            $reference = isset($b['reference']) && $b['reference'] !== null && $b['reference'] !== ''
                            ? (string) $b['reference'] : null;

            if ($extId === '' || $dueDate === '') {
                continue;
            }

            $incomingBillIds[] = $extId;

            $stmt = $db->prepare(
                'INSERT INTO bills
                 (user_id, external_id, name, amount, category,
                  due_date, status, paid_date, reference)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   name       = VALUES(name),
                   amount     = VALUES(amount),
                   category   = VALUES(category),
                   due_date   = VALUES(due_date),
                   status     = VALUES(status),
                   paid_date  = VALUES(paid_date),
                   reference  = VALUES(reference),
                   updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->bind_param(
                'issdsssss',
                $userId, $extId, $name, $amount, $cat,
                $dueDate, $status, $paidDate, $reference
            );
            $stmt->execute();
            $stmt->close();
        }
        sw_delete_stale_rows($db, $userId, 'bills', $incomingBillIds);

        // ── user_preferences ──────────────────────────────────────────────────
        $darkMode   = (int) (bool) ($state['darkMode'] ?? false);
        $notifEmail = (int) (($state['notifications']['email'] ?? true) !== false);
        $language   = in_array($state['language'] ?? '', ['am', 'en'], true)
                        ? (string) $state['language'] : 'en';
        $categories = $state['categories'] ?? [];
        $catJson    = json_encode(
            is_array($categories) ? $categories : [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $notifSeenAt = sw_coerce_datetime_nullable($state['notifSeenAt'] ?? null);

        $knownKeys = [
            'expenses', 'budgets', 'recurring', 'bills',
            'darkMode', 'notifications', 'language', 'categories', 'notifSeenAt',
            'user',
        ];
        $extra = [];
        foreach ($state as $k => $v) {
            if (!in_array($k, $knownKeys, true)) {
                $extra[$k] = $v;
            }
        }
        $extraJson = $extra !== []
            ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $stmt = $db->prepare(
            'INSERT INTO user_preferences
             (user_id, dark_mode, notifications_email, language,
              categories, notif_seen_at, extra_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               dark_mode           = VALUES(dark_mode),
               notifications_email = VALUES(notifications_email),
               language            = VALUES(language),
               categories          = VALUES(categories),
               notif_seen_at       = VALUES(notif_seen_at),
               extra_json          = VALUES(extra_json),
               updated_at          = CURRENT_TIMESTAMP'
        );
        $stmt->bind_param(
            'iiissss',
            $userId, $darkMode, $notifEmail, $language,
            $catJson, $notifSeenAt, $extraJson
        );
        $stmt->execute();
        $stmt->close();

        $db->commit();

    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Deletes rows from $table where user_id = $userId and external_id is NOT in
 * $keepIds.  Called after each entity sync to remove deleted items.
 *
 * @param string[] $keepIds
 */
function sw_delete_stale_rows(
    mysqli $db,
    int    $userId,
    string $table,
    array  $keepIds
): void {
    // Whitelist table names — never interpolate user input here.
    $allowed = ['expenses', 'budgets', 'recurring_items', 'bills'];
    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException("sw_delete_stale_rows: unknown table '{$table}'.");
    }

    if ($keepIds === []) {
        // All items deleted — wipe the table for this user.
        $stmt = $db->prepare("DELETE FROM `{$table}` WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        return;
    }

    // Build a parameterised IN list.
    $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
    $types        = 'i' . str_repeat('s', count($keepIds));
    $params       = array_merge([$userId], $keepIds);

    $stmt = $db->prepare(
        "DELETE FROM `{$table}`
         WHERE user_id = ? AND external_id NOT IN ({$placeholders})"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}

// ── Public API (same signatures as before) ───────────────────────────────────

/**
 * Load state for $userId.
 *
 * On first call after deploy, auto-migrates the user's JSON blob into the
 * relational tables.  Subsequent calls read entirely from those tables.
 */
function sw_load_state(mysqli $db, int $userId): ?array
{
    if (!sw_is_migrated($db, $userId)) {
        // First time this user is seen post-deploy: run migration.
        sw_migrate_user_state($db, $userId);
    }

    // Read from relational tables.  If the user has no data at all, return
    // an empty-but-valid state (same as the old "null → use default" path).
    $state = sw_load_state_from_tables($db, $userId);

    // Return null only when there truly is no record at all (new user who
    // never saved).  An empty state is still a valid state.
    return $state;
}

/**
 * Save state for $userId.
 *
 * Writes to both:
 *   1. The five relational tables (primary, Phase 1 target).
 *   2. The old user_states blob (belt-and-suspenders backup; removed Phase 2).
 */
function sw_save_state(mysqli $db, int $userId, array $state): void
{
    // Ensure the user has a migration record before saving (handles new users
    // who sign up after Phase 1 is deployed — they skip the JSON blob path
    // entirely and go straight into the relational tables).
    if (!sw_is_migrated($db, $userId)) {
        sw_migrate_user_state($db, $userId);
    }

    // Phase 3: write only to relational tables. Blob backup removed.
    sw_save_state_to_tables($db, $userId, $state);
}

/**
 * Wipe all data for $userId — called by the "Danger Zone" reset.
 *
 * Clears all five relational tables, the old blob, and the migration log
 * so the next save starts from a clean slate.
 */
function sw_clear_state(mysqli $db, int $userId): void
{
    $tables = ['expenses', 'budgets', 'recurring_items', 'bills', 'user_preferences'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("DELETE FROM `{$table}` WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Remove old blob.
    $stmt = $db->prepare('DELETE FROM user_states WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    // Reset migration log so the next sw_save_state() re-initialises cleanly.
    $stmt = $db->prepare('DELETE FROM user_migration_log WHERE user_id = ?');
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

// [SEC F-08] Notify the user whenever their password is successfully changed.
function sw_send_password_changed_email(string $recipientName, string $recipientEmail): void
{
    $displayName = trim($recipientName) !== '' ? trim($recipientName) : 'there';
    $subject     = 'Your SpendWise password was changed';
    $htmlBody    =
        '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#0f172a">' .
        '<p>Hello ' . htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>' .
        '<p>This is a confirmation that your SpendWise password was just changed.</p>' .
        '<p>If you made this change, no further action is needed.</p>' .
        '<p><strong>If you did not change your password</strong>, please contact support immediately and consider changing your email account password as well.</p>' .
        '</div>';
    $textBody =
        "Hello {$displayName},\n\n" .
        "This is a confirmation that your SpendWise password was just changed.\n\n" .
        "If you made this change, no further action is needed.\n\n" .
        "If you did NOT change your password, please contact support immediately.";

    sw_send_notification_email($recipientName, $recipientEmail, $subject, $htmlBody, $textBody);
}

function sw_send_notification_email(string $recipientName, string $recipientEmail, string $subject, string $htmlBody, ?string $textBody = null): void
{
    $settings = sw_mailer_settings();
    $caBundle = sw_ca_bundle_path();

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
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $mail->send();
    } catch (MailerException $e) {
        throw new RuntimeException('Could not send notification email. Check your SMTP credentials and mail access.');
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
    // [SEC F-12] Store in the app directory, not world-readable /tmp.
    // Falls back to /tmp only if SW_ROOT_DIR is not defined.
    $dir = defined('SW_ROOT_DIR') ? rtrim(SW_ROOT_DIR, DIRECTORY_SEPARATOR) : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    return $dir . DIRECTORY_SEPARATOR . '.spendwise_google_certs_' . md5(defined('SW_GOOGLE_CLIENT_ID') ? SW_GOOGLE_CLIENT_ID : 'default') . '.json';
}

function sw_google_cert_hmac(array $data): string
{
    // [SEC F-12] HMAC over cache contents to detect tampering.
    $secret = defined('SW_GOOGLE_CLIENT_ID') ? SW_GOOGLE_CLIENT_ID : 'sw-cert-hmac-key';
    return hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_SLASHES) ?: '', $secret);
}

function sw_google_certificates(): array
{
    $cacheFile = sw_google_cert_cache_file();
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (
            is_array($cached)
            && isset($cached['expiresAt'], $cached['certs'], $cached['hmac'])
            && (int) $cached['expiresAt'] > time()
            && is_array($cached['certs'])
        ) {
            // [SEC F-12] Verify HMAC to detect cache file tampering.
            $payload = ['expiresAt' => $cached['expiresAt'], 'certs' => $cached['certs']];
            if (hash_equals(sw_google_cert_hmac($payload), (string) $cached['hmac'])) {
                return $cached['certs'];
            }
            // HMAC mismatch — cache tampered or stale; re-fetch.
            error_log('[SpendWise] Google cert cache HMAC mismatch — re-fetching.');
        }
    }

    $body = sw_http_get(SW_GOOGLE_CERTS_URL);
    $certs = json_decode($body, true);
    if (!is_array($certs) || $certs === []) {
        throw new RuntimeException('Google signing certificates could not be loaded.');
    }

    $payload = ['expiresAt' => time() + SW_GOOGLE_CERT_CACHE_TTL, 'certs' => $certs];
    @file_put_contents($cacheFile, json_encode(
        array_merge($payload, ['hmac' => sw_google_cert_hmac($payload)]),
        JSON_UNESCAPED_SLASHES
    ), LOCK_EX);

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

    if ((int) ($payload['exp'] ?? 0) < (time() - 5)) { // [SEC F-14] 5s skew per Google's recommendation (was 30s)
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

/**
 * Boot payload returned inline in the HTML page.
 *
 * Phase 3: we no longer dump the entire data set into the page — that was the
 * old blob approach.  Instead we return:
 *   • user identity
 *   • user preferences (dark mode, language, notifications, categories,
 *     notifSeenAt) — needed immediately to render without a flash
 *   • the 50 most-recent expenses (so the dashboard has data on first paint)
 *   • upcoming/overdue bills (needed for the bell-icon badge)
 *   • due-today recurring (needed for the badge)
 *   • current-month budgets (needed for the badge)
 *
 * Full data sets are fetched lazily by the per-resource API endpoints after
 * the page renders (see loadPageData() in script.js).
 */
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

        $userId = (int) $row['id'];

        // Ensure migration has run for this user.
        if (!sw_is_migrated($db, $userId)) {
            sw_migrate_user_state($db, $userId);
        }

        // ── Preferences (always needed on first paint) ─────────────────────
        $stmt = $db->prepare(
            'SELECT dark_mode, notifications_email, language,
                    categories, notif_seen_at, extra_json
             FROM user_preferences
             WHERE user_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $prefs = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $darkMode   = $prefs !== null ? (bool) $prefs['dark_mode']           : false;
        $notifEmail = $prefs !== null ? (bool) $prefs['notifications_email'] : true;
        $language   = $prefs !== null ? (string) $prefs['language']           : 'en';
        $notifSeenAt = $prefs !== null ? ($prefs['notif_seen_at'] ?? null) : null;
        $categories = [];
        if ($prefs !== null && !empty($prefs['categories'])) {
            $decoded = json_decode((string) $prefs['categories'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $categories = $decoded;
            }
        }

        // ── Recent expenses — last 50 rows (dashboard first paint) ─────────
        $expenses = [];
        $stmt = $db->prepare(
            'SELECT external_id AS id, amount, category, date, note, receipt
             FROM expenses
             WHERE user_id = ?
             ORDER BY date DESC, id DESC
             LIMIT 50'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $expenses[] = [
                'id'       => $r['id'],
                'amount'   => (float) $r['amount'],
                'category' => $r['category'],
                'date'     => $r['date'],
                'note'     => $r['note'],
                'receipt'  => null, // omit receipt data-URLs from boot payload
            ];
        }
        $stmt->close();

        // ── Current-month budgets (badge needs these) ───────────────────────
        $curMonth = date('Y-m');
        $budgets  = [];
        $stmt     = $db->prepare(
            'SELECT external_id AS id, category, month, limit_amount AS `limit`
             FROM budgets
             WHERE user_id = ? AND month = ?
             ORDER BY id ASC'
        );
        $stmt->bind_param('is', $userId, $curMonth);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $budgets[] = [
                'id'       => $r['id'],
                'category' => $r['category'],
                'month'    => $r['month'],
                'limit'    => (float) $r['limit'],
            ];
        }
        $stmt->close();

        // ── Upcoming / overdue bills (badge needs these) ────────────────────
        $bills = [];
        $stmt  = $db->prepare(
            "SELECT external_id AS id, name, amount, category,
                    due_date AS dueDate, status,
                    paid_date AS paidDate, reference
             FROM bills
             WHERE user_id = ? AND status IN ('upcoming','overdue')
             ORDER BY due_date ASC
             LIMIT 30"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $bills[] = [
                'id'        => $r['id'],
                'name'      => $r['name'],
                'amount'    => (float) $r['amount'],
                'category'  => $r['category'],
                'dueDate'   => $r['dueDate'],
                'status'    => $r['status'],
                'paidDate'  => $r['paidDate'],
                'reference' => $r['reference'],
            ];
        }
        $stmt->close();

        // ── Active recurring (badge needs due-today items) ──────────────────
        $recurring = [];
        $stmt      = $db->prepare(
            'SELECT external_id AS id, name, amount, category, frequency,
                    start_date AS startDate, end_date AS endDate,
                    next_due AS nextDue, active
             FROM recurring_items
             WHERE user_id = ? AND active = 1
             ORDER BY next_due ASC
             LIMIT 30'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $recurring[] = [
                'id'        => $r['id'],
                'name'      => $r['name'],
                'amount'    => (float) $r['amount'],
                'category'  => $r['category'],
                'frequency' => $r['frequency'],
                'startDate' => $r['startDate'],
                'endDate'   => $r['endDate'],
                'nextDue'   => $r['nextDue'],
                'active'    => (bool) $r['active'],
            ];
        }
        $stmt->close();

        return [
            'user'   => $user,
            'google' => sw_google_bootstrap(),
            'state'  => [
                'expenses'      => $expenses,
                'budgets'       => $budgets,
                'recurring'     => $recurring,
                'bills'         => $bills,
                'darkMode'      => $darkMode,
                'notifications' => ['email' => $notifEmail],
                'language'      => $language,
                'categories'    => $categories,
                'notifSeenAt'   => $notifSeenAt,
            ],
        ];
    } catch (Throwable $e) {
        // [SEC F-13] Never expose exception details in the boot payload — visible to any page visitor.
        // Log the error server-side for debugging.
        error_log('[SpendWise] Boot error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return [
            'user'   => null,
            'state'  => null,
            'google' => sw_google_bootstrap(),
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
                session_regenerate_id(true); // [SEC F-11] prevent session fixation

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
                session_regenerate_id(true); // [SEC F-11] prevent session fixation

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
                // [SEC F-05] Enforce length limits to match DB column and prevent abuse.
                if (strlen($name) > 100) {
                    sw_error('Name too long. Maximum 100 characters.');
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
                session_regenerate_id(true); // [SEC F-11] prevent session fixation

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
                // [SEC F-02] Always return the same generic response regardless of whether
                // the email exists — prevents account enumeration via timing/message differences.
                if ($userRow !== null) {
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expiresAt = date('Y-m-d H:i:s', time() + SW_OTP_EXPIRES_IN);

                    // [SEC F-07] Reset attempts counter when issuing a new OTP.
                    $stmt = $db->prepare(
                        'INSERT INTO password_reset_codes (user_id, email, code, attempts, expires_at)
                         VALUES (?, ?, ?, 0, ?)
                         ON DUPLICATE KEY UPDATE email = VALUES(email), code = VALUES(code), attempts = 0, expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP'
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
                        error_log('[SpendWise] OTP email error: ' . $e->getMessage());
                        sw_error('An unexpected error occurred. Please try again.', 500);
                    }
                }
                // [SEC F-02] Return same message whether email exists or not (anti-enumeration).
                sw_json_response(200, [
                    'ok' => true,
                    'data' => [
                        'expiresIn' => SW_OTP_EXPIRES_IN,
                        'maskedEmail' => sw_mask_email($email),
                        'message' => 'If that email is registered, we sent a 6-digit code.',
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

                // [SEC F-07] Fetch OTP with attempts column.
                $stmt = $db->prepare('SELECT code, expires_at, attempts FROM password_reset_codes WHERE user_id = ? LIMIT 1');
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
                // [SEC F-07] Reject after 5 failed attempts to prevent brute-force.
                if ((int) $otpRow['attempts'] >= 5) {
                    sw_delete_password_reset_code($db, $userId);
                    sw_error('Too many incorrect attempts. Request a new code.');
                }
                if ((string) $otpRow['code'] !== $code) {
                    // Increment the attempts counter.
                    $stmt = $db->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE user_id = ?');
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $stmt->close();
                    $remaining = 5 - ((int) $otpRow['attempts'] + 1);
                    sw_error($remaining > 0 ? "Incorrect code. {$remaining} attempt(s) remaining." : 'Too many incorrect attempts. Request a new code.');
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
                // [SEC F-08] Invalidate the verified flag immediately after use (one-time guarantee).
                sw_forget_verified_reset($email);

                // [SEC F-08] Notify the user that their password was changed.
                try {
                    sw_send_password_changed_email((string) ($userRow['name'] ?? ''), $email);
                } catch (Throwable $ignored) {
                    // Non-fatal: password is already updated; log and continue.
                    error_log('[SpendWise] Failed to send password-changed notification to ' . $email . ': ' . $ignored->getMessage());
                }

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
                // [SEC F-05] Enforce name length limit.
                if (strlen($name) > 100) {
                    sw_error('Name too long. Maximum 100 characters.');
                }
                // [SEC F-10] Validate phone format: only digits, spaces, +, -, (, ) — max 20 chars.
                if ($phone !== '' && !preg_match('/^[0-9\s+\-().]{1,20}$/', $phone)) {
                    sw_error('Invalid phone number. Use digits, spaces, +, -, (, ) only (max 20 characters).');
                }
                // [SEC F-03] Enforce server-side avatar size limit — client-side check is bypassable.
                if ($avatar !== null) {
                    if (strlen($avatar) > 204800) { // ~150 KB raw when decoded from base64
                        sw_error('Avatar too large. Maximum 150 KB.');
                    }
                    if (!preg_match('/^data:image\/(jpeg|png|webp|gif);base64,/', $avatar)) {
                        sw_error('Invalid avatar format. Must be a JPEG, PNG, WebP, or GIF image.');
                    }
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
        // [SEC F-06] Never expose internal exception details to the client.
        // Log internally for debugging, return a generic message.
        error_log('[SpendWise] API error in action=' . ($action ?? 'unknown') . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        sw_json_response(500, ['ok' => false, 'error' => 'An unexpected error occurred. Please try again.']);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    sw_handle_api_request();
}
