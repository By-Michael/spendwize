<?php
// config.example.php — safe template, commit this to git
// To set up locally:  copy this file to config.php and fill in your values.
// To set up on server: copy this file to config.production.php, fill in values,
//                      then upload via FTP — never via git push.

return [
    'db' => [
        'host' => '',   // e.g. 127.0.0.1 (local) or sql308.infinityfree.com (production)
        'user' => '',   // your DB username
        'pass' => '',   // your DB password
        'name' => '',   // your DB name
    ],
    'smtp' => [
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'secure'     => 'tls',
        'username'   => '',   // your Gmail address
        'password'   => '',   // Gmail App Password (not your main password)
        'from_email' => '',   // usually same as username
        'from_name'  => 'SpendWise',
    ],
    'google_client_id' => '',   // from Google Cloud Console > APIs & Services > Credentials
    'groq_api_key' => '',       // from console.groq.com/keys — free tier, powers the AI advisor
    'ocrspace_api_key' => '',   // from ocr.space/ocrapi — free tier, powers receipt scanning
];
