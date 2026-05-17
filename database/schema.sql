-- Initial MariaDB schema for PoW Clicker.
-- Import into an empty database:
--   mariadb pow_clicker < database/schema.sql
--
-- The cleanup events require the MariaDB event scheduler to be enabled.

CREATE TABLE IF NOT EXISTS `balances` (
  `address` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `balance` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE IF NOT EXISTS `auth_nonces` (
  `nonce` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `public_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`nonce`),
  KEY `idx_auth_nonces_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE IF NOT EXISTS `pow_tasks` (
  `public_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `challenge` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`public_key`),
  KEY `idx_pow_tasks_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

DELIMITER //

CREATE EVENT IF NOT EXISTS `cleanup_expired_auth_nonces`
ON SCHEDULE EVERY 2 MINUTE
DO
  DELETE FROM `auth_nonces`
  WHERE `expires_at` < UTC_TIMESTAMP()//

CREATE EVENT IF NOT EXISTS `cleanup_expired_pow_tasks`
ON SCHEDULE EVERY 2 MINUTE
DO
  DELETE FROM `pow_tasks`
  WHERE `expires_at` < UTC_TIMESTAMP()//

DELIMITER ;
