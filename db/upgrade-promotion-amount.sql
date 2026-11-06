-- به‌روزرسانی یک‌باره: ثبت مبلغ افزایش حقوق پیشنهادی مدیر مستقیم هنگام تأیید پس از ارزیابی
ALTER TABLE promotion_requests ADD COLUMN raise_amount BIGINT NULL AFTER direct_at;
