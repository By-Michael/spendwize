# SpendWise — Phase 2 API

REST API layer extracted from the monolithic `functions.php` switch statement.
Each resource now lives in its own file and speaks proper HTTP methods.

---

## Directory structure

```
api/
  _bootstrap.php      Shared: loads functions.php, CORS headers, auth guard, helpers
  expenses.php        GET/POST/PUT/DELETE  /api/expenses.php
  budgets.php         GET/POST/PUT/DELETE  /api/budgets.php
  recurring.php       GET/POST/PUT/DELETE  /api/recurring.php
  bills.php           GET/POST/PUT/DELETE  /api/bills.php
  dashboard.php       GET                  /api/dashboard.php
  reports.php         GET                  /api/reports.php?type=<type>
  profile.php         GET/PUT              /api/profile.php

auth/
  login.php           POST  /auth/login.php
  signup.php          POST  /auth/signup.php
  logout.php          POST  /auth/logout.php
  google.php          POST  /auth/google.php
  otp.php             POST  /auth/otp.php?action=send|verify
  reset-password.php  POST  /auth/reset-password.php
```

---

## Common response envelope

Every endpoint returns JSON in this shape:

```json
{ "ok": true,  "data": { ... } }   // 200 or 201
{ "ok": false, "error": "..."  }   // 4xx or 5xx
```

---

## Authentication

All `api/` endpoints require an active PHP session (set by any `auth/` endpoint).
Unauthenticated requests return `401`.

---

## Endpoints

### Auth

#### `POST /auth/login.php`
```json
// Body
{ "email": "user@example.com", "password": "secret" }

// Response
{ "ok": true, "data": { "user": { ... }, "state": { ... } } }
```

#### `POST /auth/signup.php`
```json
// Body
{ "name": "Alice", "email": "alice@example.com", "password": "atleast8" }

// Response — same shape as login
```

#### `POST /auth/logout.php`
```json
// Response
{ "ok": true, "data": { "loggedOut": true } }
```

#### `POST /auth/google.php`
```json
// Body
{ "credential": "<Google One-Tap JWT>" }

// Response — same shape as login
```

#### `POST /auth/otp.php?action=send`
```json
// Body
{ "email": "user@example.com" }

// Response
{ "ok": true, "data": { "expiresIn": 300, "maskedEmail": "u***@example.com", "message": "..." } }
```

#### `POST /auth/otp.php?action=verify`
```json
// Body
{ "email": "user@example.com", "code": "123456" }

// Response
{ "ok": true, "data": { "verified": true } }
```

#### `POST /auth/reset-password.php`
Requires a prior successful `/auth/otp.php?action=verify`.
```json
// Body
{ "email": "user@example.com", "password": "newpassword" }

// Response
{ "ok": true, "data": { "updated": true } }
```

---

### Expenses — `/api/expenses.php`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/expenses.php` | List expenses |
| GET | `/api/expenses.php?id=<ext_id>` | Get one |
| POST | `/api/expenses.php` | Create |
| PUT | `/api/expenses.php?id=<ext_id>` | Update |
| DELETE | `/api/expenses.php?id=<ext_id>` | Delete |

**GET query filters:**
- `?category=Food` — exact category match
- `?month=2025-06` — filter by month (YYYY-MM)
- `?from=2025-06-01&to=2025-06-30` — date range
- `?limit=100&offset=0` — pagination (max 2000)

**POST / PUT body:**
```json
{
  "id": "uuid-or-frontend-id",
  "amount": 42.50,
  "category": "Food",
  "date": "2025-06-15",
  "note": "Lunch",
  "receipt": "<base64 or null>"
}
```

**Expense object:**
```json
{
  "id": "uuid",
  "amount": 42.50,
  "category": "Food",
  "date": "2025-06-15",
  "note": "Lunch",
  "receipt": null
}
```

---

### Budgets — `/api/budgets.php`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/budgets.php` | List all |
| GET | `/api/budgets.php?id=<ext_id>` | Get one |
| GET | `/api/budgets.php?month=2025-06` | Filter by month |
| POST | `/api/budgets.php` | Create |
| PUT | `/api/budgets.php?id=<ext_id>` | Update |
| DELETE | `/api/budgets.php?id=<ext_id>` | Delete |

**POST / PUT body:**
```json
{ "id": "uuid", "category": "Food", "month": "2025-06", "limit": 500.00 }
```

---

### Recurring Items — `/api/recurring.php`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/recurring.php` | List all |
| GET | `/api/recurring.php?active=1` | Only active |
| GET | `/api/recurring.php?due_before=2025-07-01` | Due before date |
| POST | `/api/recurring.php` | Create |
| PUT | `/api/recurring.php?id=<ext_id>` | Update |
| DELETE | `/api/recurring.php?id=<ext_id>` | Delete |

**Valid frequencies:** `daily`, `weekly`, `fortnightly`, `monthly`, `quarterly`, `yearly`

**POST / PUT body:**
```json
{
  "id": "uuid",
  "name": "Netflix",
  "amount": 15.99,
  "category": "Entertainment",
  "frequency": "monthly",
  "startDate": "2025-01-01",
  "endDate": null,
  "nextDue": "2025-07-01",
  "active": true
}
```

---

### Bills — `/api/bills.php`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/bills.php` | List all |
| GET | `/api/bills.php?status=upcoming` | Filter by status |
| GET | `/api/bills.php?from=…&to=…` | Date range on due_date |
| POST | `/api/bills.php` | Create |
| POST | `/api/bills.php?action=pay` | Mark as paid |
| POST | `/api/bills.php?action=unpay` | Mark as upcoming |
| PUT | `/api/bills.php?id=<ext_id>` | Update |
| DELETE | `/api/bills.php?id=<ext_id>` | Delete |

**Valid statuses:** `upcoming`, `overdue`, `paid`

**Pay action body:**
```json
{ "id": "uuid", "paidDate": "2025-06-20" }
```

---

### Dashboard — `/api/dashboard.php`

```
GET /api/dashboard.php
GET /api/dashboard.php?month=2025-06
```

Single aggregated response for the home screen. Returns totals, budget status, upcoming bills, overdue bills, recurring items due soon, and the 5 most recent expenses — in one request.

```json
{
  "ok": true,
  "data": {
    "month": "2025-06",
    "totalExpenses": 1234.56,
    "expensesByCategory": [
      { "category": "Food", "total": 400.00, "count": 12 }
    ],
    "budgetStatus": [
      {
        "id": "...", "category": "Food", "month": "2025-06",
        "limit": 500.00, "spent": 400.00, "remaining": 100.00,
        "pct": 80.0, "over": false
      }
    ],
    "upcomingBills": [ ... ],
    "overdueBills": [ ... ],
    "recurringDueSoon": [ ... ],
    "recentExpenses": [ ... ]
  }
}
```

---

### Reports — `/api/reports.php?type=<type>`

| Type | Description |
|------|-------------|
| `monthly_trend` | Monthly totals for last N months. `?months=12` |
| `category_breakdown` | Spend per category with percentages. `?month=` or `?from=&to=` |
| `budget_vs_actual` | Budget limit vs actual spend per category. `?month=` |
| `daily_spending` | Day-by-day totals for a month. `?month=` |
| `top_expenses` | Highest individual expenses. `?month=` `?limit=10` |
| `recurring_summary` | Monthly/annual cost of active recurring items. |

---

### Profile — `/api/profile.php`

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/api/profile.php` | Get user + preferences |
| PUT | `/api/profile.php` | Update name / phone / avatar |
| PUT | `/api/profile.php?section=prefs` | Update preferences |
| PUT | `/api/profile.php?section=password` | Change password |

**Preferences body:**
```json
{
  "darkMode": true,
  "language": "en",
  "notifications": { "email": true },
  "categories": ["Food", "Transport", "Entertainment"]
}
```

**Password change body:**
```json
{ "current_password": "old", "new_password": "newpass8chars" }
```

---

## Backward compatibility

The original `functions.php` `save_state` / `load_state` / `update_profile` actions in `?action=X` are still fully functional — the legacy frontend continues to work without changes.

The new API files are purely **additive**. No existing code was modified.

---

## Deploying

1. Upload the `api/` and `auth/` directories alongside your existing files.
2. No `.htaccess` changes needed — each file is a normal PHP script.
3. The `_bootstrap.php` walks up one directory to load `functions.php`; the relative path must be maintained.
4. Test with `curl` or Postman before wiring the frontend (Phase 3).

---

## Next: Phase 3

Once the frontend is updated to call these endpoints directly, the `save_state` dispatch loop in `script.js` can be removed, cutting network payload by ~90% per mutation
