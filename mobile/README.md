# Elzoco HRM Mobile

Flutter Android/iOS app. Persian RTL, premium navy/teal/white design. Uses the existing PHP/MySQL backend; no separate mobile database.

## Authentication foundation

- Mobile API endpoint: `/api/mobile.php`
- Employee sign-in reuses the current national-code + full personnel-code verification. The phone number is checked when the employee account uses the zero-national-code fallback, matching the existing website login rule.
- Admin, senior manager and direct manager sign-in reuse the existing `admins` accounts.
- Bearer token is generated from 256 random bits; only its SHA-256 hash is stored in MySQL.
- Token expires after 30 days. Logout revokes it.
- The Flutter app stores the token using `flutter_secure_storage`.
- After login and whenever the app starts, it calls `action=me` to validate the token against the server. Invalid sessions are cleared.
- Manager dashboard scope is calculated by the existing employee-scope authorization function.

## Deploy to the test host

1. Upload `api/mobile.php` to the test site's `api/` directory.
2. Run `db/migrations/2026-11-06-mobile-tokens.sql` exactly once on the test site's database.
3. Confirm the site is served over HTTPS. Do not test real credentials over plain HTTP.
4. Open `https://YOUR-DOMAIN/payroll/api/mobile.php?action=me` in a browser. Expected result without a Bearer token: HTTP 401 and a JSON response similar to:
   `{"ok":false,"message":"نشست معتبر نیست؛ دوباره وارد شوید.","logout":true}`
5. Never share passwords, national codes, or bearer tokens in chat or screenshots.

## Run Flutter on a connected Android device

From `mobile/flutter_app/`:

```bash
flutter pub get
flutter run --dart-define=API_BASE_URL=https://YOUR-DOMAIN/payroll
```

Replace `YOUR-DOMAIN` with the actual test-host domain. Keep `/payroll` only if that is the site's public folder path.

For a release APK:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://YOUR-DOMAIN/payroll
```

## Current scope

This first slice is the authentication foundation and a minimal role-aware landing screen. Employee workflows (payslips, leave, loans/advances, promotion requests and notifications), followed by manager/supervisor workflows and the full admin panel, are still to be implemented and tested.


## Standalone browser test console (no Flutter required)

A single-file browser console is available at the repository root: `mobile-test-console.html`.

1. Upload `mobile-test-console.html` to the same public `payroll` directory that contains `api/` (for example, `public_html/payroll/mobile-test-console.html`).
2. Open `https://YOUR-DOMAIN/payroll/mobile-test-console.html` on a desktop or Android phone.
3. Run **بررسی اتصال سرور**. HTTP 401 with `logout:true` is the expected unauthenticated response and confirms that the endpoint is reachable.
4. Choose employee or admin sign-in and enter authorized test credentials. The page sends credentials only to the same-origin API, does not save credentials or tokens in browser storage, and keeps the Bearer token in page memory only.
5. After a successful login, it calls `action=me` to verify the session. Use logout to revoke the token.

Use HTTPS only. Do not share passwords, national IDs, phone numbers, or tokens in screenshots or chat. This is a diagnostic page, not the production mobile app.
