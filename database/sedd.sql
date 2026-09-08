-- ==========================================================
-- StudentOS AI - Comprehensive Demo Seed Data
-- Database: studentos_ai
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. ROLES & PERMISSIONS
-- ----------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'SUPER_ADMIN', 'Super Admin', 'Full system access, security controls, backup management, and audit inspection'),
(2, 'ADMIN', 'Administrator', 'Academic institute operations, course/subject scheduling, and reporting'),
(3, 'FACULTY', 'Faculty Member', 'Classroom management, student attendance, assignments, and exam grading'),
(4, 'STUDENT', 'Student', 'Academic learning, tasks, study planner, notes, and AI assistance');

INSERT INTO `permissions` (`id`, `module`, `slug`, `name`, `description`) VALUES
(1, 'system', 'system.full_control', 'Full System Control', 'Unrestricted administrative platform access'),
(2, 'users', 'users.manage', 'Manage Users', 'Create, update, activate and deactivate users'),
(3, 'academic', 'academic.manage', 'Manage Academic', 'Manage departments, courses, subjects, schedules'),
(4, 'students', 'students.manage', 'Manage Students', 'Manage student records and enrollments'),
(5, 'faculty', 'faculty.manage', 'Manage Faculty', 'Manage faculty appointments and assignments'),
(6, 'attendance', 'attendance.mark', 'Mark Attendance', 'Record and modify attendance records'),
(7, 'assignments', 'assignments.manage', 'Manage Assignments', 'Create and grade assignments'),
(8, 'exams', 'exams.manage', 'Manage Examinations', 'Schedule exams and evaluate submissions'),
(9, 'ai', 'ai.use', 'Use AI Tools', 'Access AI Assistant, PDF Q&A and Study Planner'),
(10, 'ai', 'ai.settings', 'Manage AI Settings', 'Configure API keys, models and token quotas'),
(11, 'logs', 'logs.view', 'View Logs', 'Inspect audit and security logs'),
(12, 'backup', 'backup.manage', 'Database Backup', 'Generate and restore database backups');

-- Assign permissions to roles
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10), (1, 11), (1, 12),
(2, 2), (2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 11),
(3, 6), (3, 7), (3, 8), (3, 9),
(4, 9);

-- ----------------------------------------------------------
-- 2. CORE USERS
-- Passwords:
-- superadmin@studentos.ai -> Admin@12345
-- admin@studentos.ai      -> Admin@12345
-- faculty@studentos.ai    -> Faculty@12345
-- student@studentos.ai    -> Student@12345
-- ----------------------------------------------------------
INSERT INTO `users` (`id`, `role_id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `avatar`, `is_verified`, `is_active`) VALUES
(1, 1, 'superadmin@studentos.ai', '$2y$12$8ws3j8bu7mE0GahK.EccVewq4V7dc9j.w4vBnEHWZegnlX55gcntO', 'Marcus', 'Vance', '9876543210', 'default-avatar.png', 1, 1),
(2, 2, 'admin@studentos.ai', '$2y$12$8ws3j8bu7mE0GahK.EccVewq4V7dc9j.w4vBnEHWZegnlX55gcntO', 'Elena', 'Rostova', '9876543211', 'default-avatar.png', 1, 1),
(3, 3, 'faculty@studentos.ai', '$2y$12$oeElY5JWtihGrIFzyxmTCOoMbDUI0yRXsFJnuiYypuWqVPp1RK39y', 'Dr. Alan', 'Turing', '9876543212', 'default-avatar.png', 1, 1),
(4, 4, 'student@studentos.ai', '$2y$12$LoDxKa4jUf054bDJjOVXn.8CKDmBA5yUzrn0Wnw/ygClHKRz3zAN2', 'Alex', 'Johnson', '9876543213', 'default-avatar.png', 1, 1),
(5, 3, 'sarah.connor@studentos.ai', '$2y$12$oeElY5JWtihGrIFzyxmTCOoMbDUI0yRXsFJnuiYypuWqVPp1RK39y', 'Prof. Sarah', 'Connor', '9876543214', 'default-avatar.png', 1, 1),
(6, 4, 'emma.watson@studentos.ai', '$2y$12$LoDxKa4jUf054bDJjOVXn.8CKDmBA5yUzrn0Wnw/ygClHKRz3zAN2', 'Emma', 'Watson', '9876543215', 'default-avatar.png', 1, 1);

-- ----------------------------------------------------------
-- 3. DEPARTMENTS & COURSES
-- ----------------------------------------------------------
INSERT INTO `departments` (`id`, `code`, `name`, `description`, `head_id`, `status`) VALUES
(1, 'CSE', 'Computer Science & Engineering', 'Department of Computing, Software Systems and Artificial Intelligence', 3, 'active'),
(2, 'ECE', 'Electronics & Communication', 'Department of Microelectronics, Embedded Systems and Telecommunications', 5, 'active'),
(3, 'MECH', 'Mechanical Engineering', 'Department of Robotics, Mechanics and Thermal Engineering', NULL, 'active'),
(4, 'MGMT', 'School of Business & Management', 'Department of Technology Leadership and Business Analytics', NULL, 'active');

INSERT INTO `courses` (`id`, `department_id`, `code`, `name`, `description`, `duration_years`, `total_semesters`, `degree_type`, `status`) VALUES
(1, 4, 'BBA', 'BBA', 'Bachelor of Business Administration', 3, 6, 'Bachelor', 'active'),
(2, 1, 'BCA', 'BCA', 'Bachelor of Computer Applications', 3, 6, 'Bachelor', 'active');

-- ----------------------------------------------------------
-- 4. PROFILES
-- ----------------------------------------------------------
INSERT INTO `admin_profiles` (`id`, `user_id`, `employee_id`, `department_id`, `designation`, `phone`, `office_location`) VALUES
(1, 1, 'EMP-SA01', NULL, 'Principal System Administrator', '9876543210', 'Admin Block, Room 101'),
(2, 2, 'EMP-AD01', 1, 'Academic Dean / Registrar', '9876543211', 'Admin Block, Room 204');

INSERT INTO `faculty_profiles` (`id`, `user_id`, `employee_id`, `department_id`, `designation`, `qualification`, `specialization`, `office_location`, `phone`, `joining_date`) VALUES
(1, 3, 'FAC-CS101', 1, 'Professor & HOD', 'Ph.D in Distributed Systems', 'Database Systems, Cloud Computing & AI', 'CS Block, Room 302', '9876543212', '2020-07-15'),
(2, 5, 'FAC-CS102', 1, 'Associate Professor', 'M.Tech in Software Engineering', 'Algorithms, Data Structures & Web Tech', 'CS Block, Room 305', '9876543214', '2022-01-10');

INSERT INTO `student_profiles` (`id`, `user_id`, `student_id`, `department_id`, `course_id`, `semester`, `section`, `roll_number`, `phone`, `date_of_birth`, `address`, `blood_group`, `guardian_name`, `guardian_phone`) VALUES
(1, 4, 'STU2026001', 1, 2, '5', 'A', 'CS-2026-042', '9876543213', '2004-05-14', '742 Evergreen Terrace, Tech Park, City', 'O+', 'David Johnson', '9876543299'),
(2, 6, 'STU2026002', 1, 2, '5', 'A', 'CS-2026-043', '9876543215', '2004-09-22', '124 Innovation Blvd, City', 'A+', 'Chris Watson', '9876543298');

-- ----------------------------------------------------------
-- 5. SUBJECTS & ENROLLMENTS
-- ----------------------------------------------------------
INSERT INTO `subjects` (`id`, `course_id`, `department_id`, `faculty_id`, `code`, `name`, `semester`, `credits`, `type`, `syllabus`, `status`) VALUES
(1, 2, 1, 3, 'CS501', 'Database Management Systems', '5', 4, 'core', 'Unit 1: ER Models, Relational Algebra. Unit 2: SQL, Constraints. Unit 3: Normalization (1NF, 2NF, 3NF, BCNF). Unit 4: Transaction Processing, ACID, Concurrency. Unit 5: Indexing and NoSQL.', 'active'),
(2, 2, 1, 5, 'CS502', 'Design & Analysis of Algorithms', '5', 4, 'core', 'Unit 1: Asymptotic Analysis. Unit 2: Divide & Conquer. Unit 3: Greedy Algorithms. Unit 4: Dynamic Programming. Unit 5: Graph Algorithms, NP-Completeness.', 'active'),
(3, 2, 1, 3, 'CS503', 'Operating Systems & Concurrency', '5', 3, 'core', 'Processes, Threads, Scheduling, Memory Management, Virtual Memory, File Systems, Deadlocks.', 'active'),
(4, 2, 1, 5, 'CS504', 'Web Engineering & Distributed Cloud', '5', 3, 'core', 'Full-stack Web architecture, REST APIs, Microservices, Security, OAuth2, Caching.', 'active'),
(5, 1, 4, 3, 'BBA101', 'Principles of Management', '1', 3, 'core', 'Fundamentals of Management, Planning, Organizing, Staffing, Directing and Controlling in modern business.', 'active'),
(6, 1, 4, 5, 'BBA102', 'Business Economics & Financial Accounting', '1', 4, 'core', 'Microeconomics, cost analysis, financial statements, balance sheet preparation, and cash flow.', 'active');

INSERT INTO `student_subjects` (`id`, `student_id`, `subject_id`, `semester`, `academic_year`, `status`) VALUES
(1, 4, 1, '5', '2026-2027', 'enrolled'),
(2, 4, 2, '5', '2026-2027', 'enrolled'),
(3, 4, 3, '5', '2026-2027', 'enrolled'),
(4, 4, 4, '5', '2026-2027', 'enrolled'),
(5, 6, 1, '5', '2026-2027', 'enrolled'),
(6, 6, 2, '5', '2026-2027', 'enrolled');

-- ----------------------------------------------------------
-- 6. CLASS SCHEDULES
-- ----------------------------------------------------------
INSERT INTO `class_schedules` (`id`, `subject_id`, `faculty_id`, `department_id`, `course_id`, `semester`, `section`, `day_of_week`, `start_time`, `end_time`, `room_number`) VALUES
(1, 1, 3, 1, 2, '5', 'A', 'Monday', '09:00:00', '10:00:00', 'LH-201'),
(2, 2, 5, 1, 2, '5', 'A', 'Monday', '10:15:00', '11:15:00', 'LH-201'),
(3, 3, 3, 1, 2, '5', 'A', 'Tuesday', '09:00:00', '10:00:00', 'LH-203'),
(4, 4, 5, 1, 2, '5', 'A', 'Tuesday', '11:30:00', '12:30:00', 'Lab-3'),
(5, 1, 3, 1, 2, '5', 'A', 'Wednesday', '09:00:00', '10:00:00', 'LH-201'),
(6, 2, 5, 1, 2, '5', 'A', 'Thursday', '10:00:00', '11:00:00', 'LH-201'),
(7, 4, 5, 1, 2, '5', 'A', 'Friday', '14:00:00', '16:00:00', 'Lab-3');

-- ----------------------------------------------------------
-- 7. ATTENDANCE (Recent 10 days sample)
-- ----------------------------------------------------------
INSERT INTO `attendance` (`subject_id`, `student_id`, `faculty_id`, `date`, `status`, `remarks`) VALUES
(1, 4, 3, '2026-08-25', 'present', 'On time'),
(1, 4, 3, '2026-08-27', 'present', 'Active in discussion'),
(1, 4, 3, '2026-08-29', 'present', 'On time'),
(1, 4, 3, '2026-09-01', 'present', 'On time'),
(1, 4, 3, '2026-09-03', 'absent', 'Medical leave submitted'),
(2, 4, 5, '2026-08-26', 'present', 'Good participation'),
(2, 4, 5, '2026-08-28', 'present', 'On time'),
(2, 4, 5, '2026-09-02', 'present', 'On time'),
(2, 4, 5, '2026-09-04', 'late', 'Arrived 10 mins late'),
(3, 4, 3, '2026-08-26', 'present', 'On time'),
(3, 4, 3, '2026-08-28', 'present', 'On time'),
(3, 4, 3, '2026-09-02', 'present', 'On time'),
(4, 4, 5, '2026-08-25', 'present', 'Lab work completed'),
(4, 4, 5, '2026-08-29', 'present', 'Lab demo verified');

-- ----------------------------------------------------------
-- 8. ASSIGNMENTS & SUBMISSIONS
-- ----------------------------------------------------------
INSERT INTO `assignments` (`id`, `subject_id`, `faculty_id`, `title`, `description`, `instructions`, `deadline`, `max_marks`, `status`) VALUES
(1, 1, 3, 'DBMS Normalization & BCNF Case Study', 'Decompose a university registrar relation into 3NF and BCNF. Provide dependency preservation proofs.', 'Submit typed PDF with dependency diagram and step-by-step reasoning.', '2026-09-12 23:59:00', 50, 'published'),
(2, 2, 5, 'Dynamic Programming: Knapsack & Edit Distance', 'Implement 0/1 Knapsack and Levenshtein distance algorithms. Analyze space optimization approaches.', 'Include GitHub repo link or zipped source code with automated benchmark tests.', '2026-09-15 18:00:00', 50, 'published'),
(3, 4, 5, 'Secure REST API Implementation in PHP/Flask', 'Build an authenticated endpoints suite with JWT, input sanitization, and rate-limiting.', 'Follow production security guidelines from OWASP Top 10.', '2026-09-20 23:59:00', 100, 'published');

INSERT INTO `assignment_submissions` (`id`, `assignment_id`, `student_id`, `submission_text`, `marks_obtained`, `feedback`, `graded_by`, `status`) VALUES
(1, 1, 4, 'Uploaded comprehensive proof document demonstrating BCNF decomposition without loss of dependencies.', 48.00, 'Excellent mathematical proof and clear dependency diagrams!', 3, 'graded');

-- ----------------------------------------------------------
-- 9. EXAMINATIONS & RESULTS
-- ----------------------------------------------------------
INSERT INTO `exams` (`id`, `subject_id`, `faculty_id`, `title`, `exam_type`, `exam_date`, `start_time`, `end_time`, `total_marks`, `passing_marks`, `instructions`, `room_number`, `status`) VALUES
(1, 1, 3, 'DBMS Midterm Examination', 'midterm', '2026-09-22', '10:00:00', '12:00:00', 50, 20, 'Calculators allowed. No electronic devices. All questions mandatory.', 'Auditorium A', 'scheduled'),
(2, 2, 5, 'Algorithms Internal Assessment 1', 'quiz', '2026-09-25', '14:00:00', '15:00:00', 25, 10, 'MCQ and short proofs.', 'LH-201', 'scheduled');

INSERT INTO `results` (`id`, `exam_id`, `student_id`, `subject_id`, `marks_obtained`, `total_marks`, `grade`, `remarks`, `published_at`) VALUES
(1, 1, 4, 1, 45.50, 50.00, 'A+', 'Outstanding performance in query optimization and Normalization questions', '2026-09-01 12:00:00');

INSERT INTO `performance` (`student_id`, `semester`, `gpa`, `cgpa`, `rank`, `attendance_pct`, `credits_completed`) VALUES
(4, '4', 3.85, 3.78, 3, 92.50, 84),
(4, '5', 3.90, 3.82, 2, 94.20, 104);

-- ----------------------------------------------------------
-- 10. TASKS & GOALS
-- ----------------------------------------------------------
INSERT INTO `tasks` (`id`, `user_id`, `title`, `description`, `priority`, `status`, `category`, `deadline`) VALUES
(1, 4, 'Complete DBMS BCNF decomposition assignment', 'Verify 3NF vs BCNF closure algorithms on given relation schema.', 'urgent', 'completed', 'academic', '2026-09-10 23:59:00'),
(2, 4, 'Prepare Dynamic Programming notes for Quiz', 'Review Memoization vs Tabulation for matrix multiplication and LCS.', 'high', 'in_progress', 'academic', '2026-09-14 18:00:00'),
(3, 4, 'Submit project proposal for Capstone', 'Finalize StudentOS AI architecture diagram and user flow.', 'medium', 'todo', 'project', '2026-09-18 17:00:00'),
(4, 4, 'Solve 5 LeetCode DP Problems', 'Focus on Coin Change, Longest Increasing Subsequence, and Word Break.', 'medium', 'in_progress', 'skill', '2026-09-11 22:00:00');

INSERT INTO `goals` (`id`, `user_id`, `title`, `description`, `category`, `target_date`, `progress`, `status`) VALUES
(1, 4, 'Achieve 9.0+ Semester GPA', 'Maintain top attendance and score A+ in DBMS and Algorithms.', 'academic', '2026-12-20', 85, 'in_progress'),
(2, 4, 'Master System Design & Microservices', 'Read DDIA and build two distributed API services.', 'career', '2026-11-30', 60, 'in_progress'),
(3, 4, '30-Day Daily Coding Streak', 'Consistent daily algorithmic problem solving.', 'skill', '2026-09-30', 70, 'in_progress');

-- ----------------------------------------------------------
-- 11. NOTES & STUDY SESSIONS
-- ----------------------------------------------------------
INSERT INTO `notes` (`id`, `user_id`, `subject_id`, `title`, `content`, `tags`, `is_pinned`, `is_favorite`) VALUES
(1, 4, 1, 'Database Normalization Master Guide', '### Key Rules for Relational Normalization\n\n1. **1NF**: Atomic values, no repeating groups.\n2. **2NF**: In 1NF and every non-prime attribute is fully functionally dependent on any candidate key (no partial dependency).\n3. **3NF**: In 2NF and no transitive dependencies (for X -> A, either X is a superkey or A is a prime attribute).\n4. **BCNF**: For every functional dependency X -> A, X must be a superkey.', 'dbms, normalization, sql, exam', 1, 1),
(2, 4, 2, 'Graph Traversal Algorithms (BFS vs DFS)', '### BFS\n- Uses Queue (FIFO)\n- Computes shortest path in unweighted graphs\n- Time Complexity: O(V + E)\n\n### DFS\n- Uses Stack / Recursion\n- Topological Sorting, Cycle Detection\n- Time Complexity: O(V + E)', 'algorithms, graphs, dsa', 1, 1);

INSERT INTO `study_sessions` (`id`, `user_id`, `subject_id`, `topic`, `duration_minutes`, `notes`, `session_date`) VALUES
(1, 4, 1, 'BCNF Lossless Join Decomposition Proofs', 45, 'Completed 4 textbook exercise problems successfully.', '2026-09-04'),
(2, 4, 2, 'Dijkstra and Bellman-Ford comparisons', 50, 'Practiced negative cycle detection logic.', '2026-09-05');

-- ----------------------------------------------------------
-- 12. NOTICES & NOTIFICATIONS
-- ----------------------------------------------------------
INSERT INTO `notices` (`id`, `title`, `content`, `target_role`, `department_id`, `priority`, `posted_by`) VALUES
(1, 'Mid-Semester Examination Schedule Announced', 'All 5th semester examinations will commence from September 22nd. Please check your personalized exam timetable in the Examination portal.', 'all', 1, 'high', 2),
(2, 'Annual Hackathon 2026 Registrations Open', 'The department is organizing HackAI 2026. Teams of up to 4 students can register before September 15th.', 'STUDENT', 1, 'medium', 3);

INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `action_url`, `is_read`) VALUES
(4, 'Assignment Graded', 'Your submission for DBMS Normalization has been evaluated: 48/50.', 'success', '/student/assignments.php', 0),
(4, 'Upcoming Exam Alert', 'DBMS Midterm Examination is scheduled for September 22 at 10:00 AM in Auditorium A.', 'exam', '/student/exams.php', 0),
(4, 'Attendance Good Standing', 'Your overall attendance is 94.2%. Keep it up!', 'info', '/student/attendance.php', 1);

-- ----------------------------------------------------------
-- 13. AI RECOMMENDATIONS & STUDY PLANS
-- ----------------------------------------------------------
INSERT INTO `ai_recommendations` (`user_id`, `title`, `category`, `suggestion`, `priority`, `status`) VALUES
(4, 'Focus on Dynamic Programming Practice', 'study', 'Based on your upcoming Algorithms Internal Assessment on Sept 25, spend 45 minutes practicing 0/1 Knapsack variations.', 'high', 'unread'),
(4, 'Review Operating Systems Virtual Memory', 'exam', 'Your last quiz indicated slight confusion in Page Replacement Algorithms (LRU vs FIFO). Review Unit 4 notes.', 'medium', 'unread');

INSERT INTO `ai_study_plans` (`user_id`, `subject_id`, `title`, `plan_content`, `start_date`, `end_date`, `is_active`) VALUES
(4, 1, 'DBMS Midterm Prep - 14 Day Plan', 'Day 1-3: Relational Algebra & Calculus\nDay 4-7: SQL Queries, Triggers & Views\nDay 8-10: 1NF to BCNF Normalization\nDay 11-12: Transaction Management & ACID\nDay 13-14: Mock Quizzes & Past Year Papers', '2026-09-08', '2026-09-21', 1);

-- ----------------------------------------------------------
-- 14. SYSTEM & AI SETTINGS
-- ----------------------------------------------------------
INSERT INTO `system_settings` (`key`, `value`, `description`) VALUES
('app_name', 'StudentOS AI', 'Platform display brand name'),
('academic_year', '2026-2027', 'Current active academic year'),
('allow_registration', '1', 'Enable student self-registration'),
('maintenance_mode', '0', 'Put site in maintenance mode'),
('max_upload_size_mb', '20', 'Maximum file upload size in Megabytes');

INSERT INTO `ai_settings` (`setting_key`, `setting_value`, `description`) VALUES
('gemini_api_key', '', 'Google Gemini API key for AI Tutor & RAG'),
('default_model', 'gemini-1.5-flash', 'Default LLM model identifier'),
('temperature', '0.7', 'Sampling temperature for responses'),
('max_output_tokens', '2048', 'Maximum tokens per response'),
('enable_rag', '1', 'Enable PDF document chunking and contextual Q&A');

SET FOREIGN_KEY_CHECKS = 1;
