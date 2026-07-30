# SpendWise — Capacitor app

Static frontend (`www/`) wrapped with Capacitor, talking to your existing
PHP/MySQL API on InfinityFree over `fetch()`.

## 1. Deploy the server-side changes first

The API on InfinityFree needs 4 small changes (already applied in your cloned
`spendwize` repo — just push them):

- **`functions.php`** — session cookie is now `SameSite=None` (required for a
  cross-origin WebView to send it at all) and there's a new shared
  `sw_send_cors_headers()` allow-list helper, plus a new `bootstrap` action
  the static frontend uses to restore an existing session on launch.
- **`ai.php`, `receipt.php`** — now send CORS headers too (previously only
  `api/*.php` did).
- **`api/_bootstrap.php`** — now reuses the same shared CORS helper.
- **`config.example.php`** — documents the new `app_origins` config key.

On your server, edit `config.production.php` (not committed — upload via
FTP, same as before) and set:

```php
'app_origins' => [
    'https://spendwize.infinityfreeapp.com', // your website
    'https://localhost',                     // Capacitor Android (androidScheme: https)
    'capacitor://localhost',                 // Capacitor iOS
],
```

InfinityFree's free plan supports HTTPS (via their AutoSSL / Cloudflare) —
`SameSite=None` cookies require it, so confirm your site loads over `https://`
before testing the app, not just `http://`.

## 2. Point the app at your API

`www/index.html` has:

```html
window.__SPENDWISE_API_BASE__ = "https://spendwize.infinityfreeapp.com/";
```

Change this if your domain differs. Everything else in `www/script.js`
reads from `API_BASE` automatically.

## 3. Install Capacitor and add the Android platform

```bash
npm install
npx cap add android
npx cap sync
```

(For iOS: `npm install @capacitor/ios --save` then `npx cap add ios`.)

## 4. Open and run

```bash
npx cap open android
```

This opens Android Studio — hit Run. `capacitor.config.json` sets
`server.androidScheme: "https"` so the WebView's origin is `https://localhost`,
matching the `app_origins` entry above.

## Notes / gotchas

- **Cookies require HTTPS end-to-end.** If InfinityFree's SSL isn't active on
  your exact domain, `SameSite=None; Secure` cookies won't be set and login
  won't persist. Test in a real mobile browser at your `https://` URL first.
- **CORS is origin-exact.** `capacitor://localhost` (iOS/older Android) is a
  different origin from `https://localhost` (current Android default) — both
  are in `app_origins` above so either build works.
- If you change `appId` in `capacitor.config.json` after `cap add android`,
  re-run `npx cap sync`.
- `www/index.html` calls a new `bootstrap` action on launch to restore an
  already-logged-in session (the old `index.php` did this server-side by
  embedding it in the page, which a static `index.html` can't do).
