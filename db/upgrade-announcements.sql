-- اجرای یک‌باره برای فعال‌سازی مرکز اطلاعیه‌ها
-- جدول notifications در نصب‌های فعلی سامانه وجود دارد؛ این دستور نصب‌های قدیمی را نیز پوشش می‌دهد.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  kind VARCHAR(24) NOT NULL DEFAULT 'general',
  audience VARCHAR(24) NOT NULL DEFAULT 'all',
  audience_value VARCHAR(190) NOT NULL DEFAULT '',
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  recipient_count INT NOT NULL DEFAULT 0,
  INDEX idx_ann_active (active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
