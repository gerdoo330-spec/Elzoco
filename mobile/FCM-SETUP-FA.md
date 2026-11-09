# راه‌اندازی اعلان فوری موبایل Elzoco

## فایل‌هایی که باید روی هاست آزمایشی به‌روزرسانی شوند

فایل‌های داخل بسته را در مسیر متناظر جایگزین کنید:

- `api/mobile.php` → `public_html/payroll/api/mobile.php`
- `api/announcements.php` → `public_html/payroll/api/announcements.php`
- `src/bootstrap.php` → `public_html/payroll/src/bootstrap.php`
- `src/notify.php` → `public_html/payroll/src/notify.php`
- `src/push.php` → `public_html/payroll/src/push.php`

فایل‌های فعلی را پیش از جایگزینی نگه دارید. این بسته برای هاست آزمایشی است؛ روی سایت اصلی نصب نکنید.

## دیتابیس هاست موجود

در phpMyAdmin دیتابیس مربوط به هاست آزمایشی را انتخاب و فقط یک‌بار فایل زیر را اجرا کنید:

`db/migrations/2026-11-09-mobile-push-tokens.sql`

این migration فقط جدول توکن دستگاه را ایجاد می‌کند. جدول نشست موبایل `mobile_tokens` باید طبق نصب قبلی موجود باشد. اگر وجود ندارد، ابتدا migration قبلی `db/migrations/2026-11-06-mobile-tokens.sql` را بررسی و در صورت نیاز اجرا کنید.

## تنظیم امن Firebase در هاست

1. در Firebase Console پروژه بسازید و Firebase Cloud Messaging را فعال کنید.
2. فایل Service Account JSON را از تنظیمات حساب سرویس دریافت کنید.
3. فایل JSON را خارج از `public_html` قرار دهید؛ برای نمونه در پوشه خصوصی حساب هاست. دسترسی فایل را محدود کنید.
4. متغیر محیطی زیر را برای PHP تنظیم کنید و مقدار آن را مسیر کامل فایل قرار دهید:

`ELZOCO_FIREBASE_SERVICE_ACCOUNT_PATH=/home/ACCOUNT/private/firebase-service-account.json`

نام کاربری هاست را جایگزین کنید. اگر هاست امکان تنظیم متغیر محیطی را نمی‌دهد، مسیر را در آرایه‌ی تنظیمات `config.php` با کلید `firebase_service_account_path` قرار دهید؛ خود فایل JSON همچنان باید خارج از `public_html` بماند. کلید خصوصی را در GitHub، فایل‌های سایت داخل webroot، ZIP عمومی یا پیام چت قرار ندهید.
5. افزونه‌های PHP یعنی cURL و OpenSSL باید فعال باشند.

## تنظیم اپ Android و iOS

- فایل `google-services.json` مربوط به اپ Android را در `android/app/` قرار دهید.
- فایل `GoogleService-Info.plist` مربوط به اپ iOS را در پروژه iOS قرار دهید.
- شناسه بسته‌ی Android و Bundle ID اپ iOS باید دقیقاً با شناسه‌هایی که در Firebase ثبت می‌کنید یکسان باشند.
- برای iOS، کلید APNs را در تنظیمات Cloud Messaging پروژه Firebase ثبت کنید.
- پس از افزودن تنظیمات، اپ را دوباره بسازید و روی هر دو سکو مجوز اعلان را تأیید کنید.

## ترتیب تست

1. یک کاربر آزمایشی با اپ وارد شود تا توکن دستگاه در جدول `mobile_push_tokens` ثبت شود.
2. در پنل مدیر یک اطلاعیه آزمایشی بفرستید.
3. ابتدا وقتی اپ باز است، سپس وقتی در پس‌زمینه است و در پایان وقتی کاملاً بسته است، دریافت اعلان را آزمایش کنید.
4. روی اعلان بزنید؛ اپ باید صندوق اعلان‌های داخلی را باز کند.

تا وقتی فایل تنظیمات Firebase و Service Account روی محیط تست تنظیم نشده باشند، اعلان داخلی سامانه همچنان کار می‌کند اما اعلان فوری گوشی ارسال نخواهد شد.
