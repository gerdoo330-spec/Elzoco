# Elzoco HRM Mobile

Flutter Android/iOS app. Persian RTL, premium navy/teal/white design. Uses the existing PHP/MySQL backend; no separate mobile database.

## First implementation slice
- Mobile API: `/api/mobile.php`
- Employee sign-in reuses current national/personnel/phone verification.
- Admin, senior and manager sign-in reuses existing admin accounts.
- Bearer token is random 256-bit; only SHA-256 hash is stored.
- Token expires in 30 days and logout revokes it.
- Manager dashboard counts reuse the existing employee-scope function.

## Test host
1. Upload `api/mobile.php`.
2. Run `db/migrations/2026-11-06-mobile-tokens.sql` once on the test database.
3. Enforce HTTPS.
4. GET `/api/mobile.php?action=me` without token should return HTTP 401 JSON.
5. Do not share credentials or access tokens.

Flutter project: `flutter_app/`. This first slice is the authentication foundation; role-specific workflows are still being implemented.
