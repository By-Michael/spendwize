<?php
/**
 * SpendWise — Phase 2 API bootstrap
 *
 * Every api/*.php and auth/*.php file includes this once via:
 *   require_once __DIR__ . '/../api/_bootstrap.php';   (from api/)
 *   require_once __DIR__ . '/../../api/_bootstrap.php'; (from auth/)
 *
 * Provides:
 *   - functions.php loaded (db, helpers, session)
 *   - CORS headers
 *   - sw_method()           — returns uppercased HTTP method
 *   - sw_api_require_auth() — 401s if no session, returns user array
 *   - sw_body()             — parsed JSON request body (cached)
 *   - sw_param()            — query-string getter with default
 *   - sw_ok()               — send 200 JSON success envelope
 *   - sw_created()          — send 201 JSON success envelope
 *   - sw_fail()             — send error JSON envelope (alias of sw_error())
 */

declare(strict_types=1);

// Walk up from wherever this file sits to find functions.php.
$_swRoot = dirname(__DIR__);
require_once $_swRoot . '/functions.php';
unset($_swRoot);

// ── CORS ──────────────────────────────────────────────────────────────────────
// [SEC F-01] Restrict CORS to the production domain only.
// Replace SW_APP_ORIGIN in config.php with your exact production URL, e.g.:
//   define('SW_APP_ORIGIN', 'https://yourdomain.com');
// Never use '*' when session cookies are involved — that enables CSRF from any site.
$allowedOrigin = defined('SW_APP_ORIGIN') ? SW_APP_ORIGIN : '';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowedOrigin !== '' && $requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} elseif ($allowedOrigin === '') {
    // Fallback for local dev when SW_APP_ORIGIN is not set — restrict to same origin
    header('Access-Control-Allow-Origin: ' . ($requestOrigin ?: 'null'));
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function sw_method(): string
{
    return strtoupper(trim($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

/**
 * Require an authenticated session.
 *
 * Returns the session user array on success; sends a 401 and exits otherwise.
 *
 * @return array{id:int,name:string,email:string,phone:string,avatar:string|null,provider:string,hasPassword:bool}
 */
function sw_api_require_auth(): array
{
    $user = sw_session_user();
    if ($user === null) {
        sw_json_response(401, ['ok' => false, 'error' => 'Authentication required.']);
    }
    return $user;
}

/**
 * Return the decoded JSON request body, cached across calls.
 */
function sw_body(): array
{
    static $body = null;
    if ($body === null) {
        $body = sw_read_input();
    }
    return $body;
}

/**
 * Get a query-string parameter, with an optional default.
 */
function sw_param(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

/**
 * Send a 200 OK JSON envelope: { ok: true, data: $data }.
 */
function sw_ok(array $data = []): never
{
    sw_json_response(200, ['ok' => true, 'data' => $data]);
}

/**
 * Send a 201 Created JSON envelope: { ok: true, data: $data }.
 */
function sw_created(array $data = []): never
{
    sw_json_response(201, ['ok' => true, 'data' => $data]);
}

/**
 * Send an error JSON envelope.  Alias of sw_error() for readability.
 */
function sw_fail(string $message, int $status = 400): never
{
    sw_error($message, $status);
}
