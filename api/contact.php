<?php
/**
 * SpendWise — Landing page contact form
 *
 * POST /api/contact.php
 * Body: { name, email, message }
 * Returns: { ok, data: { message } }
 *
 * Public endpoint — no auth required. Delivers to the address configured
 * in config.php ('contact_email'), never hardcoded here.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

try {
    if (sw_method() !== 'POST') {
        sw_fail('Method not allowed.', 405);
    }

    $body = sw_body();

    $name = trim((string) ($body['name'] ?? ''));
    $email = sw_normalize_email((string) ($body['email'] ?? ''));
    $message = trim((string) ($body['message'] ?? ''));

    // Honeypot: a hidden field legitimate users never fill in. Bots that
    // auto-fill every field will trip it; pretend success so they move on.
    $honeypot = trim((string) ($body['website'] ?? ''));
    if ($honeypot !== '') {
        sw_ok(['message' => "Thanks! We'll be in touch soon."]);
    }

    if ($name === '' || $email === '' || $message === '') {
        sw_fail('Please fill in your name, email, and message.');
    }
    if (strlen($name) > 100) {
        sw_fail('Name is too long.');
    }
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sw_fail('Please enter a valid email address.');
    }
    if (strlen($message) < 5) {
        sw_fail('Message is too short.');
    }
    if (strlen($message) > 5000) {
        sw_fail('Message is too long.');
    }

    try {
        sw_send_contact_message($name, $email, $message);
    } catch (RuntimeException $e) {
        error_log('[contact.php] ' . $e->getMessage());
        sw_fail('Could not send your message right now. Please try again later.', 502);
    }

    sw_ok(['message' => "Thanks! We'll be in touch soon."]);
} catch (Throwable $e) {
    error_log('[contact.php] Unexpected error: ' . $e->getMessage());
    sw_fail('Something went wrong. Please try again.', 500);
}
