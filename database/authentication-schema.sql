-- ============================================================================
-- BLUE CLEANING SERVICES - AUTHENTICATION SYSTEM TABLES
-- Version: 1.0.0
-- Created: 07/08/2025
-- Description: Additional tables for authentication and security systems
-- ============================================================================

-- Set charset and timezone for Australian operations
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+10:00'; -- Australian Eastern Standard Time

-- ============================================================================
-- CUSTOMERS TABLE - Customer accounts
-- ============================================================================
CREATE TABLE IF NOT EXISTS `customers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Unique customer identifier',
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    
    -- Contact Information (Australian format)
    `mobile` VARCHAR(15) NULL COMMENT 'Australian mobile: +61 4XX XXX XXX',
    `phone` VARCHAR(15) NULL COMMENT 'Australian landline: +61 X XXXX XXXX',
    
    -- Address (Australian format)
    `street_address` TEXT NULL,
    `suburb` VARCHAR(100) NULL COMMENT 'Australian term for city/locality',
    `state` ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT') NULL,
    `postcode` VARCHAR(4) NULL COMMENT 'Australian 4-digit postcode',
    `country` CHAR(3) NOT NULL DEFAULT 'AUS',
    
    -- System Fields
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `status` ENUM('active', 'inactive', 'suspended', 'pending_verification') NOT NULL DEFAULT 'pending_verification',
    `preferences` JSON NULL COMMENT 'Customer preferences and settings',
    `timezone` VARCHAR(50) NOT NULL DEFAULT 'Australia/Sydney',
    
    -- Timestamps
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_customers_email` (`email`),
    UNIQUE KEY `uk_customers_code` (`customer_code`),
    KEY `idx_customers_status` (`status`),
    KEY `idx_customers_suburb_state` (`suburb`, `state`),
    KEY `idx_customers_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer accounts and profiles';

-- ============================================================================
-- PROFESSIONALS TABLE - Professional/Service provider accounts
-- ============================================================================
CREATE TABLE IF NOT EXISTS `professionals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `professional_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Unique professional identifier',
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    
    -- Contact Information (Australian format)
    `mobile` VARCHAR(15) NULL COMMENT 'Australian mobile: +61 4XX XXX XXX',
    `phone` VARCHAR(15) NULL COMMENT 'Australian landline: +61 X XXXX XXXX',
    
    -- Business Information
    `business_name` VARCHAR(200) NULL,
    `abn` VARCHAR(11) NULL COMMENT 'Australian Business Number',
    `acn` VARCHAR(9) NULL COMMENT 'Australian Company Number',
    
    -- Address (Australian format)
    `street_address` TEXT NULL,
    `suburb` VARCHAR(100) NULL COMMENT 'Australian term for city/locality',
    `state` ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT') NULL,
    `postcode` VARCHAR(4) NULL COMMENT 'Australian 4-digit postcode',
    `country` CHAR(3) NOT NULL DEFAULT 'AUS',
    
    -- Professional Details
    `services` JSON NULL COMMENT 'Services offered',
    `service_areas` JSON NULL COMMENT 'Areas of service coverage',
    `qualifications` JSON NULL COMMENT 'Professional qualifications',
    `experience_years` INT UNSIGNED NULL,
    
    -- System Fields
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `status` ENUM('active', 'inactive', 'suspended', 'pending_verification', 'training') NOT NULL DEFAULT 'pending_verification',
    `verification_level` ENUM('none', 'basic', 'verified', 'premium') NOT NULL DEFAULT 'none',
    `preferences` JSON NULL COMMENT 'Professional preferences and settings',
    `timezone` VARCHAR(50) NOT NULL DEFAULT 'Australia/Sydney',
    
    -- Ratings and Reviews
    `rating_average` DECIMAL(3,2) NULL DEFAULT NULL,
    `rating_count` INT UNSIGNED DEFAULT 0,
    
    -- Timestamps
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_professionals_email` (`email`),
    UNIQUE KEY `uk_professionals_code` (`professional_code`),
    KEY `idx_professionals_status` (`status`),
    KEY `idx_professionals_abn` (`abn`),
    KEY `idx_professionals_suburb_state` (`suburb`, `state`),
    KEY `idx_professionals_rating` (`rating_average`),
    KEY `idx_professionals_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Professional service provider accounts';

-- ============================================================================
-- ADMIN USERS TABLE - Administrative accounts
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Unique admin identifier',
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    
    -- Contact Information
    `mobile` VARCHAR(15) NULL,
    `phone` VARCHAR(15) NULL,
    
    -- Admin Details
    `role` ENUM('super_admin', 'admin', 'moderator', 'support') NOT NULL DEFAULT 'support',
    `permissions` JSON NULL COMMENT 'Admin permissions and access levels',
    `department` VARCHAR(100) NULL,
    `employee_id` VARCHAR(20) NULL,
    
    -- System Fields
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `two_factor_enabled` BOOLEAN NOT NULL DEFAULT FALSE,
    `last_password_change` TIMESTAMP NULL DEFAULT NULL,
    `timezone` VARCHAR(50) NOT NULL DEFAULT 'Australia/Sydney',
    
    -- Security
    `failed_login_attempts` INT UNSIGNED DEFAULT 0,
    `locked_until` TIMESTAMP NULL DEFAULT NULL,
    
    -- Timestamps
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admin_email` (`email`),
    UNIQUE KEY `uk_admin_code` (`admin_code`),
    KEY `idx_admin_role` (`role`),
    KEY `idx_admin_status` (`status`),
    KEY `idx_admin_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Administrative user accounts';

-- ============================================================================
-- PASSWORD RESET TOKENS TABLE - Password reset functionality
-- ============================================================================
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `user_type` ENUM('customer', 'professional', 'admin') NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` TIMESTAMP NOT NULL,
    `used_at` TIMESTAMP NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_reset_token` (`token`),
    KEY `idx_reset_user` (`user_id`, `user_type`),
    KEY `idx_reset_expires` (`expires_at`),
    KEY `idx_reset_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Password reset tokens for secure password recovery';

-- ============================================================================
-- USER SESSIONS TABLE - Active user sessions tracking
-- ============================================================================
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(128) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `user_type` ENUM('customer', 'professional', 'admin') NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NULL,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `invalidated_at` TIMESTAMP NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session_id` (`session_id`),
    KEY `idx_session_user` (`user_id`, `user_type`),
    KEY `idx_session_activity` (`last_activity`),
    KEY `idx_session_invalidated` (`invalidated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User session tracking and management';

-- ============================================================================
-- SECURITY AUDIT LOG TABLE - Security events logging
-- ============================================================================
CREATE TABLE IF NOT EXISTS `security_audit_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `user_type` ENUM('customer', 'professional', 'admin', 'system') NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `severity` ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL DEFAULT 'MEDIUM',
    `description` TEXT NULL,
    `metadata` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `request_uri` VARCHAR(500) NULL,
    `session_id` VARCHAR(128) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    KEY `idx_audit_user` (`user_id`, `user_type`),
    KEY `idx_audit_event` (`event_type`),
    KEY `idx_audit_severity` (`severity`),
    KEY `idx_audit_created` (`created_at`),
    KEY `idx_audit_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security events and audit trail';

-- ============================================================================
-- TWO FACTOR AUTHENTICATION TABLE - 2FA tokens and settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS `two_factor_auth` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `user_type` ENUM('customer', 'professional', 'admin') NOT NULL,
    `secret_key` VARCHAR(32) NOT NULL,
    `backup_codes` JSON NULL COMMENT 'Encrypted backup recovery codes',
    `enabled` BOOLEAN NOT NULL DEFAULT FALSE,
    `method` ENUM('totp', 'sms', 'email') NOT NULL DEFAULT 'totp',
    `phone_number` VARCHAR(15) NULL COMMENT 'For SMS 2FA',
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_2fa_user` (`user_id`, `user_type`),
    KEY `idx_2fa_enabled` (`enabled`),
    KEY `idx_2fa_method` (`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Two-factor authentication settings';

-- ============================================================================
-- LOGIN ATTEMPTS TABLE - Failed login tracking for security
-- ============================================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `user_type` ENUM('customer', 'professional', 'admin') NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `success` BOOLEAN NOT NULL DEFAULT FALSE,
    `failure_reason` ENUM('invalid_credentials', 'account_locked', 'account_inactive', 'rate_limited', '2fa_required', '2fa_failed') NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    KEY `idx_login_email` (`email`),
    KEY `idx_login_ip` (`ip_address`),
    KEY `idx_login_attempted` (`attempted_at`),
    KEY `idx_login_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login attempt tracking for security monitoring';

-- ============================================================================
-- INDEXES AND TRIGGERS
-- ============================================================================

-- Index for efficient cleanup of expired tokens
CREATE INDEX idx_password_reset_cleanup ON password_reset_tokens (expires_at, used_at);

-- Index for session cleanup
CREATE INDEX idx_session_cleanup ON user_sessions (last_activity, invalidated_at);

-- ============================================================================
-- TRIGGERS FOR AUDIT LOGGING
-- ============================================================================

DELIMITER ;;

-- Trigger to log password changes
CREATE TRIGGER tr_customers_password_change
AFTER UPDATE ON customers
FOR EACH ROW
BEGIN
    IF OLD.password != NEW.password THEN
        INSERT INTO security_audit_log (user_id, user_type, event_type, severity, description, created_at)
        VALUES (NEW.id, 'customer', 'password_changed', 'MEDIUM', 'Customer password was changed', NOW());
    END IF;
END;;

CREATE TRIGGER tr_professionals_password_change
AFTER UPDATE ON professionals
FOR EACH ROW
BEGIN
    IF OLD.password != NEW.password THEN
        INSERT INTO security_audit_log (user_id, user_type, event_type, severity, description, created_at)
        VALUES (NEW.id, 'professional', 'password_changed', 'MEDIUM', 'Professional password was changed', NOW());
    END IF;
END;;

CREATE TRIGGER tr_admin_password_change
AFTER UPDATE ON admin_users
FOR EACH ROW
BEGIN
    IF OLD.password != NEW.password THEN
        INSERT INTO security_audit_log (user_id, user_type, event_type, severity, description, created_at)
        VALUES (NEW.id, 'admin', 'password_changed', 'HIGH', 'Admin password was changed', NOW());
        
        -- Update last password change timestamp
        UPDATE admin_users SET last_password_change = NOW() WHERE id = NEW.id;
    END IF;
END;;

DELIMITER ;

-- ============================================================================
-- INITIAL DATA
-- ============================================================================

-- Create default admin user (password should be changed immediately)
INSERT IGNORE INTO admin_users (
    admin_code, email, password, name, first_name, last_name, role, status, created_at
) VALUES (
    'ADMIN_001',
    'admin@bluecleaningservices.com.au',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- 'password' - CHANGE THIS!
    'System Administrator',
    'System',
    'Administrator',
    'super_admin',
    'active',
    NOW()
);

-- ============================================================================
-- CLEANUP PROCEDURES
-- ============================================================================

DELIMITER ;;

-- Procedure to clean expired password reset tokens
CREATE PROCEDURE CleanExpiredPasswordTokens()
BEGIN
    DELETE FROM password_reset_tokens 
    WHERE expires_at < NOW() 
       OR (used_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR));
END;;

-- Procedure to clean old login attempts (keep 30 days)
CREATE PROCEDURE CleanOldLoginAttempts()
BEGIN
    DELETE FROM login_attempts 
    WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END;;

-- Procedure to clean inactive sessions (keep 7 days)
CREATE PROCEDURE CleanInactiveSessions()
BEGIN
    DELETE FROM user_sessions 
    WHERE (invalidated_at IS NOT NULL AND invalidated_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
       OR last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);
END;;

DELIMITER ;

-- ============================================================================
-- EVENTS FOR AUTOMATIC CLEANUP (Optional - enable if needed)
-- ============================================================================

-- Uncomment these if you want automatic cleanup
-- SET GLOBAL event_scheduler = ON;

-- CREATE EVENT IF NOT EXISTS ev_cleanup_password_tokens
-- ON SCHEDULE EVERY 1 HOUR
-- DO CALL CleanExpiredPasswordTokens();

-- CREATE EVENT IF NOT EXISTS ev_cleanup_login_attempts
-- ON SCHEDULE EVERY 1 DAY
-- DO CALL CleanOldLoginAttempts();

-- CREATE EVENT IF NOT EXISTS ev_cleanup_sessions
-- ON SCHEDULE EVERY 2 HOUR
-- DO CALL CleanInactiveSessions();

-- ============================================================================
-- END OF AUTHENTICATION SCHEMA
-- ============================================================================
