# SpendWise 💰

A personal finance web app for tracking expenses, managing budgets, monitoring bills, and understanding your spending — built for **Ethiopian Birr (ETB)** users, with full **Amharic (አማርኛ) language support**.

---
// this is just a test
## ✨ Features

- **Dashboard** — At-a-glance overview of your spending, upcoming bills, budget status, and recurring expenses
- **Expense Tracking** — Add, edit, delete, and search expenses with categories, dates, notes, and receipt images
- **OCR Receipt Scanning** — Smart text extraction (OCR.space + Groq) that scans uploaded receipt images and automatically pre-fills the expense amount, date, merchant, and line items
- **Financial AI Insights** — Groq-powered AI assistant that analyzes spending patterns, provides personalized budgeting advice, and detects unusual expense anomalies
- **Recurring Expenses** — Set up repeating transactions (daily, weekly, fortnightly, monthly, quarterly, yearly) with confirm/skip controls
- **Budget Manager** — Create monthly category budgets with visual progress bars and over-budget alerts
- **Bills Tracker** — Track utility and subscription bills (electricity, water, internet, TV, etc.) with due-date reminders and overdue detection
- **Reports** — Charts and summaries of spending patterns over time (monthly trend, category breakdown, budget vs. actual, daily spend, top expenses, recurring summary)
- **Email Notifications** — Daily cron job sends due-date reminders and periodic engagement emails
- **REST API** — Resource-based JSON API (`/api`, `/auth`) alongside the original monolithic endpoint, fully documented in [`api/README.md`](api/README.md)
- **Multi-language UI** — Switch between English and Amharic (አማርኛ) at any time
- **Dark Mode** — Full dark/light theme toggle
- **Authentication** — Email/password sign-up & login, Google Sign-In, password reset via OTP email
- **Profile Management** — Update name, avatar, phone number, and notification preferences
- **Responsive Design** — Works on desktop and mobile, with a bottom navigation bar on small screens
- **Auto-Deploy** — GitHub Actions workflow deploys to InfinityFree via FTP on every push to `main`

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vanilla JavaScript (no framework), CSS |
| Charts | [Chart.js 4.4](https://www.chartjs.org/) |
| Icons | [Lucide](https://lucide.dev/) |
| Backend | PHP 8+ |
| Database | MySQL (via MySQLi) |
| Email | [PHPMailer](https://github.com/PHPMailer/PHPMailer) + SMTP (Gmail by default) |
| AI / Insights | [Groq](https://console.groq.com/) (`llama-3.3-70b-versatile`) |
| OCR | [OCR.space](https://ocr.space/ocrapi) + Groq (`llama-3.1-8b-instant`) for receipt parsing |
| Auth | Session-based + Google Identity Services |
| Hosting | InfinityFree (FTP deploy) |
| CI/CD | GitHub Actions |

---

## 📁 Project Structure

```
spendwise/
├── index.php                     # Entry point — bootstraps app & outputs HTML shell
├── functions.php                 # Core: config loading, DB, auth, legacy ?action= API
├── ai.php                        # AI financial insights endpoint (Groq)
├── receipt.php                   # OCR receipt scanning endpoint (OCR.space + Groq)
├── script.js                     # Entire frontend app (vanilla JS)
├── style.css                     # All styles
├── database.sql                  # SQL schema (users, states, password reset codes)
├── config.example.php            # Config template — copy to config.php / config.production.php
├── mailer.local.example.php      # Legacy standalone SMTP credential template
├── api/                          # REST API — one file per resource
│   ├── README.md                 # Full API reference (endpoints, request/response shapes)
│   ├── _bootstrap.php            # Shared: loads functions.php, CORS, auth guard, helpers
│   ├── expenses.php              # GET/POST/PUT/DELETE  /api/expenses.php
│   ├── budgets.php                # GET/POST/PUT/DELETE  /api/budgets.php
│   ├── recurring.php             # GET/POST/PUT/DELETE  /api/recurring.php
│   ├── bills.php                  # GET/POST/PUT/DELETE  /api/bills.php
│   ├── dashboard.php             # GET                  /api/dashboard.php
│   ├── reports.php                # GET                  /api/reports.php?type=<type>
│   └── profile.php                # GET/PUT              /api/profile.php
├── auth/                         # Authentication endpoints
│   ├── login.php
│   ├── signup.php
│   ├── logout.php
│   ├── google.php
│   ├── otp.php                   # ?action=send | verify
│   └── reset-password.php
├── assets/
│   └── spendwise-logo.png
├── i18n/
│   ├── am.js                     # Amharic translations
│   └── extra.js                  # Additional i18n strings
├── PHPMailer/                    # PHPMailer library (email sending)
├── scripts/
│   ├── build-i18n.js             # Script to regenerate translation files
│   └── send-notifications.php    # Cron job: daily bill/recurring reminders + engagement emails
└── .github/
    └── workflows/
        └── deploy.yml             # Auto-deploy to InfinityFree via FTP
```

---

## 🚀 Getting Started

### Requirements

- PHP 8.0 or higher (with the `gd` extension, for receipt image processing)
- MySQL 5.7+ / MariaDB 10.4+
- A web server (Apache or Nginx)
- An SMTP account for sending OTP/password-reset emails (Gmail by default)
- A [Groq](https://console.groq.com/keys) API key (free tier) — powers AI insights & receipt parsing
- An [OCR.space](https://ocr.space/ocrapi) API key (free tier) — powers receipt text extraction
- A Google OAuth Client ID (optional) — for Google Sign-In

### 1. Clone the repo

```bash
git clone https://github.com/By-Michael/spendwize.git
cd spendwize
```

### 2. Set up the database

Import the schema:

```bash
mysql -u your_user -p < database.sql
```

Or run the contents of `database.sql` in your MySQL client. The app will also auto-create the tables on first run via `sw_ensure_schema()`.

### 3. Configure the app

All configuration — database, SMTP, Google Client ID, Groq, and OCR.space — now lives in a single config file.

Copy the template:

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
<?php
return [
    'db' => [
        'host' => '',   // e.g. 127.0.0.1 (local) or sql308.infinityfree.com (production)
        'user' => '',
        'pass' => '',
        'name' => '',
    ],
    'smtp' => [
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'secure'     => 'tls',
        'username'   => '',   // your Gmail address
        'password'   => '',   // Gmail App Password — not your main password
        'from_email' => '',
        'from_name'  => 'SpendWise',
    ],
    'google_client_id' => '',   // from Google Cloud Console > APIs & Services > Credentials
    'groq_api_key'      => '',  // from console.groq.com/keys — free tier, powers the AI advisor
    'ocrspace_api_key'  => '',  // from ocr.space/ocrapi — free tier, powers receipt scanning
];
```

`functions.php` loads config in this order: `config.php` → `config.production.php` → fails loudly if neither exists. Use `config.php` for local dev and `config.production.php` for your live server (upload it manually via FTP — never commit it).

`groq_api_key` and `ocrspace_api_key` can also be set via environment variables (`SW_GROQ_API_KEY`, `SW_OCRSPACE_API_KEY`) instead of the config file.

> **Tip:** Generate a Gmail App Password at [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords) (requires 2FA enabled).

### 4. Serve the app

Point your web server document root to the project folder, or use PHP's built-in server for local development:

```bash
php -S localhost:8000
```

Then open [http://localhost:8000](http://localhost:8000).

### 5. (Optional) Set up daily email notifications

`scripts/send-notifications.php` sends due-date reminders for bills/recurring items and periodic engagement emails. Run it via cron on your server:

```bash
0 8 * * * php /path/to/spendwise/scripts/send-notifications.php >> /var/log/spendwise-notif.log 2>&1
```

---

## ⚙️ Auto-Deploy (GitHub Actions)

The repo includes a workflow (`.github/workflows/deploy.yml`) that automatically deploys to InfinityFree via FTP whenever you push to the `main` branch.

### Set up GitHub Secrets

Go to your repo → **Settings → Secrets and variables → Actions** and add:

| Secret | Value |
|--------|-------|
| `FTP_HOST` | Your InfinityFree FTP host (e.g. `ftpupload.net`) |
| `FTP_USER` | Your FTP username |
| `FTP_PASS` | Your FTP password |

> Remember to upload `config.production.php` to the server manually via FTP — it's git-ignored and won't be deployed by the workflow.

---

## 🔌 API

SpendWise now exposes a proper resource-based REST API under `api/` and `auth/`, alongside the original monolithic `?action=` endpoint in `functions.php` (kept for backward compatibility — the legacy frontend continues to work unchanged).

See [`api/README.md`](api/README.md) for the full reference: endpoints, request/response shapes, query filters, and the common `{ ok, data }` / `{ ok, error }` response envelope.

Quick overview:

| Area | Files |
|------|-------|
| Expenses, budgets, recurring items, bills, dashboard, reports, profile | `api/*.php` |
| Login, signup, logout, Google sign-in, OTP, password reset | `auth/*.php` |
| AI financial insights | `ai.php` |
| Receipt OCR scanning | `receipt.php` |

All `api/` endpoints require an active PHP session (set by any `auth/` endpoint) and return JSON.

---

## 🌍 Internationalization (i18n)

The app supports **English** and **Amharic (አማርኛ)**. Translation strings live in:

- `i18n/am.js` — Amharic translations (auto-generated, do not edit by hand)
- `i18n/extra.js` — Additional strings

To regenerate translations after adding new strings:

```bash
node scripts/build-i18n.js
```

Users can switch languages anytime from the **Profile → Settings** page.

---

## 💡 Notes

- All amounts are displayed in **Ethiopian Birr (ETB)**
- Expense categories: Food, Transport, Entertainment, Health, Utilities, Shopping, Rent, Education, Personal Care, Other (+ custom)
- Bill categories: Electricity, Water, Internet, TV, Other
- Receipt images are stored as Base64 in the database (max 2 MB per image); they're resized/re-compressed under the hood before being sent to OCR.space
- App state is persisted server-side per user in the `user_states` table
- Never commit `config.php`, `config.production.php`, `mailer.local.php`, or any credentials file — they're all covered by `.gitignore`

---

## License

Copyright © 2026. All rights reserved.

This project is proprietary and closed-source. Unauthorized copying, modification, distribution, or commercial use of this software, via any medium, is strictly prohibited.

---

## 🙏 Acknowledgements

- [PHPMailer](https://github.com/PHPMailer/PHPMailer) for email delivery
- [Chart.js](https://www.chartjs.org/) for charts and data visualization
- [Lucide](https://lucide.dev/) for icons
- [Google Identity Services](https://developers.google.com/identity) for OAuth login
- [Groq](https://groq.com/) for AI insights and receipt parsing
- [OCR.space](https://ocr.space/) for receipt text extraction
