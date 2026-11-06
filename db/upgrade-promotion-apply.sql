-- به‌روزرسانی یک‌باره: نگه‌داری افزایش حقوق و شغل جدید پس از ارتقا (قابل تنظیم در پنل مدیر)
-- این فایل را فقط یک بار روی دیتابیس اجرا کنید.

ALTER TABLE employees
  ADD COLUMN promo_salary_add BIGINT NOT NULL DEFAULT 0 AFTER salary,
  ADD COLUMN promo_post VARCHAR(190) NOT NULL DEFAULT '' AFTER post;

INSERT IGNORE INTO settings (k, v) VALUES ('promo_apply_mode', 'carry');
