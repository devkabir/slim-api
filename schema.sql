-- ==============================================================================
-- Schema Definition
-- ==============================================================================
CREATE DATABASE IF NOT EXISTS `slim_todo_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `slim_todo_db`;

CREATE TABLE IF NOT EXISTS `todos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `completed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- Least-Privileged Database User Setup
-- Run as MySQL root/admin during server provisioning:
-- ==============================================================================
-- CREATE USER IF NOT EXISTS 'todo_app'@'127.0.0.1' IDENTIFIED BY 'ChangeThisToStrongPassword123!';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `slim_todo_db`.* TO 'todo_app'@'127.0.0.1';
-- FLUSH PRIVILEGES;
