-- مسیر تأیید هر درخواست را در همان لحظه‌ی ثبت نگه می‌دارد.
-- ردیف‌های قدیمی (NULL) با مسیر پیش‌فرض قبلی یعنی سرپرست ← مدیر ارشد ادامه می‌یابند.
ALTER TABLE leave_requests ADD COLUMN route VARCHAR(40) NULL;
ALTER TABLE advance_requests ADD COLUMN route VARCHAR(40) NULL;
ALTER TABLE promotion_requests ADD COLUMN route VARCHAR(40) NULL;
