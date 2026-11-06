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
