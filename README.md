/# SpendWise 💰

A personal finance web app for tracking expenses, managing budgets, monitoring bills, and understanding your spending — built for **Ethiopian Birr (ETB)** users, with full **Amharic (አማርኛ) language support**.

---

## ✨ Features

- **Dashboard** — At-a-glance overview of your spending, upcoming bills, budget status, and recurring expenses
- **Expense Tracking** — Add, edit, delete, and search expenses with categories, dates, notes, and receipt images
- **Recurring Expenses** — Set up repeating transactions (daily, weekly, monthly, yearly) with confirm/skip controls
- **Budget Manager** — Create monthly category budgets with visual progress bars and over-budget alerts
- **Bills Tracker** — Track utility and subscription bills (electricity, water, internet, TV, etc.) with due-date reminders and overdue detection
- **Reports** — Charts and summaries of spending patterns over time
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
| Email | [PHPMailer](https://github.com/PHPMailer/PHPMailer) + Gmail SMTP |
| Auth | Session-based + Google Identity Services |
| Hosting | InfinityFree (FTP deploy) |
| CI/CD | GitHub Actions |

---

## 📁 Project Structure

```
spendwise/
├── index.php                  # Entry point — bootstraps app & outputs HTML shell
├── functions.php              # All backend logic: auth, DB, API handlers
├── script.js                  # Entire frontend app (vanilla JS)
├── style.css                  # All styles
├── database.sql               # SQL schema (users, states, password reset codes)
├── assets/
│   └── spendwise-logo.png
├── i18n/
│   ├── am.js                  # Amharic translations
│   └── extra.js               # Additional i18n strings
├── PHPMailer/                 # PHPMailer library (email sending)
├── scripts/
│   └── build-i18n.js          # Script to regenerate translation files
├── mailer.local.php.example   # Template for local SMTP config
└── .github/
    └── workflows/
        └── deploy.yml         # Auto-deploy to InfinityFree via FTP
```

---

## 🚀 Getting Started

### Requirements

- PHP 8.0 or higher
- MySQL 5.7+ / MariaDB 10.4+
- A web server (Apache or Nginx)
- Gmail account (for sending OTP/password-reset emails)
- Google OAuth Client ID (for Google Sign-In, optional)

### 1. Clone the repo

```bash
git clone https://github.com/By-Michael/spendwise.git
cd spendwise
```

### 2. Set up the database

Import the schema:

```bash
mysql -u your_user -p < database.sql
```

Or run the contents of `database.sql` in your MySQL client. The app will also auto-create the tables on first run via `sw_ensure_schema()`.

### 3. Configure the database connection

Create a file called `db.local.php` in the project root (this file is git-ignored):

```php
<?php
return [
    'host' => 'localhost',
    'user' => 'your_db_user',
    'pass' => 'your_db_password',
    'name' => 'spendwise_app',
];
```

> If `db.local.php` is not found, the app falls back to the InfinityFree credentials hardcoded in `functions.php`. Update those for production.

### 4. Configure email (for OTP / password reset)

Copy the example file and fill in your Gmail credentials:

```bash
cp mailer.local.php.example mailer.local.php
```

Edit `mailer.local.php`:

```php
<?php
return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'secure'     => 'tls',
    'username'   => 'your-email@gmail.com',
    'password'   => 'your-app-password',   // Use a Gmail App Password, not your main password
    'from_email' => 'your-email@gmail.com',
    'from_name'  => 'SpendWise',
];
```

> **Tip:** Generate a Gmail App Password at [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords) (requires 2FA enabled).

You can also set these via environment variables (`SW_SMTP_HOST`, `SW_SMTP_USERNAME`, `SW_SMTP_PASSWORD`, etc.) instead of the file.

### 5. Configure Google Sign-In (optional)

If you want Google login, set your Google OAuth Client ID in `functions.php`:

```php
const SW_GOOGLE_CLIENT_ID = 'YOUR_CLIENT_ID.apps.googleusercontent.com';
```

Get a Client ID from the [Google Cloud Console](https://console.cloud.google.com/apis/credentials).

### 6. Serve the app

Point your web server document root to the project folder, or use PHP's built-in server for local development:

```bash
php -S localhost:8000
```

Then open [http://localhost:8000](http://localhost:8000).

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
- Receipt images are stored as Base64 in the database (max 2 MB per image)
- App state is persisted server-side per user in the `user_states` table

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
"test" 
