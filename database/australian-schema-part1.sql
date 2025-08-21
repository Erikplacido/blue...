-- ============================================================================
-- BLUE CLEANING SERVICES - AUSTRALIAN DATABASE SCHEMA
-- Version: 2.0.0
-- Created: 07/08/2025
-- Description: Complete database schema with Australian regional compliance
-- ============================================================================

-- Set charset and timezone for Australian operations
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+10:00'; -- Australian Eastern Standard Time

-- ============================================================================
-- USERS TABLE - Main user authentication and profile
-- ============================================================================
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Unique user identifier',
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `user_type` ENUM('customer', 'professional', 'admin', 'candidate') NOT NULL DEFAULT 'customer',
    
    -- Personal Information
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `date_of_birth` DATE NULL,
    `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,
    
    -- Contact Information (Australian format)
    `mobile` VARCHAR(15) NULL COMMENT 'Australian mobile: +61 4XX XXX XXX',
    `landline` VARCHAR(15) NULL COMMENT 'Australian landline: +61 X XXXX XXXX',
    
    -- Address (Australian format)
    `street_number` VARCHAR(10) NULL,
    `street_name` VARCHAR(100) NULL,
    `street_type` ENUM('ST','RD','AVE','PL','CT','DR','LN','WAY','CL','TCE','HWY','PWAY','BLVD','CIR','CRES','GDNS','GRV','MEWS','PARADE','ROW','SQUARE','WALK') NULL,
    `unit_number` VARCHAR(10) NULL COMMENT 'Unit/Apartment number',
    `suburb` VARCHAR(100) NULL COMMENT 'Australian term for city/locality',
    `state` ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT') NULL,
    `postcode` VARCHAR(4) NULL COMMENT 'Australian 4-digit postcode',
    `country` CHAR(3) NOT NULL DEFAULT 'AUS',
    
    -- Australian Business Numbers
    `abn` VARCHAR(11) NULL COMMENT 'Australian Business Number (for professionals)',
    `acn` VARCHAR(9) NULL COMMENT 'Australian Company Number',
    `tfn` VARCHAR(9) NULL COMMENT 'Tax File Number (encrypted)',
    
    -- System Fields
    `status` ENUM('active', 'inactive', 'suspended', 'pending_verification') NOT NULL DEFAULT 'pending_verification',
    `preferences` JSON NULL COMMENT 'User preferences and settings',
    `profile_image` VARCHAR(500) NULL,
    `timezone` VARCHAR(50) NOT NULL DEFAULT 'Australia/Sydney',
    `language` VARCHAR(5) NOT NULL DEFAULT 'en_AU',
    
    -- Security
    `two_factor_enabled` BOOLEAN NOT NULL DEFAULT FALSE,
    `two_factor_secret` VARCHAR(255) NULL,
    `backup_codes` JSON NULL,
    `last_login_at` TIMESTAMP NULL,
    `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    
    -- Timestamps
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_code` (`user_code`),
    INDEX `idx_users_type_status` (`user_type`, `status`),
    INDEX `idx_users_mobile` (`mobile`),
    INDEX `idx_users_postcode_state` (`postcode`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users with Australian address and contact format';

-- ============================================================================
-- PROFESSIONALS TABLE - Service provider details
-- ============================================================================
CREATE TABLE `professionals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `professional_code` VARCHAR(20) NOT NULL UNIQUE,
    
    -- Professional Information
    `business_name` VARCHAR(200) NULL,
    `experience_years` INT UNSIGNED NULL,
    `specializations` JSON NULL COMMENT 'Array of specialization areas',
    `service_areas` JSON NULL COMMENT 'Postcodes or suburbs served',
    
    -- Rates (Australian dollars)
    `hourly_rate` DECIMAL(8,2) NULL COMMENT 'Base hourly rate in AUD',
    `minimum_booking_hours` DECIMAL(3,1) NOT NULL DEFAULT 2.0,
    `travel_fee` DECIMAL(6,2) NULL DEFAULT 0.00,
    `weekend_surcharge` DECIMAL(4,2) NULL DEFAULT 0.00 COMMENT 'Weekend surcharge percentage',
    
    -- Availability
    `availability_schedule` JSON NULL COMMENT 'Weekly availability schedule',
    `max_bookings_per_day` INT UNSIGNED NOT NULL DEFAULT 8,
    `advance_booking_days` INT UNSIGNED NOT NULL DEFAULT 14,
    
    -- Australian Compliance
    `police_check_verified` BOOLEAN NOT NULL DEFAULT FALSE,
    `police_check_expiry` DATE NULL,
    `insurance_verified` BOOLEAN NOT NULL DEFAULT FALSE,
    `insurance_expiry` DATE NULL,
    `working_with_children_check` BOOLEAN NOT NULL DEFAULT FALSE,
    `wwcc_number` VARCHAR(50) NULL,
    `wwcc_expiry` DATE NULL,
    
    -- Performance Metrics
    `average_rating` DECIMAL(3,2) NULL DEFAULT 0.00,
    `total_ratings` INT UNSIGNED NOT NULL DEFAULT 0,
    `completed_bookings` INT UNSIGNED NOT NULL DEFAULT 0,
    `cancellation_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `response_time_minutes` INT UNSIGNED NULL,
    
    -- Status
    `verification_status` ENUM('pending', 'in_progress', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `availability_status` ENUM('available', 'busy', 'unavailable', 'on_leave') NOT NULL DEFAULT 'unavailable',
    `profile_completion` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Profile completion percentage',
    
    -- Timestamps
    `verified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_professionals_user_id` (`user_id`),
    KEY `idx_professionals_code` (`professional_code`),
    KEY `idx_professionals_status` (`verification_status`, `availability_status`),
    KEY `idx_professionals_rating` (`average_rating`),
    KEY `idx_professionals_postcode` ((JSON_EXTRACT(`service_areas`, '$[*].postcode'))),
    
    CONSTRAINT `fk_professionals_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Professional service providers';

-- ============================================================================
-- CANDIDATES TABLE - Training system for potential professionals
-- ============================================================================
CREATE TABLE `candidates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `candidate_code` VARCHAR(20) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NULL COMMENT 'Linked to users table if registered',
    
    -- Personal Information
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(15) NOT NULL,
    `date_of_birth` DATE NULL,
    
    -- Address Information
    `street_address` VARCHAR(255) NULL,
    `suburb` VARCHAR(100) NULL,
    `state` ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT') NULL,
    `postcode` VARCHAR(4) NULL,
    
    -- Application Information
    `application_source` VARCHAR(100) NULL COMMENT 'How they found us',
    `motivation` TEXT NULL COMMENT 'Why they want to join',
    `available_hours` JSON NULL COMMENT 'Available working hours',
    `preferred_areas` JSON NULL COMMENT 'Preferred service areas',
    `transportation` ENUM('car', 'public_transport', 'bicycle', 'walking') NULL,
    `has_insurance` BOOLEAN NULL,
    `has_police_check` BOOLEAN NULL,
    
    -- Training Progress
    `status` ENUM('pending', 'in_training', 'training_completed', 'approved', 'rejected', 'converted_to_professional') NOT NULL DEFAULT 'pending',
    `training_started_at` TIMESTAMP NULL,
    `training_completed_at` TIMESTAMP NULL,
    `overall_score` DECIMAL(5,2) NULL COMMENT 'Overall training score',
    `evaluation_notes` TEXT NULL,
    
    -- Conversion to Professional
    `converted_to_professional_at` TIMESTAMP NULL,
    `converted_user_id` BIGINT UNSIGNED NULL COMMENT 'User ID after conversion',
    
    -- Timestamps
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_candidates_code` (`candidate_code`),
    UNIQUE KEY `uk_candidates_email` (`email`),
    KEY `idx_candidates_status` (`status`),
    KEY `idx_candidates_postcode_state` (`postcode`, `state`),
    KEY `idx_candidates_user_id` (`user_id`),
    
    CONSTRAINT `fk_candidates_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Training candidates for professional conversion';

-- ============================================================================
-- TRAININGS TABLE - Training modules and courses
-- ============================================================================
CREATE TABLE `trainings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `training_code` VARCHAR(20) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `training_type` ENUM('onboarding', 'skill_development', 'safety', 'compliance', 'certification') NOT NULL,
    
    -- Training Content
    `content_text` LONGTEXT NULL COMMENT 'Text-based training content',
    `video_url` VARCHAR(500) NULL,
    `estimated_duration` INT UNSIGNED NULL COMMENT 'Duration in minutes',
    `difficulty_level` ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
    
    -- Assessment
    `has_assessment` BOOLEAN NOT NULL DEFAULT TRUE,
    `passing_score` INT UNSIGNED NOT NULL DEFAULT 80 COMMENT 'Minimum score to pass (%)',
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    
    -- Skills and Requirements
    `skills_acquired` JSON NULL COMMENT 'Skills gained from this training',
    `prerequisites` JSON NULL COMMENT 'Required prior trainings',
    `is_required` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Required for professional approval',
    
    -- Status and Scheduling
    `status` ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    `available_from` TIMESTAMP NULL,
    `available_until` TIMESTAMP NULL,
    
    -- Timestamps
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_trainings_code` (`training_code`),
    KEY `idx_trainings_type_status` (`training_type`, `status`),
    KEY `idx_trainings_required` (`is_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Training courses and modules';

-- ============================================================================
-- TRAINING_QUESTIONS TABLE - Assessment questions
-- ============================================================================
CREATE TABLE `training_questions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `training_id` BIGINT UNSIGNED NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('multiple_choice', 'true_false', 'short_answer') NOT NULL,
    `answer_options` JSON NULL COMMENT 'Array of possible answers for multiple choice',
    `correct_answer` TEXT NOT NULL COMMENT 'Correct answer or answer index',
    `points` INT UNSIGNED NOT NULL DEFAULT 1,
    `explanation` TEXT NULL COMMENT 'Explanation for the correct answer',
    `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
    
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    KEY `idx_training_questions_training_id` (`training_id`),
    KEY `idx_training_questions_order` (`training_id`, `display_order`),
    
    CONSTRAINT `fk_training_questions_training_id` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Training assessment questions';

-- ============================================================================
-- CANDIDATE_TRAINING_PROGRESS TABLE - Track training progress
-- ============================================================================
CREATE TABLE `candidate_training_progress` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `candidate_id` BIGINT UNSIGNED NOT NULL,
    `training_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('assigned', 'in_progress', 'completed', 'failed', 'expired') NOT NULL DEFAULT 'assigned',
    `progress_percentage` INT UNSIGNED NOT NULL DEFAULT 0,
    `current_section` VARCHAR(100) NULL,
    `time_spent_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Timestamps
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_candidate_training` (`candidate_id`, `training_id`),
    KEY `idx_training_progress_status` (`status`),
    KEY `idx_training_progress_candidate` (`candidate_id`),
    
    CONSTRAINT `fk_training_progress_candidate_id` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_training_progress_training_id` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Candidate training progress tracking';

-- ============================================================================
-- CANDIDATE_EVALUATION_RESULTS TABLE - Assessment results
-- ============================================================================
CREATE TABLE `candidate_evaluation_results` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `candidate_id` BIGINT UNSIGNED NOT NULL,
    `training_id` BIGINT UNSIGNED NOT NULL,
    `attempt_number` INT UNSIGNED NOT NULL DEFAULT 1,
    
    -- Assessment Results
    `score` DECIMAL(5,2) NOT NULL COMMENT 'Score percentage',
    `total_questions` INT UNSIGNED NOT NULL,
    `correct_answers` INT UNSIGNED NOT NULL,
    `time_taken_minutes` INT UNSIGNED NULL,
    `passed` BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Detailed Results
    `answers` JSON NULL COMMENT 'Array of answers and correctness',
    `feedback` TEXT NULL COMMENT 'Automated or manual feedback',
    
    -- Timestamps
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `graded_at` TIMESTAMP NULL,
    
    PRIMARY KEY (`id`),
    KEY `idx_evaluation_results_candidate` (`candidate_id`, `training_id`),
    KEY `idx_evaluation_results_score` (`score`, `passed`),
    
    CONSTRAINT `fk_evaluation_results_candidate_id` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_evaluation_results_training_id` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Training assessment results';

-- ============================================================================
-- TRAINING_FILES TABLE - Training resource files
-- ============================================================================
CREATE TABLE `training_files` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `training_id` BIGINT UNSIGNED NOT NULL,
    `file_type` ENUM('document', 'image', 'video', 'audio', 'other') NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(100) NULL,
    `description` TEXT NULL,
    
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    KEY `idx_training_files_training_id` (`training_id`),
    KEY `idx_training_files_type` (`file_type`),
    
    CONSTRAINT `fk_training_files_training_id` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Training resource files';

-- ============================================================================
-- BOOKINGS TABLE - Service bookings with Australian pricing
-- ============================================================================
CREATE TABLE `bookings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_code` VARCHAR(20) NOT NULL UNIQUE,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `professional_id` BIGINT UNSIGNED NULL,
    
    -- Service Details
    `service_type` ENUM('regular_cleaning', 'deep_cleaning', 'end_of_lease', 'commercial', 'carpet_cleaning', 'window_cleaning', 'other') NOT NULL,
    `service_description` TEXT NULL,
    `property_type` ENUM('house', 'apartment', 'unit', 'office', 'commercial', 'other') NOT NULL,
    
    -- Address (Australian format)
    `service_address` VARCHAR(255) NOT NULL,
    `service_suburb` VARCHAR(100) NOT NULL,
    `service_state` ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT') NOT NULL,
    `service_postcode` VARCHAR(4) NOT NULL,
    
    -- Booking Details
    `booking_date` DATE NOT NULL,
    `booking_time` TIME NOT NULL,
    `estimated_duration` DECIMAL(3,1) NOT NULL COMMENT 'Duration in hours',
    `special_instructions` TEXT NULL,
    
    -- Pricing (Australian dollars)
    `base_price` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `travel_fee` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `weekend_surcharge` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `additional_fees` JSON NULL COMMENT 'Breakdown of additional fees',
    `discount_amount` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `gst_amount` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT 'GST (10% in Australia)',
    `total_amount` DECIMAL(8,2) NOT NULL,
    
    -- Recurrence
    `is_recurring` BOOLEAN NOT NULL DEFAULT FALSE,
    `recurrence_pattern` ENUM('weekly', 'fortnightly', 'monthly', 'custom') NULL,
    `recurrence_end_date` DATE NULL,
    `parent_booking_id` BIGINT UNSIGNED NULL COMMENT 'For recurring bookings',
    
    -- Status Management
    `status` ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'pending',
    `cancellation_reason` TEXT NULL,
    `cancelled_by` ENUM('customer', 'professional', 'system', 'admin') NULL,
    `cancelled_at` TIMESTAMP NULL,
    
    -- Professional Assignment
    `assigned_at` TIMESTAMP NULL,
    `professional_notified_at` TIMESTAMP NULL,
    `professional_accepted_at` TIMESTAMP NULL,
    
    -- Service Execution
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `actual_duration` DECIMAL(3,1) NULL COMMENT 'Actual service duration',
    
    -- Quality and Feedback
    `customer_rating` INT UNSIGNED NULL CHECK (`customer_rating` BETWEEN 1 AND 5),
    `customer_feedback` TEXT NULL,
    `professional_notes` TEXT NULL,
    
    -- Timestamps
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bookings_code` (`booking_code`),
    KEY `idx_bookings_customer_id` (`customer_id`),
    KEY `idx_bookings_professional_id` (`professional_id`),
    KEY `idx_bookings_date_status` (`booking_date`, `status`),
    KEY `idx_bookings_postcode_state` (`service_postcode`, `service_state`),
    KEY `idx_bookings_parent` (`parent_booking_id`),
    
    CONSTRAINT `fk_bookings_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bookings_professional_id` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bookings_parent_id` FOREIGN KEY (`parent_booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Service bookings with Australian addressing and pricing';

-- ============================================================================
-- CONTINUE WITH MORE TABLES...
-- ============================================================================
-- This is part 1 of the schema. Additional tables will be created in subsequent parts.

-- Add indexes for performance optimization
-- ============================================================================

-- Users table optimization
ALTER TABLE `users` ADD INDEX `idx_users_name_search` (`first_name`, `last_name`);
ALTER TABLE `users` ADD INDEX `idx_users_location` (`suburb`, `state`, `postcode`);
ALTER TABLE `users` ADD INDEX `idx_users_created` (`created_at`);

-- Professionals table optimization  
ALTER TABLE `professionals` ADD INDEX `idx_professionals_rates` (`hourly_rate`, `minimum_booking_hours`);
ALTER TABLE `professionals` ADD INDEX `idx_professionals_performance` (`average_rating`, `completed_bookings`);

-- Candidates table optimization
ALTER TABLE `candidates` ADD INDEX `idx_candidates_location` (`suburb`, `state`, `postcode`);
ALTER TABLE `candidates` ADD INDEX `idx_candidates_training_dates` (`training_started_at`, `training_completed_at`);

-- Bookings table optimization
ALTER TABLE `bookings` ADD INDEX `idx_bookings_service_location` (`service_postcode`, `service_state`, `booking_date`);
ALTER TABLE `bookings` ADD INDEX `idx_bookings_price_range` (`total_amount`, `booking_date`);
ALTER TABLE `bookings` ADD INDEX `idx_bookings_recurring` (`is_recurring`, `parent_booking_id`);

-- Training system optimization
ALTER TABLE `trainings` ADD INDEX `idx_trainings_available` (`available_from`, `available_until`, `status`);
ALTER TABLE `candidate_training_progress` ADD INDEX `idx_training_progress_dates` (`assigned_at`, `completed_at`, `status`);
ALTER TABLE `candidate_evaluation_results` ADD INDEX `idx_evaluation_results_dates` (`submitted_at`, `passed`);

COMMIT;
