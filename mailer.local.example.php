<?php
/**
 * mailer.local.example.php — SMTP credential template
 *
 * HOW TO USE:
 *   1. Copy this file to  mailer.local.php  (same folder)
 *   2. Fill in your real values below
 *   3. Upload mailer.local.php to your server via FTP
 *      — never commit it to git (it's already in .gitignore)
 *
 * GMAIL SETUP (recommended):
 *   • Enable 2-Step Verification on your Google account
 *   • Go to: myaccount.google.com → Security → App passwords
 *   • Create an app password for "Mail" — it looks like: abcd efgh ijkl mnop
 *   • Paste it as 'password' below (spaces are stripped automatically)
 *
 * ALTERNATIVE: Use any SMTP provider (Brevo, Mailgun, SendGrid, Zoho, etc.)
 *   Just change host/port/secure to match your provider's settings.
 */

return [
    // ── Gmail (most common) ──────────────────────────────────────────────────
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'secure'     => 'tls',          // 'tls' for port 587, 'ssl' for port 465
    'username'   => '',             // your full Gmail address, e.g. you@gmail.com
    'password'   => '',             // Gmail App Password — NOT your regular password
    'from_email' => '',             // usually the same as username
    'from_name'  => 'SpendWise',

    // ── Outlook / Hotmail ────────────────────────────────────────────────────
    // 'host'     => 'smtp-mail.outlook.com',
    // 'port'     => 587,
    // 'secure'   => 'tls',
    // 'username' => 'you@outlook.com',
    // 'password' => 'your-password',

    // ── Brevo (free 300 emails/day) ──────────────────────────────────────────
    // 'host'     => 'smtp-relay.brevo.com',
    // 'port'     => 587,
    // 'secure'   => 'tls',
    // 'username' => 'your-brevo-login-email',
    // 'password' => 'your-brevo-smtp-key',   // from Brevo → SMTP & API → SMTP
];
