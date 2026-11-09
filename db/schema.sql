CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('admin','senior','manager') NOT NULL DEFAULT 'manager',
  personnel_code VARCHAR(30) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  year SMALLINT NOT NULL,
  month TINYINT NOT NULL,
  file_name VARCHAR(255) NULL,
  uploaded_by VARCHAR(120) NULL,
  uploaded_at DATETIME NOT NULL,
  report JSON NULL,
  UNIQUE KEY uq_period (year, month)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  emp_key VARCHAR(120) NOT NULL UNIQUE,
  personnel_code VARCHAR(30) NOT NULL DEFAULT '',
  personnel_ok TINYINT(1) NOT NULL DEFAULT 0,
  national_code VARCHAR(10) NOT NULL DEFAULT '',
  name VARCHAR(190) NOT NULL,
  name_key VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL DEFAULT '',
  post VARCHAR(190) NOT NULL DEFAULT '',
  promo_post VARCHAR(190) NOT NULL DEFAULT '',
  start_code VARCHAR(12) NOT NULL DEFAULT '',
  hire_date VARCHAR(12) NOT NULL DEFAULT '',
  sheba VARCHAR(30) NOT NULL DEFAULT '',
  salary BIGINT NOT NULL DEFAULT 0,
  promo_salary_add BIGINT NOT NULL DEFAULT 0,
  color VARCHAR(12) NOT NULL DEFAULT '',
  inactive TINYINT(1) NOT NULL DEFAULT 0,
  in_latest TINYINT(1) NOT NULL DEFAULT 1,
  manager_pc VARCHAR(30) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL,
  INDEX idx_nc (national_code),
  INDEX idx_pc (personnel_code)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payslips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_id INT NOT NULL,
  employee_id INT NOT NULL,
  personnel_code VARCHAR(30) NOT NULL DEFAULT '',
  has_work TINYINT(1) NOT NULL DEFAULT 0,
  net DECIMAL(18,0) NOT NULL DEFAULT 0,
  data JSON NOT NULL,
  UNIQUE KEY uq_slip (period_id, employee_id),
  FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS advance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tracking VARCHAR(20) NOT NULL UNIQUE,
  kind VARCHAR(10) NOT NULL DEFAULT 'advance',
  employee_id INT NOT NULL,
  personnel_code VARCHAR(30) NOT NULL,
  national_code VARCHAR(10) NOT NULL,
  name VARCHAR(190) NOT NULL,
  jdate VARCHAR(12) NOT NULL,
  sheba VARCHAR(30) NOT NULL,
  amount BIGINT NOT NULL,
  salary BIGINT NOT NULL,
  window_start VARCHAR(12) NOT NULL DEFAULT '',
  window_end VARCHAR(12) NOT NULL DEFAULT '',
  status VARCHAR(30) NOT NULL DEFAULT 'در انتظار بررسی',
  approved_amount BIGINT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  decided_by VARCHAR(120) NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  stage VARCHAR(20) NOT NULL DEFAULT 'final',
  direct_pc VARCHAR(30) NOT NULL DEFAULT '',
  direct_decision VARCHAR(190) NOT NULL DEFAULT '',
  to_admin_decision VARCHAR(20) NOT NULL DEFAULT '',
  general_decision VARCHAR(190) NOT NULL DEFAULT '',
  chain_pcs VARCHAR(500) NOT NULL DEFAULT '',
  chain_idx TINYINT NOT NULL DEFAULT 0,
  route VARCHAR(40) NULL,
  INDEX idx_adv_emp (employee_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tracking VARCHAR(20) NOT NULL UNIQUE,
  employee_id INT NOT NULL,
  personnel_code VARCHAR(30) NOT NULL,
  national_code VARCHAR(10) NOT NULL,
  name VARCHAR(190) NOT NULL,
  type_key VARCHAR(20) NOT NULL,
  type_label VARCHAR(30) NOT NULL,
  from_date VARCHAR(12) NOT NULL,
  to_date VARCHAR(12) NOT NULL,
  start_time VARCHAR(5) NOT NULL DEFAULT '',
  end_time VARCHAR(5) NOT NULL DEFAULT '',
  amount DECIMAL(8,2) NOT NULL DEFAULT 0,
  reason VARCHAR(500) NOT NULL,
  stage ENUM('direct','general','final','rejected','admin') NOT NULL,
  direct_pc VARCHAR(30) NOT NULL DEFAULT '',
  general_pc VARCHAR(30) NOT NULL DEFAULT '',
  reject_reason VARCHAR(500) NOT NULL DEFAULT '',
  direct_decision VARCHAR(190) NOT NULL DEFAULT '',
  general_decision VARCHAR(190) NOT NULL DEFAULT '',
  to_admin_decision VARCHAR(20) NOT NULL DEFAULT '',
  chain_pcs VARCHAR(500) NOT NULL DEFAULT '',
  chain_idx TINYINT NOT NULL DEFAULT 0,
  route VARCHAR(40) NULL,
  created_jdate VARCHAR(12) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_lv_emp (employee_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promotion_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tracking VARCHAR(20) NOT NULL UNIQUE,
  employee_id INT NOT NULL,
  personnel_code VARCHAR(30) NOT NULL,
  national_code VARCHAR(10) NOT NULL,
  name VARCHAR(190) NOT NULL,
  post VARCHAR(190) NOT NULL DEFAULT '',
  job_requested VARCHAR(190) NOT NULL,
  current_shift VARCHAR(30) NOT NULL DEFAULT '',
  shift VARCHAR(30) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  stage ENUM('direct','general','admin','evaluated','final','rejected') NOT NULL,
  review_level VARCHAR(10) NOT NULL DEFAULT 'direct',
  direct_pc VARCHAR(30) NOT NULL DEFAULT '',
  direct_decision VARCHAR(20) NOT NULL DEFAULT '',
  direct_note VARCHAR(500) NOT NULL DEFAULT '',
  direct_by VARCHAR(120) NOT NULL DEFAULT '',
  direct_at DATETIME NULL,
  senior_decision VARCHAR(20) NOT NULL DEFAULT '',
  senior_note VARCHAR(500) NOT NULL DEFAULT '',
  senior_by VARCHAR(120) NOT NULL DEFAULT '',
  senior_at DATETIME NULL,
  to_admin_decision VARCHAR(20) NOT NULL DEFAULT '',
  chain_pcs VARCHAR(500) NOT NULL DEFAULT '',
  chain_idx TINYINT NOT NULL DEFAULT 0,
  route VARCHAR(40) NULL,
  raise_amount BIGINT NULL,
  eval_score DECIMAL(5,2) NULL,
  eval_note VARCHAR(500) NOT NULL DEFAULT '',
  eval_by VARCHAR(120) NOT NULL DEFAULT '',
  eval_at DATETIME NULL,
  decided_by VARCHAR(120) NULL,
  decided_at DATETIME NULL,
  decision_note VARCHAR(500) NOT NULL DEFAULT '',
  created_jdate VARCHAR(12) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_pr_emp (employee_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mobile_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('employee','admin') NOT NULL,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  INDEX idx_mobile_user(user_type,user_id),
  INDEX idx_mobile_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mobile_push_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('employee','admin') NOT NULL,
  user_id INT NOT NULL,
  token_value VARCHAR(512) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  platform ENUM('android','ios') NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  INDEX idx_push_user(user_type,user_id,revoked_at),
  INDEX idx_push_platform(platform,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
