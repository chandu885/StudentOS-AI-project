<?php
// frontend/documentation.php - Dedicated Standalone Documentation & Demos Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

$loggedIn = isLoggedIn();
$currentUser = $loggedIn ? ($_SESSION['user'] ?? null) : null;
$userRoleId = $currentUser['role_id'] ?? 4;

// Allow role pre-selection via GET parameter (?role=student|faculty|admin|superadmin)
$requestedRole = strtolower(trim($_GET['role'] ?? ''));
if (!in_array($requestedRole, ['student', 'faculty', 'admin', 'superadmin'])) {
    // If no query param, default to logged-in user role or student
    if ($loggedIn) {
        if ($userRoleId == 1) $requestedRole = 'superadmin';
        elseif ($userRoleId == 2) $requestedRole = 'admin';
        elseif ($userRoleId == 3) $requestedRole = 'faculty';
        else $requestedRole = 'student';
    } else {
        $requestedRole = 'student';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation & Interactive Demos — StudentOS AI</title>
    <meta name="description" content="Comprehensive institutional architecture documentation, step-by-step guides, and interactive role simulation suite for StudentOS AI.">
    
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        .doc-page-header {
            position: sticky;
            top: 0;
            z-index: var(--z-sticky);
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 0;
        }
        .doc-page-header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .doc-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 19px;
            font-weight: 800;
            color: var(--text-primary);
            text-decoration: none;
        }
        .doc-brand i {
            color: var(--primary);
            font-size: 22px;
        }
        .doc-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .doc-hero-banner {
            padding: 50px 0 30px;
            text-align: center;
            background: radial-gradient(circle at 50% 10%, rgba(99, 102, 241, 0.14) 0%, transparent 70%);
            border-bottom: 1px solid var(--border-color);
        }
        .doc-hero-banner h1 {
            font-size: 34px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 10px;
        }
        .doc-hero-banner p {
            font-size: 15px;
            color: var(--text-secondary);
            max-width: 780px;
            margin: 0 auto 20px;
            line-height: 1.6;
        }
        .quick-nav-pills {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .quick-nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: var(--radius-full);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .quick-nav-pill:hover {
            color: var(--text-primary);
            border-color: var(--primary);
        }
    </style>
</head>
<body class="landing-page">

    <!-- Top Navigation Header -->
    <header class="doc-page-header">
        <div class="container">
            <a href="<?php echo htmlspecialchars(url('/index.php')); ?>" class="doc-brand" title="StudentOS AI">
                <i class="fas fa-graduation-cap"></i>
                <span>StudentOS <span class="gradient-text">AI</span></span>
                <span class="badge badge-purple" style="font-size: 11px; margin-left: 6px;">Documentation &amp; Demos</span>
            </a>

            <div class="doc-header-actions">
                <!-- Public Index Link (Opens in separate page) -->
                <a href="<?php echo htmlspecialchars(url('/index.php')); ?>" class="btn btn-outline" target="_blank" rel="noopener noreferrer" style="padding: 7px 14px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;" title="Open Public Index in new tab">
                    <i class="fas fa-home"></i>
                    <span>Public Index</span>
                    <i class="fas fa-external-link-alt" style="font-size: 10px; opacity: 0.65;"></i>
                </a>

                <?php if ($loggedIn && $currentUser): ?>
                    <a href="<?php echo htmlspecialchars(url(getDashboardUrl())); ?>" class="btn btn-primary" role="button" style="padding: 7px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-chart-pie"></i>
                        <span>My Dashboard</span>
                    </a>
                    <?php echo renderLogoutButton('btn btn-danger', 'padding: 7px 14px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;'); ?>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars(url('/login.php')); ?>" class="btn btn-primary" role="button" style="padding: 7px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Portal Sign In</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="doc-hero-banner">
        <div class="container">
            <div class="badge badge-primary" style="margin-bottom: 12px; font-size: 12px; padding: 4px 12px;">
                <i class="fas fa-book-reader"></i> Dedicated Documentation Portal
            </div>
            <h1>Institutional Architecture &amp; Live Demos</h1>
            <p>
                Explore complete multi-tier operational workflows for <strong>Students</strong>, <strong>Faculty</strong>, <strong>Administrators</strong>, and <strong>Super Admins</strong>. Select an institutional tier below to review the end-to-end lifecycle steps and execute real-time simulated sandbox demos.
            </p>

            <div class="quick-nav-pills">
                <span style="font-size: 12px; color: var(--text-muted); align-self: center; margin-right: 4px;">Direct Portals:</span>
                <a href="<?php echo htmlspecialchars(url('/student/login.php')); ?>" class="quick-nav-pill" target="_blank" rel="noopener noreferrer"><i class="fas fa-user-graduate" style="color: #818CF8;"></i> Student Portal <i class="fas fa-external-link-alt" style="font-size: 9px; opacity: 0.6;"></i></a>
                <a href="<?php echo htmlspecialchars(url('/faculty/login.php')); ?>" class="quick-nav-pill" target="_blank" rel="noopener noreferrer"><i class="fas fa-chalkboard-teacher" style="color: #34D399;"></i> Faculty Portal <i class="fas fa-external-link-alt" style="font-size: 9px; opacity: 0.6;"></i></a>
                <a href="<?php echo htmlspecialchars(url('/admin/login.php')); ?>" class="quick-nav-pill" target="_blank" rel="noopener noreferrer"><i class="fas fa-shield-alt" style="color: #FBBF24;"></i> Admin Portal <i class="fas fa-external-link-alt" style="font-size: 9px; opacity: 0.6;"></i></a>
                <a href="<?php echo htmlspecialchars(url('/super-admin/login.php')); ?>" class="quick-nav-pill" target="_blank" rel="noopener noreferrer"><i class="fas fa-crown" style="color: #F87171;"></i> Super Admin <i class="fas fa-external-link-alt" style="font-size: 9px; opacity: 0.6;"></i></a>
            </div>
        </div>
    </section>

    <!-- Main Documentation Section -->
    <main class="documentation-section" style="padding: 40px 0 80px; border-top: none;">
        <div class="container">

            <!-- Role Switcher Tabs -->
            <div class="doc-tabs-container">
                <button class="doc-role-tab <?php echo ($requestedRole === 'student') ? 'active' : ''; ?>" id="tab-student" onclick="selectDocRole('student')">
                    <i class="fas fa-user-graduate"></i>
                    <span>User (Student)</span>
                    <span class="doc-role-badge">Learner Tier (Steps 1–5)</span>
                </button>
                <button class="doc-role-tab <?php echo ($requestedRole === 'faculty') ? 'active' : ''; ?>" id="tab-faculty" onclick="selectDocRole('faculty')">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Faculty</span>
                    <span class="doc-role-badge">User + Faculty Demos (Steps 1–10)</span>
                </button>
                <button class="doc-role-tab <?php echo ($requestedRole === 'admin') ? 'active' : ''; ?>" id="tab-admin" onclick="selectDocRole('admin')">
                    <i class="fas fa-shield-alt"></i>
                    <span>Admin</span>
                    <span class="doc-role-badge">User + Faculty + Admin (Steps 1–15)</span>
                </button>
                <button class="doc-role-tab <?php echo ($requestedRole === 'superadmin') ? 'active' : ''; ?>" id="tab-superadmin" onclick="selectDocRole('superadmin')">
                    <i class="fas fa-crown"></i>
                    <span>Super Admin</span>
                    <span class="doc-role-badge">Universal Tier (All 20 Steps)</span>
                </button>
            </div>

            <!-- Scope Summary Card -->
            <div class="doc-scope-card">
                <div>
                    <div class="doc-scope-title" id="docScopeTitle">
                        <i class="fas fa-layer-group" style="color: var(--primary);"></i>
                        <span>Learner Tier Documentation Scope</span>
                    </div>
                    <p id="docScopeSummary" style="font-size: 13px; color: var(--text-secondary); margin-top: 4px; margin-bottom: 0;">
                        Showcasing student learning lifecycle steps and interactive AI study &amp; grade calculation demos.
                    </p>
                </div>
                <div class="doc-scope-pills" id="docScopePills">
                    <span class="doc-scope-pill active-pill"><i class="fas fa-user-graduate"></i> User Demos (5 Steps)</span>
                </div>
            </div>

            <!-- Main Content Grid: Left = Steps, Right = Live Demo Terminal -->
            <div class="doc-content-grid">
                <!-- Left: Workflow Steps -->
                <div class="doc-steps-list">
                    <!-- Group 1: User / Student Steps (Shown in all 4 tiers) -->
                    <div id="group-student-steps">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <h4 style="font-size: 14px; font-weight: 700; color: #818CF8; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-user-graduate"></i> User (Student) Operating Lifecycle
                            </h4>
                            <span class="badge badge-primary" style="font-size: 11px;">Steps 1 – 5</span>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num">1</span> Account Registration &amp; Degree Enrollment
                                </div>
                                <span class="badge badge-secondary" style="font-size: 11px;">Onboarding</span>
                            </div>
                            <div class="doc-step-desc">
                                Register with verified institutional email, student roll code, semester, and active degree course program (B.Tech, M.Tech, MCA).
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Institutional Credentials</span>
                                <span><i class="fas fa-check"></i> Output: RBAC Role 4 Session</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num">2</span> Curriculum, Timetable &amp; Syllabus Sync
                                </div>
                                <span class="badge badge-secondary" style="font-size: 11px;">Academic Sync</span>
                            </div>
                            <div class="doc-step-desc">
                                Automatically synchronizes enrolled courses, weekly lecture periods, room assignments, and faculty instructor profiles.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Course ID &amp; Semester</span>
                                <span><i class="fas fa-check"></i> Output: Interactive Schedule Grid</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num">3</span> 24/7 AI Assistant &amp; Document RAG Q&amp;A
                                </div>
                                <span class="badge badge-purple" style="font-size: 11px;">Gemini RAG</span>
                            </div>
                            <div class="doc-step-desc">
                                Upload lecture slides, notes, and textbooks. Ask academic queries with zero-hallucination sliding-window vector citations.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: PDF / Syllabus Document</span>
                                <span><i class="fas fa-check"></i> Output: Answer with Page # Citation</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num">4</span> Dynamic Study Planner &amp; Deadline Tracker
                                </div>
                                <span class="badge badge-success" style="font-size: 11px;">Pomodoro</span>
                            </div>
                            <div class="doc-step-desc">
                                AI inspects pending assignments, quizzes, and midterm dates to synthesize a prioritized daily study timetable.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Assignment &amp; Exam Dates</span>
                                <span><i class="fas fa-check"></i> Output: Optimized Revision Tasks</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num">5</span> Active-Recall Quizzes &amp; SGPA Grade Ledger
                                </div>
                                <span class="badge badge-info" style="font-size: 11px;">Analytics</span>
                            </div>
                            <div class="doc-step-desc">
                                Generate active-recall practice MCQs from course modules and calculate semester SGPA using credit-weighted formulas.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Subject Credits &amp; Marks</span>
                                <span><i class="fas fa-check"></i> Output: Real-time SGPA &amp; CGPA</span>
                            </div>
                        </div>
                    </div>

                    <!-- Group 2: Faculty Steps (Shown in Faculty, Admin, Super Admin) -->
                    <div id="group-faculty-steps" style="display: none; margin-top: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <h4 style="font-size: 14px; font-weight: 700; color: #34D399; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-chalkboard-teacher"></i> Faculty Operational Management
                            </h4>
                            <span class="badge badge-success" style="font-size: 11px;">Steps 6 – 10</span>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #10B981;">6</span> Class Section &amp; Syllabus Publishing
                                </div>
                                <span class="badge badge-secondary" style="font-size: 11px;">Course Setup</span>
                            </div>
                            <div class="doc-step-desc">
                                Upload course syllabi, define lecture hours, configure modules, and view rosters of enrolled student cohorts.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Subject Syllabus Document</span>
                                <span><i class="fas fa-check"></i> Output: Course Indexed for Students</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #10B981;">7</span> Daily Roll Call &amp; Attendance Logging
                                </div>
                                <span class="badge badge-warning" style="font-size: 11px;">Attendance</span>
                            </div>
                            <div class="doc-step-desc">
                                Record daily attendance with one-click batch controls and automatically flag students below the mandatory 75% threshold.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Student Roll Check</span>
                                <span><i class="fas fa-check"></i> Output: Real-time % &amp; Shortfall Notice</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #10B981;">8</span> Assignment Setup &amp; Rubric Evaluation
                                </div>
                                <span class="badge badge-primary" style="font-size: 11px;">Grading</span>
                            </div>
                            <div class="doc-step-desc">
                                Publish assignments with strict submission deadlines, review uploaded student files, and record rubric feedback.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Assignment Description &amp; Due Date</span>
                                <span><i class="fas fa-check"></i> Output: Graded Submissions</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #10B981;">9</span> Examination Scheduling &amp; Marks Entry
                                </div>
                                <span class="badge badge-purple" style="font-size: 11px;">Exams</span>
                            </div>
                            <div class="doc-step-desc">
                                Schedule midterms, configure question papers, input student scores, and generate automated class average score curves.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Exam Marks List</span>
                                <span><i class="fas fa-check"></i> Output: SGPA Grade Updates</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #10B981;">10</span> Academic Guidance &amp; Targeted Notices
                                </div>
                                <span class="badge badge-info" style="font-size: 11px;">Communication</span>
                            </div>
                            <div class="doc-step-desc">
                                Broadcast official announcements to specific class cohorts and dispatch intervention alerts to at-risk students.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Notice Content &amp; Target Class</span>
                                <span><i class="fas fa-check"></i> Output: Real-time Notifications</span>
                            </div>
                        </div>
                    </div>

                    <!-- Group 3: Admin Steps (Shown in Admin, Super Admin) -->
                    <div id="group-admin-steps" style="display: none; margin-top: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <h4 style="font-size: 14px; font-weight: 700; color: #FBBF24; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-shield-alt"></i> University Administrator Governance
                            </h4>
                            <span class="badge badge-warning" style="font-size: 11px;">Steps 11 – 15</span>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #F59E0B;">11</span> Department &amp; Degree Program Setup
                                </div>
                                <span class="badge badge-secondary" style="font-size: 11px;">Infrastructure</span>
                            </div>
                            <div class="doc-step-desc">
                                Configure institutional departments (CSE, ECE, MECH), degree courses, total semesters, and graduation credit limits.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Academic Regulations</span>
                                <span><i class="fas fa-check"></i> Output: Active Degree Catalog</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #F59E0B;">12</span> Student Admissions &amp; Faculty Roster
                                </div>
                                <span class="badge badge-info" style="font-size: 11px;">Directory</span>
                            </div>
                            <div class="doc-step-desc">
                                Verify student enrollments, onboard faculty professors, manage employee IDs, and allocate subject teaching loads.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Faculty &amp; Student Applications</span>
                                <span><i class="fas fa-check"></i> Output: RBAC User Directory</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #F59E0B;">13</span> Central Timetable &amp; Conflict Resolver
                                </div>
                                <span class="badge badge-primary" style="font-size: 11px;">Scheduling</span>
                            </div>
                            <div class="doc-step-desc">
                                Generate campus-wide master timetables, allocate lecture halls, and automatically detect room or instructor collisions.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Room Capacities &amp; Course Load</span>
                                <span><i class="fas fa-check"></i> Output: Clash-Free Schedule</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #F59E0B;">14</span> Campus Attendance &amp; Performance Audit
                                </div>
                                <span class="badge badge-danger" style="font-size: 11px;">Compliance</span>
                            </div>
                            <div class="doc-step-desc">
                                Audit cross-departmental attendance percentages, inspect grade distribution metrics, and issue formal compliance warnings.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Faculty Attendance Records</span>
                                <span><i class="fas fa-check"></i> Output: Institutional Retention Ledger</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #F59E0B;">15</span> Transcripts, Accreditation &amp; Reports
                                </div>
                                <span class="badge badge-success" style="font-size: 11px;">Accreditation</span>
                            </div>
                            <div class="doc-step-desc">
                                Generate official semester grade cards, calculate cumulative CGPA, and export institutional accreditation data.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Completed Semester Results</span>
                                <span><i class="fas fa-check"></i> Output: Certified Grade Transcripts</span>
                            </div>
                        </div>
                    </div>

                    <!-- Group 4: Super Admin Steps (Shown in Super Admin) -->
                    <div id="group-superadmin-steps" style="display: none; margin-top: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <h4 style="font-size: 14px; font-weight: 700; color: #F87171; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-crown"></i> Super Admin Universal Governance
                            </h4>
                            <span class="badge badge-danger" style="font-size: 11px;">Steps 16 – 20</span>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #EF4444;">16</span> Universal RBAC &amp; Granular Permission Matrix
                                </div>
                                <span class="badge badge-danger" style="font-size: 11px;">Security</span>
                            </div>
                            <div class="doc-step-desc">
                                Control institutional roles (1-4) and configure module permissions across system modules with zero security blindspots.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Role Privilege Definitions</span>
                                <span><i class="fas fa-check"></i> Output: Strict Endpoint RBAC Gates</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #EF4444;">17</span> Active Session Auditing &amp; Force Revocation
                                </div>
                                <span class="badge badge-warning" style="font-size: 11px;">Sessions</span>
                            </div>
                            <div class="doc-step-desc">
                                Monitor authenticated user sessions in real-time across IP addresses and instantly terminate suspicious or compromised tokens.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Token Audit Logs</span>
                                <span><i class="fas fa-check"></i> Output: Session Invalidation</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #EF4444;">18</span> Gemini AI Engine &amp; Token Quota Tuning
                                </div>
                                <span class="badge badge-purple" style="font-size: 11px;">AI Infrastructure</span>
                            </div>
                            <div class="doc-step-desc">
                                Configure Google Gemini API keys, tune model temperature, enforce monthly token quotas, and monitor token consumption.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Gemini API Credentials</span>
                                <span><i class="fas fa-check"></i> Output: Regulated Token Consumption</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #EF4444;">19</span> Encrypted DB Backups &amp; Disaster Recovery
                                </div>
                                <span class="badge badge-success" style="font-size: 11px;">Backups</span>
                            </div>
                            <div class="doc-step-desc">
                                Generate automated or on-demand compressed MySQL database snapshots with SHA-256 integrity checks and one-click rollback.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Manual / Cron Snapshot</span>
                                <span><i class="fas fa-check"></i> Output: Verified .sql.gz Archive</span>
                            </div>
                        </div>

                        <div class="doc-step-item" style="margin-bottom: 12px;">
                            <div class="doc-step-item-header">
                                <div class="doc-step-title">
                                    <span class="doc-step-num" style="background: #EF4444;">20</span> System Health, Error Logs &amp; Audit Inspector
                                </div>
                                <span class="badge badge-info" style="font-size: 11px;">Telemetry</span>
                            </div>
                            <div class="doc-step-desc">
                                Inspect PHP execution times, MySQL slow queries, rate-limiting violations, and tamper-proof security audit streams.
                            </div>
                            <div class="doc-step-meta">
                                <span><i class="fas fa-arrow-right"></i> Input: Telemetry Sensors</span>
                                <span><i class="fas fa-check"></i> Output: 99.98% High Availability</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Live Interactive Demo Sandbox -->
                <div>
                    <div class="doc-demo-box">
                        <div class="doc-demo-topbar">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #EF4444;"></span>
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #F59E0B;"></span>
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #10B981;"></span>
                                <span style="font-size: 12px; font-family: monospace; color: var(--text-muted); margin-left: 8px;">
                                    <i class="fas fa-terminal"></i> Live Role Simulation Sandbox
                                </span>
                            </div>
                            <span class="badge badge-primary" id="demoRoleIndicator" style="font-size: 10.5px;">User Demos Active</span>
                        </div>

                        <!-- Interactive Demo Buttons (Dynamically Populated based on Selected Tier) -->
                        <div class="doc-demo-actions-menu" id="docDemoActionsMenu">
                            <!-- Populated dynamically by selectDocRole() -->
                        </div>

                        <!-- Live Simulation Display -->
                        <div class="doc-demo-screen" id="docDemoScreen">
                            <!-- Initial Screen Content -->
                        </div>

                        <!-- Portal Launch Quick Action -->
                        <div class="doc-portal-launch">
                            <span style="font-size: 12px; color: var(--text-muted);" id="docPortalHint">Ready to test student workflows in production?</span>
                            <a href="<?php echo htmlspecialchars(url('/student/login.php')); ?>" class="btn btn-primary" id="docPortalBtn" style="padding: 6px 14px; font-size: 12.5px;">
                                <i class="fas fa-sign-in-alt"></i> Access Student Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="border-top: 1px solid var(--border-color); padding: 30px 0; background: var(--bg-card); text-align: center;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div style="font-size: 13px; color: var(--text-muted);">
                &copy; <?php echo date('Y'); ?> StudentOS AI Platform. Enterprise Academic Management System.
            </div>
            <div style="display: flex; gap: 16px; align-items: center;">
                <a href="<?php echo htmlspecialchars(url('/index.php')); ?>" target="_blank" rel="noopener noreferrer" style="font-size: 13px; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fas fa-home"></i> Public Index <i class="fas fa-external-link-alt" style="font-size: 9px; opacity: 0.7;"></i>
                </a>
                <a href="<?php echo htmlspecialchars(url('/login.php')); ?>" style="font-size: 13px; color: var(--text-secondary); text-decoration: none;">
                    Portal Sign In
                </a>
            </div>
        </div>
    </footer>

    <!-- Interactive Simulation Script with 16 Multi-Role Demos -->
    <script>
    const demoCatalog = {
        user_rag: {
            role: 'User (Student)',
            roleClass: 'badge-primary',
            title: 'Gemini RAG PDF Q&A & Document Grounding',
            action: 'Query: "Explain B+ Tree Split Conditions from Operating Systems textbook"',
            html: `<div style="background: rgba(99, 102, 241, 0.08); border-left: 3px solid #6366F1; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                     <div style="font-weight: 700; color: #818CF8; margin-bottom: 4px;"><i class="fas fa-search"></i> Vector Context Retrieval (Similarity Score: 0.942)</div>
                     <div style="font-size: 12px; color: var(--text-secondary);">Source: <code>Database_System_Concepts_Silberschatz_Ch14.pdf</code> (Page 642)</div>
                   </div>
                   <div style="line-height: 1.6; font-size: 13px; color: var(--text-primary); margin-bottom: 12px;">
                     A B+ tree node splits when an insertion causes its number of entries to exceed capacity <em>n</em>. The node is divided into two nodes containing <strong>&lceil;n/2&rceil;</strong> and <strong>&lfloor;n/2&rfloor;</strong> entries, with the middle key copied up to the parent directory node.
                   </div>
                   <div style="display: flex; gap: 8px;">
                     <span class="badge badge-success" style="font-size: 10px;"><i class="fas fa-check-circle"></i> Grounded Citation: Page 642</span>
                     <span class="badge badge-purple" style="font-size: 10px;"><i class="fas fa-brain"></i> Gemini 1.5 Pro</span>
                   </div>`
        },
        user_planner: {
            role: 'User (Student)',
            roleClass: 'badge-primary',
            title: 'Dynamic Study Planner & Pomodoro Task Matrix',
            action: 'Synthesizing 7-Day Exam Revision Schedule for Operating Systems',
            html: `<table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px;">
                     <thead>
                       <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-align: left;">
                         <th style="padding: 6px;">Day</th><th>Focus Topic</th><th>Pomodoro Sessions</th><th>Urgency</th>
                       </tr>
                     </thead>
                     <tbody>
                       <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                         <td style="padding: 8px 4px; font-weight: 600;">Mon</td><td>Deadlocks &amp; Banker's Algorithm</td><td>3 × 25 mins</td><td><span class="badge badge-danger" style="font-size: 9px;">High</span></td>
                       </tr>
                       <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                         <td style="padding: 8px 4px; font-weight: 600;">Tue</td><td>Virtual Memory &amp; Page Replacement</td><td>2 × 25 mins</td><td><span class="badge badge-warning" style="font-size: 9px;">Medium</span></td>
                       </tr>
                       <tr>
                         <td style="padding: 8px 4px; font-weight: 600;">Wed</td><td>File Systems &amp; Disk Scheduling</td><td>2 × 25 mins</td><td><span class="badge badge-secondary" style="font-size: 9px;">Normal</span></td>
                       </tr>
                     </tbody>
                   </table>
                   <div style="font-size: 11.5px; color: var(--text-muted);">
                     <i class="fas fa-info-circle"></i> Generated based on your current 68% mock exam score and 4 days until midterms.
                   </div>`
        },
        user_sgpa: {
            role: 'User (Student)',
            roleClass: 'badge-primary',
            title: 'Real-time Weighted SGPA / CGPA Ledger',
            action: 'Computing Semester 5 Projected SGPA across 5 Enrolled Subjects',
            html: `<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                     <div style="background: rgba(99, 102, 241, 0.1); padding: 12px; border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.2);">
                       <div style="font-size: 11px; color: var(--text-muted);">Projected SGPA</div>
                       <div style="font-size: 24px; font-weight: 800; color: #818CF8;">8.92 <span style="font-size: 12px; color: #34D399;">+0.34 &uarr;</span></div>
                     </div>
                     <div style="background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                       <div style="font-size: 11px; color: var(--text-muted);">Cumulative CGPA</div>
                       <div style="font-size: 24px; font-weight: 800; color: #34D399;">8.68 <span style="font-size: 12px; color: var(--text-muted);">/ 10.0</span></div>
                     </div>
                   </div>
                   <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                     Formula: <code>&sum;(Credit_i &times; GradePoint_i) / &sum;Credits</code> = 196.2 / 22 = <strong>8.918 SGPA</strong> (First Class with Distinction).
                   </div>`
        },
        user_quiz: {
            role: 'User (Student)',
            roleClass: 'badge-primary',
            title: 'Active-Recall MCQ Generator & Self-Assessment',
            action: 'Synthesizing Adaptive Practice MCQ for Database Normalization',
            html: `<div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px; margin-bottom: 10px;">
                     <div style="font-weight: 600; font-size: 13px; margin-bottom: 8px; color: var(--text-primary);">
                       Q: A relation is in Boyce-Codd Normal Form (BCNF) if and only if for every non-trivial functional dependency X &rarr; Y:
                     </div>
                     <div style="display: flex; flex-direction: column; gap: 6px; font-size: 12px;">
                       <div style="padding: 6px 10px; border-radius: 4px; background: rgba(16, 185, 129, 0.15); border: 1px solid #10B981; color: #34D399;">
                         <i class="fas fa-check-circle"></i> <strong>A)</strong> X is a superkey of the relation (Correct)
                       </div>
                       <div style="padding: 6px 10px; border-radius: 4px; background: rgba(255,255,255,0.03); color: var(--text-secondary);">
                         <strong>B)</strong> Y is a prime attribute
                       </div>
                       <div style="padding: 6px 10px; border-radius: 4px; background: rgba(255,255,255,0.03); color: var(--text-secondary);">
                         <strong>C)</strong> X contains at least two composite keys
                       </div>
                     </div>
                   </div>
                   <div style="font-size: 11.5px; color: #34D399;"><i class="fas fa-lightbulb"></i> Explanation: In BCNF, every determinant must be a candidate key or superkey.</div>`
        },
        faculty_attendance: {
            role: 'Faculty',
            roleClass: 'badge-success',
            title: 'Daily Batch Attendance Ledger & 75% Threshold Alert',
            action: 'Submitting Class Roll Call for CS501 (38 Students Enrolled)',
            html: `<div style="background: rgba(16, 185, 129, 0.08); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.3); padding: 12px; margin-bottom: 12px;">
                     <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                       <strong style="color: #34D399; font-size: 13px;"><i class="fas fa-clipboard-check"></i> Attendance Session Saved: Sep 7, Period 2</strong>
                       <span class="badge badge-success" style="font-size: 10px;">35 Present / 3 Absent</span>
                     </div>
                     <div style="font-size: 12px; color: var(--text-secondary);">
                       Attendance recorded for Section A. Instant webhook alert dispatched to students falling below 75% attendance criteria.
                     </div>
                   </div>
                   <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; padding: 10px; font-size: 12px; color: #F87171;">
                     <i class="fas fa-exclamation-triangle"></i> <strong>Critical Warning Triggered:</strong> Roll No <code>CS2024-041</code> is at <strong>71.4%</strong> attendance (Threshold: 75.0%). Official shortfall warning dispatched.
                   </div>`
        },
        faculty_grading: {
            role: 'Faculty',
            roleClass: 'badge-success',
            title: 'Assignment Submissions Review & Rubric Scoring',
            action: 'Reviewing Lab Assignment 3: "Thread Synchronization & Semaphores"',
            html: `<div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                     <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: rgba(255,255,255,0.03); border-radius: 6px;">
                       <div><strong>Rahul Sharma</strong> (CS2024-012) — <code>threads_lab.c</code></div>
                       <div><span class="badge badge-success">28 / 30 pts</span></div>
                     </div>
                     <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: rgba(255,255,255,0.03); border-radius: 6px;">
                       <div><strong>Sneha Patel</strong> (CS2024-025) — <code>threads_lab.c</code></div>
                       <div><span class="badge badge-success">30 / 30 pts</span></div>
                     </div>
                     <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: rgba(255,255,255,0.03); border-radius: 6px;">
                       <div><strong>Ananya Roy</strong> (CS2024-039) — <code>threads_lab.c</code></div>
                       <div><span class="badge badge-warning">24 / 30 pts</span></div>
                     </div>
                   </div>
                   <div style="margin-top: 10px; font-size: 11.5px; color: var(--text-muted);">
                     <i class="fas fa-sync-alt"></i> Grades immediately synchronized with student SGPA grade cards.
                   </div>`
        },
        faculty_notice: {
            role: 'Faculty',
            roleClass: 'badge-success',
            title: 'Targeted Class Notice Broadcast',
            action: 'Dispatching Priority Notice to All Students of CS501',
            html: `<div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 12px;">
                     <div style="font-weight: 700; color: #60A5FA; margin-bottom: 4px;">
                       <i class="fas fa-bullhorn"></i> Midterm Exam Pattern &amp; Extra Doubt Session
                     </div>
                     <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 8px;">
                       "The midterm examination next Tuesday will cover Modules 1–3 (CPU Scheduling, Synchronization, Memory Management). An open doubt session is arranged in LH-204 this Friday at 4 PM."
                     </div>
                     <div style="font-size: 11px; color: var(--text-muted);">
                       Audience: <strong>38 Enrolled Students (CS501 - Operating Systems)</strong> &bull; Channels: In-App, Email Notification
                     </div>
                   </div>`
        },
        faculty_analytics: {
            role: 'Faculty',
            roleClass: 'badge-success',
            title: 'Cohort Grade Distribution & Risk Matrix',
            action: 'Generating Performance Histogram for Midterm Examination 1',
            html: `<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px; text-align: center;">
                     <div style="background: rgba(16, 185, 129, 0.1); padding: 10px; border-radius: 6px;">
                       <div style="font-size: 20px; font-weight: 700; color: #34D399;">76.8%</div>
                       <div style="font-size: 10.5px; color: var(--text-muted);">Class Average</div>
                     </div>
                     <div style="background: rgba(99, 102, 241, 0.1); padding: 10px; border-radius: 6px;">
                       <div style="font-size: 20px; font-weight: 700; color: #818CF8;">98.0%</div>
                       <div style="font-size: 10.5px; color: var(--text-muted);">Highest Score</div>
                     </div>
                     <div style="background: rgba(245, 158, 11, 0.1); padding: 10px; border-radius: 6px;">
                       <div style="font-size: 20px; font-weight: 700; color: #FBBF24;">4</div>
                       <div style="font-size: 10.5px; color: var(--text-muted);">At-Risk Students (&lt;50%)</div>
                     </div>
                   </div>
                   <div style="font-size: 11.5px; color: var(--text-secondary);">
                     <i class="fas fa-robot" style="color: var(--ai-accent);"></i> AI Recommendation: Recommend scheduling peer tutoring for Module 2 deadlock questions where error frequency was 44%.
                   </div>`
        },
        admin_course: {
            role: 'Admin',
            roleClass: 'badge-warning',
            title: 'Department, Course & Semester Program Configuration',
            action: 'Configuring B.Tech in Artificial Intelligence & Data Science',
            html: `<div style="background: rgba(245, 158, 11, 0.08); border-left: 3px solid #F59E0B; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                     <div style="font-weight: 700; color: #FBBF24; font-size: 13px; margin-bottom: 4px;">
                       <i class="fas fa-layer-group"></i> Degree Program: B.Tech AI &amp; DS (Code: AIDS-2026)
                     </div>
                     <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                       • Department: <strong>Computer Science &amp; Engineering</strong><br>
                       • Duration: <strong>4 Years (8 Semesters)</strong> | Total Required Credits: <strong>160</strong><br>
                       • Status: <span class="badge badge-success" style="font-size: 10px;">Active Catalog &amp; Open for Admissions</span>
                     </div>
                   </div>
                   <div style="font-size: 11.5px; color: var(--text-muted);">
                     <i class="fas fa-check"></i> Course structure published and available in student registration dropdown.
                   </div>`
        },
        admin_faculty_assign: {
            role: 'Admin',
            roleClass: 'badge-warning',
            title: 'Faculty Course Allocation & Workload Balancer',
            action: 'Assigning Prof. Rajesh Menon to CS501 & CS502',
            html: `<div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                     <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                       <strong>Prof. Rajesh Menon (EMP-204)</strong>
                       <span class="badge badge-primary">Associate Professor</span>
                     </div>
                     <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                       • Allocated Subject 1: <strong>Operating Systems (CS501)</strong> — 4 Credits (Section A)<br>
                       • Allocated Subject 2: <strong>Systems Programming Lab (CS502)</strong> — 2 Credits<br>
                       • Current Weekly Teaching Load: <strong>14 Hours / 18 Hours Max</strong> (Healthy)
                     </div>
                   </div>`
        },
        admin_timetable: {
            role: 'Admin',
            roleClass: 'badge-warning',
            title: 'Master Campus Timetable & Collision Detection',
            action: 'Resolving Classroom Allocation Conflict for LH-102',
            html: `<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; padding: 12px; font-size: 12px; line-height: 1.5; color: var(--text-primary);">
                     <div style="color: #34D399; font-weight: 700; margin-bottom: 4px;">
                       <i class="fas fa-check-double"></i> Automated Clash Resolution Applied
                     </div>
                     Detected collision between <strong>ME301</strong> and <strong>EE302</strong> in LH-102 (Wed 10:00 AM).<br>
                     Engine re-routed <strong>EE302</strong> to <strong>Audi-2 (Capacity: 120)</strong>. Timetable verified clash-free across 42 sections.
                   </div>`
        },
        admin_audit: {
            role: 'Admin',
            roleClass: 'badge-warning',
            title: 'Campus Attendance & Compliance Retention Audit',
            action: 'Running Institution-Wide Attendance Health Verification',
            html: `<div style="font-size: 12.5px; line-height: 1.6; color: var(--text-secondary);">
                     <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                       <span>Computer Science &amp; Eng:</span> <strong style="color: #34D399;">84.2% Avg Attendance</strong>
                     </div>
                     <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                       <span>Electronics &amp; Comm:</span> <strong style="color: #34D399;">81.6% Avg Attendance</strong>
                     </div>
                     <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                       <span>Mechanical Engineering:</span> <strong style="color: #FBBF24;">76.1% Avg Attendance</strong>
                     </div>
                   </div>
                   <div style="margin-top: 10px; font-size: 11px; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 8px;">
                     Total Students Audited: <strong>1,840</strong> | Mandatory Warning Threshold: <strong>75.0%</strong>
                   </div>`
        },
        super_rbac: {
            role: 'Super Admin',
            roleClass: 'badge-danger',
            title: 'Universal RBAC & Granular Permission Matrix Control',
            action: 'Inspecting System Roles (1-4) & Security Endpoints',
            html: `<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 11.5px; margin-bottom: 12px;">
                     <div style="padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; border: 1px solid rgba(239, 68, 68, 0.3);">
                       <strong style="color: #F87171;">Role 1: Super Admin</strong><br>
                       Universal Root Privileges, Schema Migrations, API Keys, DB Backups
                     </div>
                     <div style="padding: 8px; background: rgba(245, 158, 11, 0.1); border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.3);">
                       <strong style="color: #FBBF24;">Role 2: Admin</strong><br>
                       Dept Management, Timetables, Faculty Allocation, Approvals
                     </div>
                     <div style="padding: 8px; background: rgba(16, 185, 129, 0.1); border-radius: 4px; border: 1px solid rgba(16, 185, 129, 0.3);">
                       <strong style="color: #34D399;">Role 3: Faculty</strong><br>
                       Attendance, Assignment Grading, Exams, Cohort Announcements
                     </div>
                     <div style="padding: 8px; background: rgba(99, 102, 241, 0.1); border-radius: 4px; border: 1px solid rgba(99, 102, 241, 0.3);">
                       <strong style="color: #818CF8;">Role 4: Student (User)</strong><br>
                       RAG Q&amp;A, Study Planner, Exam Prep, SGPA Calculation
                     </div>
                   </div>
                   <div style="font-size: 11px; color: #34D399;"><i class="fas fa-lock"></i> Zero trust architecture enforced across all 46 API endpoints.</div>`
        },
        super_session_kill: {
            role: 'Super Admin',
            roleClass: 'badge-danger',
            title: 'Live Active Session Inspector & Emergency Revocation',
            action: 'Revoking Suspicious Session Token across IP 192.168.1.182',
            html: `<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                     <div style="color: #F87171; font-weight: 700; margin-bottom: 4px;">
                       <i class="fas fa-ban"></i> Session Token Terminated &amp; Blacklisted
                     </div>
                     <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                       • User ID: <strong>usr_9042</strong> | Role: <strong>Faculty (Role 3)</strong><br>
                       • Client IP: <code>192.168.1.182</code> (Geolocation: Unrecognized Device)<br>
                       • Action: Auth token purged from Redis &amp; MySQL session cache.<br>
                       • User redirected to login screen with Security Alert #SEC-992.
                     </div>
                   </div>`
        },
        super_ai_tune: {
            role: 'Super Admin',
            roleClass: 'badge-danger',
            title: 'Google Gemini AI Model, Temperature & Token Quota Tuning',
            action: 'Configuring Gemini 1.5 Pro with Institutional Cost Cap',
            html: `<div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                     <div style="font-weight: 700; color: #A78BFA; margin-bottom: 6px;">
                       <i class="fas fa-brain"></i> Institutional AI Core Configuration
                     </div>
                     <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                       • Primary Foundation Model: <strong>Google Gemini 1.5 Pro</strong><br>
                       • Inference Temperature: <strong>0.35</strong> (Optimized for Academic Accuracy)<br>
                       • Top-P Sampling: <strong>0.92</strong> | Max Context Window: <strong>1,000,000 Tokens</strong><br>
                       • Monthly Institution Quota: <strong>5,000,000 Tokens</strong> (Consumed: 22.4%)
                     </div>
                   </div>`
        },
        super_backup: {
            role: 'Super Admin',
            roleClass: 'badge-danger',
            title: 'Encrypted Database Backup & Disaster Recovery',
            action: 'Executing Encrypted Snapshot of studentos_ai Database',
            html: `<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                     <div style="font-weight: 700; color: #34D399; margin-bottom: 4px;">
                       <i class="fas fa-database"></i> Full Database Snapshot Verified &amp; Encrypted
                     </div>
                     <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                       • Archive: <code>studentos_backup_20260907_2115.sql.gz</code><br>
                       • Tables Backed Up: <strong>32 Tables (811 Columns, 14,200 Rows)</strong><br>
                       • Checksum: <code>e8b9...c34a (SHA-256 Verified)</code><br>
                       • Size: <strong>14.8 MB</strong> | Storage: AES-256 Encrypted Local &amp; S3 Mirror
                     </div>
                   </div>`
        }
    };

    const roleConfig = {
        student: {
            title: 'Learner Tier Documentation Scope',
            summary: 'Showcasing student learning lifecycle steps and interactive AI study &amp; grade calculation demos.',
            pills: '<span class="doc-scope-pill active-pill"><i class="fas fa-user-graduate"></i> User Demos (5 Steps)</span>',
            indicator: 'User Demos Active',
            portalHint: 'Ready to test student workflows in production?',
            portalBtn: '<i class="fas fa-sign-in-alt"></i> Access Student Portal',
            portalUrl: '<?php echo htmlspecialchars(url('/student/login.php')); ?>',
            groups: { student: true, faculty: false, admin: false, superadmin: false },
            demos: ['user_rag', 'user_planner', 'user_sgpa', 'user_quiz'],
            defaultDemo: 'user_rag'
        },
        faculty: {
            title: 'Faculty Operational Tier Scope',
            summary: 'Showcasing dual-tier workflow: both User (Student) learning tools AND Faculty instructional management across 10 operational steps.',
            pills: '<span class="doc-scope-pill active-pill"><i class="fas fa-chalkboard-teacher"></i> Faculty Demos (5 Steps)</span> <span class="doc-scope-pill inherited-pill"><i class="fas fa-user-graduate"></i> Inherited: User Demos (5 Steps)</span>',
            indicator: 'User + Faculty Demos Active',
            portalHint: 'Ready to manage courses, attendance, and exam grading?',
            portalBtn: '<i class="fas fa-chalkboard-teacher"></i> Access Faculty Portal',
            portalUrl: '<?php echo htmlspecialchars(url('/faculty/login.php')); ?>',
            groups: { student: true, faculty: true, admin: false, superadmin: false },
            demos: ['faculty_attendance', 'faculty_grading', 'faculty_notice', 'faculty_analytics', 'user_rag', 'user_planner'],
            defaultDemo: 'faculty_attendance'
        },
        admin: {
            title: 'University Admin Governance Scope',
            summary: 'Showcasing three-tier workflow: User (Student), Faculty, and Admin institutional governance across 15 comprehensive operational steps.',
            pills: '<span class="doc-scope-pill active-pill"><i class="fas fa-shield-alt"></i> Admin Demos (5 Steps)</span> <span class="doc-scope-pill inherited-pill"><i class="fas fa-chalkboard-teacher"></i> Inherited: Faculty Demos</span> <span class="doc-scope-pill inherited-pill"><i class="fas fa-user-graduate"></i> Inherited: User Demos</span>',
            indicator: 'User + Faculty + Admin Demos Active',
            portalHint: 'Ready to oversee university departments, schedules, and compliance?',
            portalBtn: '<i class="fas fa-shield-alt"></i> Access Admin Portal',
            portalUrl: '<?php echo htmlspecialchars(url('/admin/login.php')); ?>',
            groups: { student: true, faculty: true, admin: true, superadmin: false },
            demos: ['admin_course', 'admin_faculty_assign', 'admin_timetable', 'admin_audit', 'faculty_attendance', 'user_rag'],
            defaultDemo: 'admin_course'
        },
        superadmin: {
            title: 'Super Admin Universal Scope',
            summary: 'Universal Access: Complete system governance encompassing User (Student), Faculty, Admin, and Super Admin infrastructure controls across all 20 lifecycle steps.',
            pills: '<span class="doc-scope-pill active-pill"><i class="fas fa-crown"></i> Super Admin (5 Steps)</span> <span class="doc-scope-pill inherited-pill"><i class="fas fa-shield-alt"></i> Admin</span> <span class="doc-scope-pill inherited-pill"><i class="fas fa-chalkboard-teacher"></i> Faculty</span> <span class="doc-scope-pill inherited-pill"><i class="fas fa-user-graduate"></i> User</span>',
            indicator: 'Universal (All 4 Tiers) Active',
            portalHint: 'Ready for root security management and system-wide controls?',
            portalBtn: '<i class="fas fa-crown"></i> Access Super Admin Portal',
            portalUrl: '<?php echo htmlspecialchars(url('/super-admin/login.php')); ?>',
            groups: { student: true, faculty: true, admin: true, superadmin: true },
            demos: ['super_rbac', 'super_session_kill', 'super_ai_tune', 'super_backup', 'admin_timetable', 'faculty_attendance', 'user_rag'],
            defaultDemo: 'super_rbac'
        }
    };

    function selectDocRole(roleKey) {
        const cfg = roleConfig[roleKey];
        if (!cfg) return;

        // Update URL query parameter cleanly without reloading page
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('role', roleKey);
            window.history.replaceState({}, '', url);
        } catch (e) {}

        // Update active tab buttons
        document.querySelectorAll('.doc-role-tab').forEach(t => t.classList.remove('active'));
        const activeTab = document.getElementById('tab-' + roleKey);
        if (activeTab) activeTab.classList.add('active');

        // Update scope header and pills
        document.getElementById('docScopeTitle').innerHTML = '<i class="fas fa-layer-group" style="color: var(--primary);"></i> <span>' + cfg.title + '</span>';
        document.getElementById('docScopeSummary').innerHTML = cfg.summary;
        document.getElementById('docScopePills').innerHTML = cfg.pills;
        document.getElementById('demoRoleIndicator').innerText = cfg.indicator;

        // Update portal launch action
        document.getElementById('docPortalHint').innerText = cfg.portalHint;
        const portalBtn = document.getElementById('docPortalBtn');
        portalBtn.innerHTML = cfg.portalBtn;
        portalBtn.href = cfg.portalUrl;

        // Toggle visibility of step groups
        document.getElementById('group-student-steps').style.display = cfg.groups.student ? 'block' : 'none';
        document.getElementById('group-faculty-steps').style.display = cfg.groups.faculty ? 'block' : 'none';
        document.getElementById('group-admin-steps').style.display = cfg.groups.admin ? 'block' : 'none';
        document.getElementById('group-superadmin-steps').style.display = cfg.groups.superadmin ? 'block' : 'none';

        // Populate action buttons in live demo sandbox
        const actionsMenu = document.getElementById('docDemoActionsMenu');
        actionsMenu.innerHTML = '';
        cfg.demos.forEach((demoKey, idx) => {
            const d = demoCatalog[demoKey];
            if (!d) return;
            const btn = document.createElement('button');
            btn.className = 'doc-demo-btn' + (idx === 0 ? ' active' : '');
            btn.innerHTML = `<span class="badge ${d.roleClass}" style="font-size: 9.5px; padding: 1px 5px;">${d.role}</span> ${d.title.split(' ')[0]} ${d.title.split(' ')[1] || ''}`;
            btn.onclick = () => runDocDemo(demoKey, btn);
            actionsMenu.appendChild(btn);
        });

        // Run default demo for this tier
        runDocDemo(cfg.defaultDemo);
    }

    function runDocDemo(demoKey, btnEl) {
        const d = demoCatalog[demoKey];
        if (!d) return;

        if (btnEl) {
            document.querySelectorAll('.doc-demo-btn').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');
        }

        const screen = document.getElementById('docDemoScreen');
        screen.innerHTML = `
            <div style="padding: 10px 0; color: var(--text-muted); font-family: monospace; font-size: 12px; border-bottom: 1px solid var(--border-color); margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                <span><i class="fas fa-terminal" style="color: var(--primary);"></i> Executing: <strong>${escapeHTML(d.action)}</strong></span>
                <span class="badge ${d.roleClass}">${d.role}</span>
            </div>
            <div style="margin-bottom: 12px; font-size: 15px; font-weight: 700; color: var(--text-primary);">
                ${d.title}
            </div>
            ${d.html}
        `;
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    // Initialize with requested role from server
    document.addEventListener('DOMContentLoaded', function() {
        const initialRole = "<?php echo addslashes($requestedRole); ?>";
        selectDocRole(initialRole || 'student');
    });
    </script>
</body>
</html>
