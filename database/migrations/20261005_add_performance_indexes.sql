-- ==========================================================
-- StudentOS AI - Performance Database Indexing Migration
-- Migration: 20261005_add_performance_indexes.sql
-- Optimizes query performance across all high-frequency operations:
-- authentication, coursework, attendance, timetable, examinations,
-- notifications, tasks, logs, and AI subsystem queries.
-- ==========================================================

-- 1. Users & Authentication
ALTER TABLE `users` ADD INDEX `idx_users_role_del` (`role_id`, `deleted_at`);
ALTER TABLE `users` ADD INDEX `idx_users_email_del` (`email`, `deleted_at`);
ALTER TABLE `users` ADD INDEX `idx_users_active_del` (`is_active`, `deleted_at`);
ALTER TABLE `users` ADD INDEX `idx_users_deleted` (`deleted_at`);

-- 2. User Sessions & Token Auth
ALTER TABLE `user_sessions` ADD INDEX `idx_sessions_expires` (`expires_at`);
ALTER TABLE `user_sessions` ADD INDEX `idx_sessions_token_exp` (`session_token`, `expires_at`);

-- 3. Student Profiles
ALTER TABLE `student_profiles` ADD INDEX `idx_sp_semester` (`semester`);
ALTER TABLE `student_profiles` ADD INDEX `idx_sp_dept_sem` (`department_id`, `semester`);
ALTER TABLE `student_profiles` ADD INDEX `idx_sp_prom_status` (`promotion_status`);

-- 4. Academic Structure (Departments, Courses, Semesters, Subjects)
ALTER TABLE `departments` ADD INDEX `idx_dept_status` (`status`);

ALTER TABLE `courses` ADD INDEX `idx_courses_status` (`status`);
ALTER TABLE `courses` ADD INDEX `idx_courses_dept_status` (`department_id`, `status`);

ALTER TABLE `semesters` ADD INDEX `idx_semesters_status` (`status`);
ALTER TABLE `semesters` ADD INDEX `idx_semesters_course_status` (`course_id`, `status`);
ALTER TABLE `semesters` ADD INDEX `idx_semesters_num` (`semester_number`);

ALTER TABLE `subjects` ADD INDEX `idx_sub_semester` (`semester`);
ALTER TABLE `subjects` ADD INDEX `idx_sub_status` (`status`);
ALTER TABLE `subjects` ADD INDEX `idx_sub_course_sem_stat` (`course_id`, `semester`, `status`);
ALTER TABLE `subjects` ADD INDEX `idx_sub_dept_sem_stat` (`department_id`, `semester`, `status`);
ALTER TABLE `subjects` ADD INDEX `idx_sub_fac_stat` (`faculty_id`, `status`);

ALTER TABLE `student_subjects` ADD INDEX `idx_ss_stu_sem` (`student_id`, `semester`);
ALTER TABLE `student_subjects` ADD INDEX `idx_ss_sub_status` (`subject_id`, `status`);

-- 5. Timetable & Schedules
ALTER TABLE `class_schedules` ADD INDEX `idx_cs_course_sem_sec` (`course_id`, `semester`, `section`);
ALTER TABLE `class_schedules` ADD INDEX `idx_cs_fac_day` (`faculty_id`, `day_of_week`);
ALTER TABLE `class_schedules` ADD INDEX `idx_cs_day` (`day_of_week`);

-- 6. Coursework & Assignments
ALTER TABLE `assignments` ADD INDEX `idx_asg_deadline` (`deadline`);
ALTER TABLE `assignments` ADD INDEX `idx_asg_semester` (`semester`);
ALTER TABLE `assignments` ADD INDEX `idx_asg_deleted_at` (`deleted_at`);
ALTER TABLE `assignments` ADD INDEX `idx_asg_status` (`status`);
ALTER TABLE `assignments` ADD INDEX `idx_asg_del_deadline` (`deleted_at`, `deadline`);
ALTER TABLE `assignments` ADD INDEX `idx_asg_dept_sem_del` (`department_id`, `semester`, `deleted_at`);
ALTER TABLE `assignments` ADD INDEX `idx_asg_fac_del` (`faculty_id`, `deleted_at`);
ALTER TABLE `assignments` ADD INDEX `idx_asg_sub_del` (`subject_id`, `deleted_at`);

ALTER TABLE `assignment_submissions` ADD INDEX `idx_asb_status` (`status`);
ALTER TABLE `assignment_submissions` ADD INDEX `idx_asb_submitted_at` (`submitted_at`);
ALTER TABLE `assignment_submissions` ADD INDEX `idx_asb_asg_status` (`assignment_id`, `status`);
ALTER TABLE `assignment_submissions` ADD INDEX `idx_asb_stu_status` (`student_id`, `status`);

-- 7. Attendance
ALTER TABLE `attendance` ADD INDEX `idx_att_date` (`date`);
ALTER TABLE `attendance` ADD INDEX `idx_att_status` (`status`);
ALTER TABLE `attendance` ADD INDEX `idx_att_stu_status` (`student_id`, `status`);
ALTER TABLE `attendance` ADD INDEX `idx_att_sub_date` (`subject_id`, `date`);
ALTER TABLE `attendance` ADD INDEX `idx_att_stu_sub_stat` (`student_id`, `subject_id`, `status`);

-- 8. Examinations & Results
ALTER TABLE `exams` ADD INDEX `idx_exams_date` (`exam_date`);
ALTER TABLE `exams` ADD INDEX `idx_exams_status` (`status`);
ALTER TABLE `exams` ADD INDEX `idx_exams_sub_date` (`subject_id`, `exam_date`);
ALTER TABLE `exams` ADD INDEX `idx_exams_fac_date` (`faculty_id`, `exam_date`);

ALTER TABLE `results` ADD INDEX `idx_res_stu_sub` (`student_id`, `subject_id`);
ALTER TABLE `results` ADD INDEX `idx_res_grade` (`grade`);

-- 9. Real-Time Notifications
ALTER TABLE `notifications` ADD INDEX `idx_notif_user_unread` (`user_id`, `is_read`, `created_at`);
ALTER TABLE `notifications` ADD INDEX `idx_notif_user_created` (`user_id`, `created_at`);

-- 10. Student Tasks & Study Sessions
ALTER TABLE `tasks` ADD INDEX `idx_tasks_deadline` (`deadline`);
ALTER TABLE `tasks` ADD INDEX `idx_tasks_user_status_due` (`user_id`, `status`, `deadline`);
ALTER TABLE `tasks` ADD INDEX `idx_tasks_user_due` (`user_id`, `deadline`);

ALTER TABLE `study_sessions` ADD INDEX `idx_ssess_date` (`session_date`);
ALTER TABLE `study_sessions` ADD INDEX `idx_ssess_user_date` (`user_id`, `session_date`, `created_at`);

-- 11. Notes
ALTER TABLE `notes` ADD INDEX `idx_notes_user_pin` (`user_id`, `is_pinned`, `updated_at`);
ALTER TABLE `notes` ADD INDEX `idx_notes_user_sub` (`user_id`, `subject_id`);

-- 12. Security & Compliance Logs
ALTER TABLE `audit_logs` ADD INDEX `idx_al_created_at` (`created_at`);
ALTER TABLE `audit_logs` ADD INDEX `idx_al_action_created` (`action`, `created_at`);
ALTER TABLE `audit_logs` ADD INDEX `idx_al_user_created` (`user_id`, `created_at`);

ALTER TABLE `admin_activity_logs` ADD INDEX `idx_aal_created_at` (`created_at`);
ALTER TABLE `admin_activity_logs` ADD INDEX `idx_aal_action_created` (`action`, `created_at`);
ALTER TABLE `admin_activity_logs` ADD INDEX `idx_aal_admin_created` (`admin_id`, `created_at`);

ALTER TABLE `login_logs` ADD INDEX `idx_ll_user_created` (`user_id`, `created_at`);
ALTER TABLE `login_logs` ADD INDEX `idx_ll_created_at` (`created_at`);

-- 13. Institutional Notices
ALTER TABLE `notices` ADD INDEX `idx_notices_dept_created` (`department_id`, `created_at`);
ALTER TABLE `notices` ADD INDEX `idx_notices_priority_created` (`priority`, `created_at`);

-- 14. AI Subsystem (Conversations, Quizzes, Study Plans)
ALTER TABLE `ai_conversations` ADD INDEX `idx_aiconv_user_updated` (`user_id`, `updated_at`);
ALTER TABLE `ai_quizzes` ADD INDEX `idx_aiq_user_created` (`user_id`, `created_at`);
ALTER TABLE `ai_study_plans` ADD INDEX `idx_aisp_user_active` (`user_id`, `is_active`);
