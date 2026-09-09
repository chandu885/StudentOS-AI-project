-- Migration: Create semesters table
CREATE TABLE IF NOT EXISTS `semesters` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT UNSIGNED NOT NULL,
    `semester_number` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `academic_year` VARCHAR(30) NOT NULL DEFAULT '2026-2027',
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `status` ENUM('active', 'upcoming', 'completed') NOT NULL DEFAULT 'active',
    `description` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_semesters_course` (`course_id`),
    KEY `idx_semesters_academic_year` (`academic_year`),
    UNIQUE KEY `unique_course_sem_year` (`course_id`, `semester_number`, `academic_year`),
    CONSTRAINT `fk_semesters_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert baseline semesters for Course 1 (BBA) and Course 2 (BCA)
INSERT IGNORE INTO `semesters` (`course_id`, `semester_number`, `name`, `academic_year`, `start_date`, `end_date`, `status`, `description`, `created_by`) VALUES
(1, 1, 'Semester 1 (Fall 2026)', '2026-2027', '2026-08-01', '2026-12-20', 'completed', 'BBA First Semester Foundation', 1),
(1, 2, 'Semester 2 (Spring 2027)', '2026-2027', '2027-01-10', '2027-05-30', 'completed', 'BBA Second Semester Core', 1),
(1, 3, 'Semester 3 (Fall 2027)', '2026-2027', '2026-08-01', '2026-12-20', 'completed', 'BBA Third Semester Core', 1),
(1, 4, 'Semester 4 (Spring 2028)', '2026-2027', '2027-01-10', '2027-05-30', 'completed', 'BBA Fourth Semester Intermediate', 1),
(1, 5, 'Semester 5 (Fall 2028)', '2026-2027', '2026-08-01', '2026-12-20', 'active', 'BBA Fifth Semester Advanced Management', 1),
(1, 6, 'Semester 6 (Spring 2029)', '2026-2027', '2027-01-10', '2027-05-30', 'upcoming', 'BBA Final Semester Capstone & Internship', 1),
(2, 1, 'Semester 1 (Fall 2026)', '2026-2027', '2026-08-01', '2026-12-20', 'completed', 'BCA First Semester Computing Foundations', 1),
(2, 2, 'Semester 2 (Spring 2027)', '2026-2027', '2027-01-10', '2027-05-30', 'completed', 'BCA Second Semester Programming & Electronics', 1),
(2, 3, 'Semester 3 (Fall 2027)', '2026-2027', '2026-08-01', '2026-12-20', 'completed', 'BCA Third Semester Data Structures', 1),
(2, 4, 'Semester 4 (Spring 2028)', '2026-2027', '2027-01-10', '2027-05-30', 'completed', 'BCA Fourth Semester Software Engineering', 1),
(2, 5, 'Semester 5 (Fall 2028)', '2026-2027', '2026-08-01', '2026-12-20', 'active', 'BCA Fifth Semester Database Systems & OS', 1),
(2, 6, 'Semester 6 (Spring 2029)', '2026-2027', '2027-01-10', '2027-05-30', 'upcoming', 'BCA Final Semester Cloud Computing & Projects', 1);
