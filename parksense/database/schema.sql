-- ParkSense: run once in phpMyAdmin (Import) or the mysql client.
CREATE DATABASE IF NOT EXISTS parksense CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE parksense;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  full_name     VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,                 -- bcrypt hash, never the password
  role          ENUM('admin','guard') NOT NULL DEFAULT 'guard',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,       -- set to 0 to disable an account instantly
  last_login_at DATETIME     NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50) NOT NULL,
  ip_address   VARCHAR(45) NOT NULL,
  success      TINYINT(1)  NOT NULL,
  attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (username, attempted_at),
  INDEX idx_ip   (ip_address, attempted_at)
) ENGINE=InnoDB;

-- Least-privilege account for the website (change the password, then match it in includes/config.php):
-- CREATE USER 'parksense_app'@'localhost' IDENTIFIED BY 'a-long-random-password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON parksense.* TO 'parksense_app'@'localhost';
