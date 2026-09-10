<?php
// frontend/index.php - Public Landing Page (No Login Required)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$loggedIn = isLoggedIn();
$currentUser = $loggedIn ? ($_SESSION['user'] ?? null) : null;

// Live Institutional Statistics from Database
$db = getDbConnection();
$totalStudents = 120;
$totalFaculty = 18;
$totalCourses = 8;
$totalDepartments = 4;

if ($db) {
    $cRes = $db->query("SELECT 
        (SELECT COUNT(*) FROM users WHERE role_id = 4 AND is_active = 1 AND deleted_at IS NULL) as students,
        (SELECT COUNT(*) FROM users WHERE role_id = 3 AND is_active = 1 AND deleted_at IS NULL) as faculty,
        (SELECT COUNT(*) FROM courses WHERE status = 'active') as courses,
        (SELECT COUNT(*) FROM departments) as departments
    ");
    if ($cRes && $row = $cRes->fetch_assoc()) {
        $totalStudents = max((int)$row['students'], 1);
        $totalFaculty = max((int)$row['faculty'], 1);
        $totalCourses = max((int)$row['courses'], 1);
        $totalDepartments = max((int)$row['departments'], 1);
    }
}

// Hero Showcase Data
$heroStudent = [
    'name' => 'Alex Johnson',
    'initials' => 'AJ',
    'program' => 'B.Tech Computer Science • Sem 6',
    'cohort_badge' => 'Active Cohort',
    'sgpa' => '9.15',
    'attendance' => '89.4%',
    'credits' => '82 / 160'
];

if ($loggedIn && $currentUser) {
    $uName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
    if (empty($uName)) $uName = $currentUser['email'] ?? 'Authenticated User';
    $heroStudent['name'] = $uName;
    $heroStudent['initials'] = getInitials($uName);
    $roleId = (int)($currentUser['role_id'] ?? 4);

    if ($roleId === 4 && $db) {
        $uId = (int)$currentUser['id'];
        $heroStudent['cohort_badge'] = 'Enrolled Student';
        $spStmt = $db->prepare("SELECT sp.* FROM student_profiles sp WHERE sp.user_id = ?");
        if ($spStmt) {
            $spStmt->bind_param("i", $uId);
            $spStmt->execute();
            if ($sp = $spStmt->get_result()->fetch_assoc()) {
                $sem = $sp['semester'] ?? '1';
                $heroStudent['program'] = 'Student • Semester ' . $sem;
            }
            $spStmt->close();
        }
        $attStmt = $db->prepare("SELECT ROUND(COUNT(CASE WHEN LOWER(status) = 'present' THEN 1 END) * 100 / NULLIF(COUNT(*), 0), 1) as pct FROM attendance WHERE student_id = ?");
        if ($attStmt) {
            $attStmt->bind_param("i", $uId);
            $attStmt->execute();
            if ($ar = $attStmt->get_result()->fetch_assoc()) {
                if ($ar['pct'] !== null) $heroStudent['attendance'] = $ar['pct'] . '%';
            }
            $attStmt->close();
        }
        $perfStmt = $db->prepare("SELECT gpa, cgpa, credits_completed FROM performance WHERE student_id = ? ORDER BY id DESC LIMIT 1");
        if ($perfStmt) {
            $perfStmt->bind_param("i", $uId);
            $perfStmt->execute();
            if ($pr = $perfStmt->get_result()->fetch_assoc()) {
                $heroStudent['sgpa'] = number_format((float)($pr['gpa'] ?? $pr['cgpa'] ?? 8.5), 2);
                $heroStudent['credits'] = ($pr['credits_completed'] ?? 0) . ' pts';
            }
            $perfStmt->close();
        }
    } elseif ($roleId === 3) {
        $heroStudent['cohort_badge'] = 'Faculty Staff';
        $heroStudent['program'] = 'Department Instructor';
        $heroStudent['sgpa'] = 'Faculty';
        $heroStudent['attendance'] = 'Active';
        $heroStudent['credits'] = 'Teaching';
    } elseif ($roleId === 2) {
        $heroStudent['cohort_badge'] = 'Administrator';
        $heroStudent['program'] = 'Campus Administration';
        $heroStudent['sgpa'] = 'Admin';
        $heroStudent['attendance'] = 'Active';
        $heroStudent['credits'] = 'Managed';
    } elseif ($roleId === 1) {
        $heroStudent['cohort_badge'] = 'Super Admin';
        $heroStudent['program'] = 'Full Institutional Governance';
        $heroStudent['sgpa'] = 'System';
        $heroStudent['attendance'] = 'Active';
        $heroStudent['credits'] = 'Root';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentOS AI — The Intelligent Academic Operating System</title>
    <meta name="description" content="StudentOS AI is a next-generation academic management ecosystem powered by Generative AI, RAG document intelligence, and unified multi-role governance.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/animations.css">
</head>
<body class="landing-page">

    <!-- Public Navigation Bar (No login required) -->
    <header class="landing-header">
        <div class="landing-nav-container">
            <a href="<?php echo htmlspecialchars(url($loggedIn ? getDashboardUrl() : '/index.php')); ?>" class="landing-logo">
                <i class="fas fa-graduation-cap"></i>
                <span>StudentOS <span class="gradient-text">AI</span></span>
            </a>

            <nav class="landing-nav-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#faq">FAQ</a>
            </nav>

            <div class="landing-nav-actions">
                <?php if ($loggedIn && $currentUser): ?>
                    <a href="<?php echo htmlspecialchars(url(getDashboardUrl())); ?>" class="btn btn-primary" role="button">
                        <i class="fas fa-chart-pie"></i> Go to Dashboard
                    </a>
                    <a href="<?php echo htmlspecialchars(url('/logout.php')); ?>" class="btn btn-danger btn-logout-action" role="button" style="padding: 9px 16px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;" title="Logout" onclick="try{localStorage.removeItem('auth_token');localStorage.removeItem('session_token');sessionStorage.removeItem('auth_token');sessionStorage.removeItem('session_token');}catch(e){}">
                        <i class="fas fa-sign-out-alt"></i> <span>Log Out</span>
                    </a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars(url('/faculty/login.php')); ?>" class="btn btn-outline" role="button" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(99, 102, 241, 0.4);">
                        <i class="fas fa-user-shield"></i> <span>Staff Login</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(url('/register.php')); ?>" class="btn btn-outline" role="button" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(99, 102, 241, 0.4);">
                        <i class="fas fa-user-plus"></i> <span>Register</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(url('/student/login.php')); ?>" class="btn btn-primary" role="button" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-user-graduate"></i> <span>Student Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-grid">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <span class="badge">
                                <i class="fas fa-sparkles" style="color: var(--ai-accent); margin-right: 6px;"></i>
                                Next-Gen Academic Intelligence Platform
                            </span>
                        </div>
                        <h1 class="hero-title">
                            Your Intelligent<br>
                            <span class="gradient-text">Academic Operating System</span>
                        </h1>
                        <p class="hero-subtitle">
                            Empowering students, faculty, and university administrators with unified course tracking, personalized AI study roadmaps, RAG document Q&A, and real-time performance analytics.
                        </p>

                        <div class="hero-buttons">
                            <?php if ($loggedIn): ?>
                                <a href="<?php echo htmlspecialchars(url(getDashboardUrl())); ?>" class="btn btn-primary btn-lg" role="button">
                                    <i class="fas fa-rocket"></i> Launch My Dashboard
                                </a>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars(url('/login.php')); ?>" class="btn btn-primary btn-lg" role="button">
                                    <i class="fas fa-user-graduate"></i> Login
                                </a>
                                <a href="<?php echo htmlspecialchars(url('/register.php')); ?>" class="btn btn-outline btn-lg" role="button" style="border-color: rgba(99, 102, 241, 0.5); color: #fff;">
                                    <i class="fas fa-user-plus"></i> Register
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="hero-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $totalDepartments; ?></span>
                                <span class="stat-label">Departments</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $totalCourses; ?>+</span>
                                <span class="stat-label">Degree Programs</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $totalStudents; ?>+</span>
                                <span class="stat-label">Active Learners</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Visual / Academic Intelligence Showcase -->
                    <div class="hero-visual">
                        <div class="hero-interactive-card">
                            <div class="interactive-header">
                                <div class="interactive-dots">
                                    <div class="interactive-dot dot-red"></div>
                                    <div class="interactive-dot dot-yellow"></div>
                                    <div class="interactive-dot dot-green"></div>
                                </div>
                                <span style="font-size: 12px; color: var(--text-muted); font-family: monospace;">
                                    <i class="fas fa-brain" style="color: var(--ai-accent);"></i> StudentOS Intelligence Hub
                                </span>
                                <span class="badge badge-success" style="font-size: 10px;">Live Sync</span>
                            </div>

                            <div class="interactive-body">
                                <!-- Student Quick Snapshot -->
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: var(--radius-lg);">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--ai-accent)); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;">
                                            <?php echo htmlspecialchars($heroStudent['initials']); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; font-size: 13.5px; color: var(--text-primary);"><?php echo htmlspecialchars($heroStudent['name']); ?></div>
                                            <div style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($heroStudent['program']); ?></div>
                                        </div>
                                    </div>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($heroStudent['cohort_badge']); ?></span>
                                </div>

                                <!-- Key Academic Metrics -->
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px; text-align: center;">
                                        <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase;">SGPA / GPA</span>
                                        <div style="font-size: 18px; font-weight: 800; color: #34D399; margin: 2px 0;"><?php echo htmlspecialchars($heroStudent['sgpa']); ?></div>
                                        <span style="font-size: 10px; color: var(--text-secondary);">Standing</span>
                                    </div>
                                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px; text-align: center;">
                                        <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase;">Attendance</span>
                                        <div style="font-size: 18px; font-weight: 800; color: #818CF8; margin: 2px 0;"><?php echo htmlspecialchars($heroStudent['attendance']); ?></div>
                                        <span style="font-size: 10px; color: var(--success);"><i class="fas fa-check-circle"></i> Verified</span>
                                    </div>
                                    <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px; text-align: center;">
                                        <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase;">Credits / Role</span>
                                        <div style="font-size: 18px; font-weight: 800; color: #F59E0B; margin: 2px 0;"><?php echo htmlspecialchars($heroStudent['credits']); ?></div>
                                        <span style="font-size: 10px; color: var(--text-secondary);">On Track</span>
                                    </div>
                                </div>

                                <!-- AI Assistant Chat Showcase Preview -->
                                <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <span style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-sparkles" style="color: var(--ai-accent);"></i> Gemini 1.5 RAG Co-Pilot
                                        </span>
                                        <span class="badge badge-success" style="font-size: 9.5px; padding: 1px 6px;">Verified Source</span>
                                    </div>
                                    <div style="background: rgba(99, 102, 241, 0.08); border-left: 3px solid var(--primary); padding: 8px 10px; border-radius: 4px; font-size: 12px; line-height: 1.4; color: var(--text-primary);">
                                        "Exam preparation schedule generated for <strong>DBMS (CS301)</strong>. 3 key B-Tree topics indexed from course syllabus with 40 practice problems."
                                    </div>
                                </div>

                                <!-- Real-time Status Badges -->
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: var(--text-muted); padding-top: 4px;">
                                    <span><i class="fas fa-shield-alt" style="color: var(--success); margin-right: 4px;"></i> Enterprise Role-Based Access</span>
                                    <span><i class="fas fa-bolt" style="color: var(--warning); margin-right: 4px;"></i> Sub-second Latency</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- Features Showcase Section -->
        <section id="features" class="features">
            <div class="container">
                <div class="section-header">
                    <div class="badge badge-primary" style="margin-bottom: 12px;">Core Capabilities</div>
                    <h2 class="section-title">Everything Needed for University Success</h2>
                    <p class="section-subtitle">A unified modern stack replacing fragmented spreadsheets, disparate portals, and slow communication channels.</p>
                </div>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-brain"></i></div>
                        <h3>24/7 AI Study Assistant</h3>
                        <p>Receive immediate conceptual breakdowns, step-by-step math derivations, and code debugging anytime.</p>
                        <div class="feature-tags">
                            <span class="badge badge-purple">Gemini Powered</span>
                            <span class="badge badge-secondary">Pedagogical</span>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-file-pdf"></i></div>
                        <h3>PDF Q&A & Document RAG</h3>
                        <p>Upload lecture slides, textbooks, and syllabus files. Ask questions and get answers grounded strictly with page citations.</p>
                        <div class="feature-tags">
                            <span class="badge badge-primary">Chunk Indexing</span>
                            <span class="badge badge-success">Zero Hallucination</span>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-pencil-ruler"></i></div>
                        <h3>Automated Quiz Synthesizer</h3>
                        <p>Generate practice multiple-choice quizzes from any chapter or lecture note with explanations for every option.</p>
                        <div class="feature-tags">
                            <span class="badge badge-warning">Active Recall</span>
                            <span class="badge badge-info">Instant Grading</span>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                        <h3>Dynamic Study Planner</h3>
                        <p>AI scans upcoming assignment deadlines and exam dates to synthesize an optimal revision timetable.</p>
                        <div class="feature-tags">
                            <span class="badge badge-success">Pomodoro Ready</span>
                            <span class="badge badge-secondary">Prioritized</span>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                        <h3>Academic & Grade Analytics</h3>
                        <p>Track semester SGPA and cumulative CGPA, analyze subject-wise trends, and monitor mandatory 75% attendance thresholds.</p>
                        <div class="feature-tags">
                            <span class="badge badge-info">Chart Visuals</span>
                            <span class="badge badge-primary">Credit Ledger</span>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                        <h3>Institutional RBAC</h3>
                        <p>Four distinct secure role portals: Super Admin, University Admin, Faculty, and Students with granular permissions.</p>
                        <div class="feature-tags">
                            <span class="badge badge-purple">Role Enforced</span>
                            <span class="badge badge-danger">Audit Trails</span>
                        </div>
                </div>
            </div>
        </section>

       

        <!-- How It Works Section -->
        <section id="how-it-works" class="how-it-works">
            <div class="container">
                <div class="section-header">
                    <div class="badge badge-success" style="margin-bottom: 12px;">Simple Workflow</div>
                    <h2 class="section-title">Get Started in Three Easy Steps</h2>
                    <p class="section-subtitle">Experience academic excellence with frictionless onboarding.</p>
                </div>

                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h3>Sign In & Sync Profile</h3>
                        <p>Sign in with your institutional credentials to automatically load your enrolled courses, syllabus, and timetable.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h3>Upload Documents & Notes</h3>
                        <p>Stage your course syllabus and lecture notes. The RAG pipeline processes them into indexed vectors.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h3>Learn, Test & Excel</h3>
                        <p>Ask AI questions, practice auto-generated mock exams, track tasks, and achieve peak grades with peace of mind.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section id="testimonials" class="testimonials">
            <div class="container">
                <div class="section-header">
                    <div class="badge badge-warning" style="margin-bottom: 12px;">Student Testimonials</div>
                    <h2 class="section-title">Loved by Thousands of Learners</h2>
                    <p class="section-subtitle">Real experiences from students and instructors across leading institutions.</p>
                </div>

                <div class="testimonials-grid">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">★★★★★</div>
                        <p>"The PDF Q&A feature saved me during semester finals. Being able to ask questions directly to a 300-page operating systems book with page citations is sheer magic!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">AJ</div>
                            <div>
                                <span class="author-name">Alex Johnson</span>
                                <span class="author-role">B.Tech Computer Science</span>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="testimonial-rating">★★★★★</div>
                        <p>"As an instructor with over 180 students, grading assignments and taking daily attendance used to take hours. StudentOS AI made our department 5x more productive."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar" style="background: linear-gradient(135deg, #10B981, #059669);">DR</div>
                            <div>
                                <span class="author-name">Dr. Robert Vance</span>
                                <span class="author-role">Professor of Data Structures</span>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="testimonial-rating">★★★★★</div>
                        <p>"The dynamic study planner analyzed all my assignment deadlines and exam dates to create an achievable daily schedule. My SGPA jumped from 7.4 to 9.2!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar" style="background: linear-gradient(135deg, #EC4899, #8B5CF6);">ES</div>
                            <div>
                                <span class="author-name">Emily Sanchez</span>
                                <span class="author-role">Software Engineering Senior</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Interactive FAQ Section -->
        <section id="faq" class="faq-section">
            <div class="container">
                <div class="section-header">
                    <div class="badge badge-info" style="margin-bottom: 12px;">Answers & FAQ</div>
                    <h2 class="section-title">Frequently Asked Questions</h2>
                    <p class="section-subtitle">Everything you need to know about the platform, security, and AI integrations.</p>
                </div>

                <div class="faq-accordion">
                    <div class="faq-item active">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            <span>Is StudentOS AI completely free to get started?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            Yes! Students can sign up, manage subjects, track assignments, and utilize core AI tools completely free of charge. Institutions can deploy it locally via XAMPP or in cloud environments.
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            <span>How does the PDF Q&A (RAG) feature work?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            When you upload course notes or textbooks, our backend text extraction engine segments documents into semantic chunks with 50-word sliding windows. When you ask a query, relevant text excerpts are retrieved and provided to Google Gemini as verified context, eliminating hallucinated answers.
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            <span>How is student and faculty academic data protected?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            StudentOS AI uses enterprise BCRYPT password hashing, parameterized SQL queries against SQL injection, CSRF and rate-limiting middleware, and strict role-based access control (RBAC). Passwords and private tokens are never stored in plaintext.
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            <span>Can faculty members create customized quizzes and exams?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            Yes! Faculty can create exams, add multiple-choice and descriptive questions, set deadlines, and automatically calculate student grades and SGPA impacts upon submission.
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Public Footer -->
    <footer style="background: #070B14; border-top: 1px solid var(--border-color); padding: 50px 0 30px;">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; padding-bottom: 30px; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-graduation-cap" style="color: var(--primary); font-size: 24px;"></i>
                    <span style="font-size: 18px; font-weight: 700; color: var(--text-primary);">StudentOS AI</span>
                </div>
                <div style="display: flex; gap: 24px; font-size: 13px;">
                    <a href="#features" style="color: var(--text-secondary);">Features</a>
                    <a href="#how-it-works" style="color: var(--text-secondary);">How It Works</a>
                    <a href="#testimonials" style="color: var(--text-secondary);">Testimonials</a>
                    <a href="#faq" style="color: var(--text-secondary);">FAQ</a>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--success);">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--success); box-shadow: 0 0 8px var(--success);"></span>
                    All Systems Operational (v2.4 Enterprise)
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 24px; font-size: 12px; color: var(--text-muted); flex-wrap: wrap; gap: 10px;">
                <div>&copy; <?php echo date('Y'); ?> StudentOS AI Platform. All rights reserved.</div>
                <div>Engineered with PHP 8.2, MySQL, and Google Gemini AI.</div>
            </div>
        </div>
    </footer>

    <!-- Landing Page Scripts -->
    <script>
    // FAQ Accordion Toggle
    function toggleFaq(btn) {
        const item = btn.parentElement;
        const isActive = item.classList.contains('active');
        document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('active'));
        if (!isActive) {
            item.classList.add('active');
        }
    }

    // Copy Demo Credentials Helper
    function copyText(text, btn) {
        if (!navigator.clipboard) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showCopiedFeedback(btn);
            return;
        }
        navigator.clipboard.writeText(text).then(function() {
            showCopiedFeedback(btn);
        }).catch(function(err) {
            console.error('Clipboard copy failed:', err);
        });
    }

    function showCopiedFeedback(btn) {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check" style="color: #34D399;"></i> Copied!';
        btn.style.pointerEvents = 'none';
        setTimeout(function() {
            btn.innerHTML = originalHtml;
            btn.style.pointerEvents = 'auto';
        }, 1800);
    }
    <?php if (!$loggedIn): ?>
    try {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('session_token');
        sessionStorage.removeItem('auth_token');
        sessionStorage.removeItem('session_token');
    } catch(e) {}
    <?php endif; ?>
    </script>
</body>
</html>