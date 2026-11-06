-- ساخت خودکار ساختار پرونده‌ی پرسنلی برای ثبت تأییدهای نهایی درخواست‌ها
CREATE TABLE IF NOT EXISTS employee_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  source_type VARCHAR(20) NOT NULL,
  source_id INT NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NOT NULL,
  actor_name VARCHAR(120) NOT NULL DEFAULT '',
  event_jdate VARCHAR(12) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_employee_history_source (source_type, source_id, event_type),
  INDEX idx_employee_history_employee (employee_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
