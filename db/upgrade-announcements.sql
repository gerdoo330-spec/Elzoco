-- مرکز اطلاعیه‌ها و اعلان‌های دستی
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
