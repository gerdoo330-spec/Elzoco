-- به‌روزرسانی یک‌باره: گردش یکسان برای مساعده، وام و مرخصی
-- مدیر مستقیم ← مدیر ارشد ← منابع انسانی (رد در هر مرحله به منابع انسانی می‌رود)
-- این فایل را فقط یک بار روی دیتابیس اجرا کنید.

ALTER TABLE advance_requests
  ADD COLUMN to_admin_decision VARCHAR(20) NOT NULL DEFAULT '' AFTER direct_decision,
  ADD COLUMN general_decision VARCHAR(190) NOT NULL DEFAULT '' AFTER to_admin_decision;

ALTER TABLE leave_requests
  MODIFY stage ENUM('direct','general','final','rejected','admin') NOT NULL,
  ADD COLUMN to_admin_decision VARCHAR(20) NOT NULL DEFAULT '' AFTER general_decision;

-- درخواست‌های قدیمیِ در انتظار منابع انسانی: تصمیمی که ارجاع داده شده همان تصمیم مرحله‌ی قبل است
UPDATE advance_requests SET to_admin_decision = 'approve' WHERE stage = 'admin' AND to_admin_decision = '';
UPDATE leave_requests SET to_admin_decision = 'approve' WHERE stage = 'admin' AND to_admin_decision = '';
