-- به‌روزرسانی یک‌باره: گردش ارتقا (مدیر مستقیم ← مدیر ارشد ← منابع انسانی) و افزایش خودکار حقوق پایه
-- این فایل را فقط یک بار روی دیتابیس اجرا کنید.

ALTER TABLE promotion_requests
  ADD COLUMN to_admin_decision VARCHAR(20) NOT NULL DEFAULT '' AFTER review_level,
  ADD COLUMN senior_decision VARCHAR(20) NOT NULL DEFAULT '' AFTER direct_at,
  ADD COLUMN senior_note VARCHAR(500) NOT NULL DEFAULT '' AFTER senior_decision,
  ADD COLUMN senior_by VARCHAR(120) NOT NULL DEFAULT '' AFTER senior_note,
  ADD COLUMN senior_at DATETIME NULL AFTER senior_by;

-- درخواست‌های قدیمیِ در انتظار منابع انسانی: تصمیمی که ارجاع داده شده همان تصمیم مدیر مستقیم است
UPDATE promotion_requests SET to_admin_decision = direct_decision
 WHERE stage = 'admin' AND to_admin_decision = '';
