-- ==========================================================
-- Migration: Remove phone number, section, department and program
-- Date: 2026-09-10
-- ==========================================================

-- 1. Drop Foreign Keys from student_profiles
ALTER TABLE `student_profiles` DROP FOREIGN KEY IF EXISTS `fk_sp_course`;
ALTER TABLE `student_profiles` DROP FOREIGN KEY IF EXISTS `fk_sp_dept`;

-- 2. Drop Columns from student_profiles
ALTER TABLE `student_profiles` 
    DROP COLUMN IF EXISTS `department_id`,
    DROP COLUMN IF EXISTS `course_id`,
    DROP COLUMN IF EXISTS `section`,
    DROP COLUMN IF EXISTS `phone`;

-- 3. Drop phone column from users
ALTER TABLE `users` 
    DROP COLUMN IF EXISTS `phone`;