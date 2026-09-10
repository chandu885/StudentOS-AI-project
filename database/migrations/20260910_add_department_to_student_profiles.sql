-- ==========================================================
-- Migration: Add Department to Student Registration & Profiles
-- Date: 2026-09-10
-- ==========================================================

-- 1. Ensure BBA and BCA departments exist in departments table
INSERT INTO `departments` (`code`, `name`, `description`, `status`)
VALUES 
    ('BBA', 'Bachelor of Business Administration (BBA)', 'Department of Business Administration', 'active'),
    ('BCA', 'Bachelor of Computer Applications (BCA)', 'Department of Computer Applications', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- 2. Add department and department_id columns to student_profiles
ALTER TABLE `student_profiles`
    ADD COLUMN `department` VARCHAR(50) NOT NULL DEFAULT 'BCA' AFTER `student_id`,
    ADD COLUMN `department_id` INT UNSIGNED NULL AFTER `department`;

-- 3. Add Index and Foreign Key (Set Null on delete)
ALTER TABLE `student_profiles`
    ADD KEY `idx_sp_dept` (`department_id`),
    ADD CONSTRAINT `fk_sp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

-- 4. Map existing student records to their respective department and department_id
UPDATE `student_profiles` sp
SET 
    sp.department = CASE 
        WHEN sp.student_id LIKE '%BBA%' THEN 'BBA'
        ELSE 'BCA'
    END,
    sp.department_id = CASE 
        WHEN sp.student_id LIKE '%BBA%' THEN (SELECT id FROM departments WHERE code = 'BBA' LIMIT 1)
        ELSE (SELECT id FROM departments WHERE code = 'BCA' LIMIT 1)
    END;