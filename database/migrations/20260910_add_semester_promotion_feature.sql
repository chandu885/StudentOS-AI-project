-- ==========================================================
-- Migration: Add Student Semester Promotion Opt-In Feature
-- Date: 2026-09-10
-- Features:
--   1. Promotion opt-in fields in student_profiles
--   2. semester_promotions audit & tracking table
--   3. Promotion window controls in system_settings
-- ==========================================================

-- 1. Add promotion tracking columns to student_profiles
ALTER TABLE `student_profiles`
    ADD COLUMN IF NOT EXISTS `promotion_opt_in` TINYINT(1) NOT NULL DEFAULT 0 AFTER `semester`,
    ADD COLUMN IF NOT EXISTS `promotion_status` ENUM('not_opted', 'opted_in', 'promoted', 'rejected') NOT NULL DEFAULT 'not_opted' AFTER `promotion_opt_in`,
    ADD COLUMN IF NOT EXISTS `promotion_target_sem` VARCHAR(20) NULL DEFAULT NULL AFTER `promotion_status`,
    ADD COLUMN IF NOT EXISTS `promotion_requested_at` DATETIME NULL DEFAULT NULL AFTER `promotion_target_sem`,
    ADD COLUMN IF NOT EXISTS `promoted_at` DATETIME NULL DEFAULT NULL AFTER `promotion_requested_at`,
    ADD COLUMN IF NOT EXISTS `promoted_by` INT UNSIGNED NULL DEFAULT NULL AFTER `promoted_at`,
    ADD COLUMN IF NOT EXISTS `promotion_notes` VARCHAR(255) NULL DEFAULT NULL AFTER `promoted_by`,
    ADD COLUMN IF NOT EXISTS `prev_semester` VARCHAR(20) NULL DEFAULT NULL AFTER `promotion_notes`;

-- 2. Create semester_promotions log table
CREATE TABLE IF NOT EXISTS `semester_promotions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `from_semester` VARCHAR(20) NOT NULL,
    `to_semester` VARCHAR(20) NOT NULL,
    `department` VARCHAR(50) NOT NULL,
    `academic_year` VARCHAR(30) NOT NULL DEFAULT '2026-2027',
    `action` ENUM('opt_in', 'opt_out', 'promoted', 'rejected', 'rollback') NOT NULL,
    `performed_by` INT UNSIGNED NOT NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_sem_prom_student` (`student_id`),
    KEY `idx_sem_prom_action` (`action`),
    CONSTRAINT `fk_sem_prom_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Insert baseline promotion system settings
INSERT INTO `system_settings` (`key`, `value`, `description`, `updated_at`)
VALUES
    ('semester_promotion_open', '1', 'Status of student semester promotion window (1=Open, 0=Closed)', NOW()),
    ('semester_promotion_academic_year', '2026-2027', 'Active academic year for semester promotion', NOW()),
    ('semester_promotion_min_cgpa', '0.00', 'Minimum CGPA required to opt-in for promotion (0.00 for no minimum)', NOW())
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);