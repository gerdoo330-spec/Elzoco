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
