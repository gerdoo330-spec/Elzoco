-- به‌روزرسانی برای نصب‌های قبلی (یک بار اجرا شود)
-- وام، ارتقای گروه شغلی و قوانین قابل ویرایش مساعده/وام

ALTER TABLE advance_requests
  ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'advance' AFTER tracking;

CREATE TABLE IF NOT EXISTS promotion_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tracking VARCHAR(20) NOT NULL UNIQUE,
  employee_id INT NOT NULL,
  personnel_code VARCHAR(30) NOT NULL,
  national_code VARCHAR(10) NOT NULL,
  name VARCHAR(190) NOT NULL,
  post VARCHAR(190) NOT NULL DEFAULT '',
  job_requested VARCHAR(190) NOT NULL,
  shift VARCHAR(30) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  stage ENUM('direct','general','final','rejected') NOT NULL,
  direct_pc VARCHAR(30) NOT NULL DEFAULT '',
  decided_by VARCHAR(120) NULL,
  decided_at DATETIME NULL,
  decision_note VARCHAR(500) NOT NULL DEFAULT '',
  created_jdate VARCHAR(12) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_pr_emp (employee_id)
) DEFAULT CHARSET=utf8mb4;

-- قوانین پیش‌فرض (در صورت نبودن در جدول تنظیمات)
INSERT IGNORE INTO settings (k, v) VALUES
  ('adv_wait_months', '1'),
  ('loan_wait_months', '4'),
  ('loan_ratio_pct', '33'),
  ('loan_round', '10000000');
