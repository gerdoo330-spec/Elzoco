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
