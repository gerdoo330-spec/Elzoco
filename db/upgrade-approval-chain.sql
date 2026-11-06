-- به‌روزرسانی یک‌باره: زنجیره‌ی تأیید چندسطحی (سرپرست ← مدیر ← مدیر ارشد ← منابع انسانی)
-- این فایل را فقط یک بار روی دیتابیس اجرا کنید.

ALTER TABLE advance_requests
  ADD COLUMN chain_pcs VARCHAR(500) NOT NULL DEFAULT '' AFTER general_decision,
  ADD COLUMN chain_idx TINYINT NOT NULL DEFAULT 0 AFTER chain_pcs;

ALTER TABLE leave_requests
  ADD COLUMN chain_pcs VARCHAR(500) NOT NULL DEFAULT '' AFTER to_admin_decision,
  ADD COLUMN chain_idx TINYINT NOT NULL DEFAULT 0 AFTER chain_pcs;

ALTER TABLE promotion_requests
  ADD COLUMN chain_pcs VARCHAR(500) NOT NULL DEFAULT '' AFTER to_admin_decision,
  ADD COLUMN chain_idx TINYINT NOT NULL DEFAULT 0 AFTER chain_pcs;

-- درخواست‌های در انتظار فعلی: زنجیره‌ی آن‌ها فعلاً همان مدیر فعلی است
UPDATE advance_requests SET chain_pcs = direct_pc WHERE stage = 'direct' AND direct_pc <> '';
UPDATE leave_requests SET chain_pcs = direct_pc WHERE stage = 'direct' AND direct_pc <> '';
UPDATE promotion_requests SET chain_pcs = direct_pc WHERE stage IN ('direct', 'evaluated') AND direct_pc <> '';
