-- به‌روزرسانی یک‌باره برای نصب‌های قبلی (یک بار اجرا شود)
-- نقش مدیر ارشد و زنجیره‌ی تأیید مساعده

ALTER TABLE admins MODIFY role ENUM('admin','senior','manager') NOT NULL DEFAULT 'manager';

ALTER TABLE advance_requests
  ADD COLUMN stage VARCHAR(20) NOT NULL DEFAULT 'final' AFTER created_at,
  ADD COLUMN direct_pc VARCHAR(30) NOT NULL DEFAULT '' AFTER stage,
  ADD COLUMN direct_decision VARCHAR(190) NOT NULL DEFAULT '' AFTER direct_pc;

-- درخواست‌های در انتظار فعلی مساعده، بر اساس مدیر مستقیم فعلی کارمند در مرحله‌ی درست قرار می‌گیرند
UPDATE advance_requests a JOIN employees e ON e.id = a.employee_id
  SET a.direct_pc = e.manager_pc,
      a.stage = IF(e.manager_pc <> '', 'direct', 'general')
  WHERE a.status = 'در انتظار بررسی';

-- مرخصی‌هایی که در مرحله‌ی «بررسی ادمین» مانده‌اند به مدیر ارشد منتقل می‌شوند
UPDATE leave_requests SET stage = 'general' WHERE stage = 'admin';
