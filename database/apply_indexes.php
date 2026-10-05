<?php
/**
 * database/apply_indexes.php
 * Automated runner for 20261005_add_performance_indexes.sql
 */

require_once __DIR__ . '/../backend/config/database.php';

$db = Database::getInstance()->getConnection();

echo "========================================================\n";
echo "StudentOS AI - Performance Database Indexing Runner\n";
echo "========================================================\n\n";

$indexesToApply = [
    // 1. Users
    'users' => [
        'idx_users_role_del' => ['role_id', 'deleted_at'],
        'idx_users_email_del' => ['email', 'deleted_at'],
        'idx_users_active_del' => ['is_active', 'deleted_at'],
        'idx_users_deleted' => ['deleted_at'],
    ],
    // 2. User Sessions
    'user_sessions' => [
        'idx_sessions_expires' => ['expires_at'],
        'idx_sessions_token_exp' => ['session_token', 'expires_at'],
    ],
    // 3. Student Profiles
    'student_profiles' => [
        'idx_sp_semester' => ['semester'],
        'idx_sp_dept_sem' => ['department_id', 'semester'],
        'idx_sp_prom_status' => ['promotion_status'],
    ],
    // 4. Academic Structure
    'departments' => [
        'idx_dept_status' => ['status'],
    ],
    'courses' => [
        'idx_courses_status' => ['status'],
        'idx_courses_dept_status' => ['department_id', 'status'],
    ],
    'semesters' => [
        'idx_semesters_status' => ['status'],
        'idx_semesters_course_status' => ['course_id', 'status'],
        'idx_semesters_num' => ['semester_number'],
    ],
    'subjects' => [
        'idx_sub_semester' => ['semester'],
        'idx_sub_status' => ['status'],
        'idx_sub_course_sem_stat' => ['course_id', 'semester', 'status'],
        'idx_sub_dept_sem_stat' => ['department_id', 'semester', 'status'],
        'idx_sub_fac_stat' => ['faculty_id', 'status'],
    ],
    'student_subjects' => [
        'idx_ss_stu_sem' => ['student_id', 'semester'],
        'idx_ss_sub_status' => ['subject_id', 'status'],
    ],
    // 5. Schedules
    'class_schedules' => [
        'idx_cs_course_sem_sec' => ['course_id', 'semester', 'section'],
        'idx_cs_fac_day' => ['faculty_id', 'day_of_week'],
        'idx_cs_day' => ['day_of_week'],
    ],
    // 6. Assignments & Submissions
    'assignments' => [
        'idx_asg_deadline' => ['deadline'],
        'idx_asg_semester' => ['semester'],
        'idx_asg_deleted_at' => ['deleted_at'],
        'idx_asg_status' => ['status'],
        'idx_asg_del_deadline' => ['deleted_at', 'deadline'],
        'idx_asg_dept_sem_del' => ['department_id', 'semester', 'deleted_at'],
        'idx_asg_fac_del' => ['faculty_id', 'deleted_at'],
        'idx_asg_sub_del' => ['subject_id', 'deleted_at'],
    ],
    'assignment_submissions' => [
        'idx_asb_status' => ['status'],
        'idx_asb_submitted_at' => ['submitted_at'],
        'idx_asb_asg_status' => ['assignment_id', 'status'],
        'idx_asb_stu_status' => ['student_id', 'status'],
    ],
    // 7. Attendance
    'attendance' => [
        'idx_att_date' => ['date'],
        'idx_att_status' => ['status'],
        'idx_att_stu_status' => ['student_id', 'status'],
        'idx_att_sub_date' => ['subject_id', 'date'],
        'idx_att_stu_sub_stat' => ['student_id', 'subject_id', 'status'],
    ],
    // 8. Exams & Results
    'exams' => [
        'idx_exams_date' => ['exam_date'],
        'idx_exams_status' => ['status'],
        'idx_exams_sub_date' => ['subject_id', 'exam_date'],
        'idx_exams_fac_date' => ['faculty_id', 'exam_date'],
    ],
    'results' => [
        'idx_res_stu_sub' => ['student_id', 'subject_id'],
        'idx_res_grade' => ['grade'],
    ],
    // 9. Notifications
    'notifications' => [
        'idx_notif_user_unread' => ['user_id', 'is_read', 'created_at'],
        'idx_notif_user_created' => ['user_id', 'created_at'],
    ],
    // 10. Tasks & Study Sessions
    'tasks' => [
        'idx_tasks_deadline' => ['deadline'],
        'idx_tasks_user_status_due' => ['user_id', 'status', 'deadline'],
        'idx_tasks_user_due' => ['user_id', 'deadline'],
    ],
    'study_sessions' => [
        'idx_ssess_date' => ['session_date'],
        'idx_ssess_user_date' => ['user_id', 'session_date', 'created_at'],
    ],
    // 11. Notes
    'notes' => [
        'idx_notes_user_pin' => ['user_id', 'is_pinned', 'updated_at'],
        'idx_notes_user_sub' => ['user_id', 'subject_id'],
    ],
    // 12. Logs
    'audit_logs' => [
        'idx_al_created_at' => ['created_at'],
        'idx_al_action_created' => ['action', 'created_at'],
        'idx_al_user_created' => ['user_id', 'created_at'],
    ],
    'admin_activity_logs' => [
        'idx_aal_created_at' => ['created_at'],
        'idx_aal_action_created' => ['action', 'created_at'],
        'idx_aal_admin_created' => ['admin_id', 'created_at'],
    ],
    'login_logs' => [
        'idx_ll_user_created' => ['user_id', 'created_at'],
        'idx_ll_created_at' => ['created_at'],
    ],
    // 13. Notices
    'notices' => [
        'idx_notices_dept_created' => ['department_id', 'created_at'],
        'idx_notices_priority_created' => ['priority', 'created_at'],
    ],
    // 14. AI Subsystem
    'ai_conversations' => [
        'idx_aiconv_user_updated' => ['user_id', 'updated_at'],
    ],
    'ai_quizzes' => [
        'idx_aiq_user_created' => ['user_id', 'created_at'],
    ],
    'ai_study_plans' => [
        'idx_aisp_user_active' => ['user_id', 'is_active'],
    ],
];

$createdCount = 0;
$skippedCount = 0;
$errorCount = 0;

foreach ($indexesToApply as $table => $indexes) {
    // Check if table exists
    $chkTable = $db->query("SHOW TABLES LIKE '$table'");
    if (!$chkTable || $chkTable->num_rows === 0) {
        echo "[SKIP] Table `$table` does not exist.\n";
        continue;
    }

    // Get existing indexes
    $existingIndexes = [];
    $idxRes = $db->query("SHOW INDEX FROM `$table`");
    if ($idxRes) {
        while ($row = $idxRes->fetch_assoc()) {
            $existingIndexes[$row['Key_name']] = true;
        }
    }

    foreach ($indexes as $indexName => $columns) {
        if (isset($existingIndexes[$indexName])) {
            echo "[EXISTING] `$table`.`$indexName` already exists.\n";
            $skippedCount++;
            continue;
        }

        $colList = implode('`, `', $columns);
        $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` (`$colList`)";
        
        $startTime = microtime(true);
        if ($db->query($sql)) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "[SUCCESS] Created index `$indexName` on `$table` (`$colList`) [{$duration}ms]\n";
            $createdCount++;
        } else {
            echo "[ERROR] Failed to create `$indexName` on `$table`: " . $db->error . "\n";
            $errorCount++;
        }
    }
}

echo "\n--------------------------------------------------------\n";
echo "Indexing Summary:\n";
echo "  - Indexes successfully created: $createdCount\n";
echo "  - Indexes already existing:     $skippedCount\n";
echo "  - Errors encountered:           $errorCount\n";
echo "--------------------------------------------------------\n";
