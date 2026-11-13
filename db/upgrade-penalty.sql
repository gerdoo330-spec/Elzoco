-- جریمه‌ی پرسنلی: نوع جریمه، فهرست مبلغ‌ها (جدا از نوع) و ثبت جریمه‌ها
-- (در صورت اجرا نشدن این فایل، سامانه جداول را هنگام نخستین استفاده خودش می‌سازد)

CREATE TABLE IF NOT EXISTS penalty_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS penalty_amounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount BIGINT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_penalty_amount (amount)
) DEFAULT CHARSET=utf8mb4;

-- جدول penalties همان ساختار نسخه‌ی قبل را دارد؛ type_title و amount در هر جریمه به‌عنوان
-- رونوشت ثبت می‌شوند تا تغییر تنظیمات روی جریمه‌های قبلی اثر نگذارد.
