-- به‌روزرسانی یک‌باره: گردش جدید ارتقا (مدیر مستقیم ← ادمین ← ارزیابی ← مدیر مستقیم) و اعلان‌های پاپ‌آپ
-- این فایل را فقط یک بار روی دیتابیس اجرا کنید.

ALTER TABLE promotion_requests
  MODIFY stage ENUM('direct','general','admin','evaluated','final','rejected') NOT NULL,
  ADD COLUMN review_level VARCHAR(10) NOT NULL DEFAULT 'direct' AFTER stage,
  ADD COLUMN direct_decision VARCHAR(20) NOT NULL DEFAULT '' AFTER direct_pc,
  ADD COLUMN direct_note VARCHAR(500) NOT NULL DEFAULT '' AFTER direct_decision,
  ADD COLUMN direct_by VARCHAR(120) NOT NULL DEFAULT '' AFTER direct_note,
  ADD COLUMN direct_at DATETIME NULL AFTER direct_by,
  ADD COLUMN eval_score DECIMAL(5,2) NULL AFTER direct_at,
  ADD COLUMN eval_note VARCHAR(500) NOT NULL DEFAULT '' AFTER eval_score,
  ADD COLUMN eval_by VARCHAR(120) NOT NULL DEFAULT '' AFTER eval_note,
  ADD COLUMN eval_at DATETIME NULL AFTER eval_by;

-- درخواست‌های قدیمیِ در انتظار مدیر ارشد، سطح بررسی «general» بگیرند
UPDATE promotion_requests SET review_level = 'general' WHERE stage = 'general';

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(30) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  tone VARCHAR(10) NOT NULL DEFAULT 'info',
  title VARCHAR(190) NOT NULL,
  body VARCHAR(500) NOT NULL DEFAULT '',
  tab VARCHAR(30) NOT NULL DEFAULT '',
  shown TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_nt_rcp (recipient, shown)
) DEFAULT CHARSET=utf8mb4;
