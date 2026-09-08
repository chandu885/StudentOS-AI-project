<?php
// frontend/student/results.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('student');

$userId = $_SESSION['user']['id'] ?? null;
$user = $_SESSION['user'] ?? [];
$studentName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($studentName)) {
    $studentName = $user['username'] ?? 'Alex Johnson';
}

$db = getDbConnection();
$rollNumber = 'STU2026001';
$degreeProgram = 'BCA';
$currentSemester = 'Semester 5';

if ($db && $userId) {
    $stmt = $db->prepare(
        "SELECT sp.*, d.name AS department_name, d.code AS department_code, c.name AS course_name, c.code AS course_code 
         FROM student_profiles sp 
         LEFT JOIN departments d ON sp.department_id = d.id 
         LEFT JOIN courses c ON sp.course_id = c.id 
         WHERE sp.user_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($dbProf = $res->fetch_assoc()) {
            if (!empty($dbProf['student_id'])) {
                $rollNumber = $dbProf['student_id'];
            } elseif (!empty($dbProf['roll_number'])) {
                $rollNumber = $dbProf['roll_number'];
            }
            $deptId = (int)($dbProf['department_id'] ?? 1);
            $deptName = $dbProf['department_name'] ?? '';
            $deptCode = strtoupper($dbProf['department_code'] ?? '');
            $courseCode = strtoupper($dbProf['course_code'] ?? '');
            if ($deptId === 4 || stripos($deptName, 'Management') !== false || stripos($deptName, 'Business') !== false || $deptCode === 'MGMT' || $courseCode === 'BBA') {
                $degreeProgram = 'BBA';
                $departmentName = !empty($deptName) ? $deptName : 'Department of Business Administration';
            } else {
                $degreeProgram = 'BCA';
                $departmentName = !empty($deptName) ? $deptName : 'Department of Computer Applications';
            }
            if (!empty($dbProf['semester'])) {
                $currentSemester = 'Semester ' . $dbProf['semester'];
            }
        }
    }
}

if ($degreeProgram === 'BBA') {
    $semesters = [
        'Semester 5 (Fall 2025)' => [
            'sgpa' => 3.82,
            'credits' => 20,
            'courses' => [
                ['code' => 'BBA501', 'name' => 'Financial Management & Investment', 'credits' => 4, 'marks' => 88, 'grade' => 'A', 'points' => 4.0],
                ['code' => 'BBA502', 'name' => 'Marketing Management & Consumer Insights', 'credits' => 4, 'marks' => 82, 'grade' => 'A-', 'points' => 3.7],
                ['code' => 'BBA503', 'name' => 'Human Resource Management', 'credits' => 3, 'marks' => 79, 'grade' => 'B+', 'points' => 3.3],
                ['code' => 'BBA504', 'name' => 'Business Research & Quantitative Methods', 'credits' => 4, 'marks' => 91, 'grade' => 'A+', 'points' => 4.0],
                ['code' => 'BBA505', 'name' => 'Business Analytics & Spreadsheet Lab', 'credits' => 2, 'marks' => 94, 'grade' => 'A+', 'points' => 4.0]
            ]
        ],
        'Semester 4 (Spring 2025)' => [
            'sgpa' => 3.75,
            'credits' => 21,
            'courses' => [
                ['code' => 'BBA401', 'name' => 'Organizational Behavior & Dynamics', 'credits' => 4, 'marks' => 85, 'grade' => 'A', 'points' => 4.0],
                ['code' => 'BBA402', 'name' => 'Managerial Economics & Policy', 'credits' => 4, 'marks' => 80, 'grade' => 'A-', 'points' => 3.7],
                ['code' => 'BBA403', 'name' => 'Business Law & Corporate Governance', 'credits' => 4, 'marks' => 89, 'grade' => 'A', 'points' => 4.0],
                ['code' => 'BBA404', 'name' => 'Cost & Management Accounting', 'credits' => 3, 'marks' => 76, 'grade' => 'B', 'points' => 3.0]
            ]
        ]
    ];
} else {
    $semesters = [
        'Semester 5 (Fall 2025)' => [
            'sgpa' => 3.82,
            'credits' => 20,
            'courses' => [
                ['code' => 'BCA501', 'name' => 'Database Management Systems', 'credits' => 4, 'marks' => 88, 'grade' => 'A', 'points' => 4.0],
                ['code' => 'BCA502', 'name' => 'Operating Systems & System Software', 'credits' => 4, 'marks' => 82, 'grade' => 'A-', 'points' => 3.7],
                ['code' => 'BCA503', 'name' => 'Theory of Computation & Automata', 'credits' => 3, 'marks' => 79, 'grade' => 'B+', 'points' => 3.3],
                ['code' => 'BCA504', 'name' => 'Computer Networks & Internet Protocols', 'credits' => 4, 'marks' => 91, 'grade' => 'A+', 'points' => 4.0],
                ['code' => 'BCA505', 'name' => 'Web Technologies & Cloud Computing Lab', 'credits' => 2, 'marks' => 94, 'grade' => 'A+', 'points' => 4.0]
            ]
        ],
        'Semester 4 (Spring 2025)' => [
            'sgpa' => 3.75,
            'credits' => 21,
            'courses' => [
                ['code' => 'BCA401', 'name' => 'Design & Analysis of Algorithms', 'credits' => 4, 'marks' => 85, 'grade' => 'A', 'points' => 4.0],
                ['code' => 'BCA402', 'name' => 'Discrete Mathematics & Graph Theory', 'credits' => 4, 'marks' => 80, 'grade' => 'A-', 'points' => 3.7],
                ['code' => 'BCA403', 'name' => 'Object Oriented Programming (Java)', 'credits' => 4, 'marks' => 89, 'grade' => 'A', 'points' => 4.0],
                ['code' => 'BCA404', 'name' => 'Computer Architecture & Organization', 'credits' => 3, 'marks' => 76, 'grade' => 'B', 'points' => 3.0]
            ]
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result - BSTUDENTOS</title>
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Screen Styles for Results Page */
        .print-only {
            display: none;
        }

        .results-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }

        .results-action-bar h1 {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-print-result {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: var(--font-size-sm);
            font-weight: 600;
            background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            transition: all var(--transition-normal);
        }

        .btn-print-result:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
            background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
        }

        /* Dedicated Print Styles for Official Printout */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 15mm;
            }

            /* Hide interactive UI elements and layouts */
            .sidebar,
            .top-navbar,
            .dashboard-footer,
            .results-action-bar,
            .no-print,
            button,
            .btn {
                display: none !important;
            }

            body, html {
                background: #ffffff !important;
                color: #0f172a !important;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
                font-size: 10pt !important;
                line-height: 1.4 !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .dashboard-layout,
            .dashboard-main,
            .dashboard-content {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: none !important;
            }

            .dashboard-layout::before {
                display: none !important;
            }

            /* Display print-only elements */
            .print-only {
                display: block !important;
            }

            /* Print Header Section */
            .print-header {
                border-bottom: 2px solid #0f172a;
                padding-bottom: 14px;
                margin-bottom: 18px;
            }

            .print-header-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                border-bottom: 1px solid #cbd5e1;
                padding-bottom: 12px;
                margin-bottom: 14px;
            }

            .print-brand {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .print-brand-badge {
                width: 42px;
                height: 42px;
                background: #1e1b4b !important;
                color: #ffffff !important;
                font-size: 22px;
                font-weight: 800;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-inst-name {
                font-size: 16pt;
                font-weight: 800;
                letter-spacing: 0.5px;
                color: #0f172a;
                line-height: 1.2;
            }

            .print-inst-sub {
                font-size: 8pt;
                color: #475569;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .print-header-title-box {
                text-align: right;
            }

            .print-header-title-box h1 {
                font-size: 20pt !important;
                font-weight: 800 !important;
                color: #0f172a !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
                margin: 0 0 4px 0 !important;
            }

            .print-header-date {
                font-size: 8pt;
                color: #64748b;
            }

            /* Student Metadata Box in Printout */
            .print-student-info-card {
                background: #f8fafc !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 6px;
                padding: 10px 14px;
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 10px;
                margin-bottom: 18px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-info-item {
                display: flex;
                flex-direction: column;
            }

            .print-info-label {
                font-size: 7.5pt;
                font-weight: 700;
                text-transform: uppercase;
                color: #64748b;
                margin-bottom: 2px;
            }

            .print-info-value {
                font-size: 10pt;
                font-weight: 700;
                color: #0f172a;
            }

            /* Summary Statistics in Printout */
            .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 12px !important;
                margin-bottom: 20px !important;
            }

            .stat-card {
                background: #f8fafc !important;
                border: 1.5px solid #0f172a !important;
                border-radius: 6px !important;
                padding: 10px 14px !important;
                box-shadow: none !important;
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                page-break-inside: avoid;
                break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .stat-icon {
                width: 36px !important;
                height: 36px !important;
                border-radius: 6px !important;
                background: #0f172a !important;
                color: #ffffff !important;
                font-size: 15px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .stat-number {
                font-size: 16pt !important;
                font-weight: 800 !important;
                color: #0f172a !important;
                line-height: 1.1 !important;
            }

            .stat-label {
                font-size: 8pt !important;
                font-weight: 700 !important;
                color: #334155 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }

            /* Semester Cards & Course Tables */
            .card {
                border: 1px solid #cbd5e1 !important;
                border-radius: 6px !important;
                box-shadow: none !important;
                margin-bottom: 18px !important;
                page-break-inside: avoid;
                break-inside: avoid;
                background: #ffffff !important;
            }

            .card-header {
                background: #f1f5f9 !important;
                border-bottom: 1px solid #cbd5e1 !important;
                padding: 8px 12px !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .card-header h3 {
                font-size: 10.5pt !important;
                font-weight: 700 !important;
                color: #0f172a !important;
                margin: 0 !important;
            }

            .card-body {
                padding: 0 !important;
            }

            .data-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 0 !important;
            }

            .data-table th {
                background: #f8fafc !important;
                color: #0f172a !important;
                font-size: 8pt !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                padding: 6px 10px !important;
                border: 1px solid #cbd5e1 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .data-table td {
                padding: 6px 10px !important;
                font-size: 9pt !important;
                color: #0f172a !important;
                border: 1px solid #cbd5e1 !important;
            }

            .data-table tr:nth-child(even) {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .badge {
                border: 1px solid #94a3b8 !important;
                background: #f1f5f9 !important;
                color: #0f172a !important;
                font-size: 7.5pt !important;
                font-weight: 600 !important;
                padding: 2px 6px !important;
                border-radius: 4px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Print Footer / Official Certification */
            .print-footer {
                margin-top: 24px;
                padding-top: 12px;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .print-signature-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
                margin-bottom: 16px;
                padding: 0 20px;
            }

            .print-sig-box {
                text-align: center;
                width: 200px;
            }

            .print-sig-line {
                border-top: 1px dashed #0f172a;
                margin-bottom: 6px;
            }

            .print-sig-title {
                font-size: 8pt;
                font-weight: 700;
                color: #0f172a;
                text-transform: uppercase;
            }

            .print-disclaimer {
                text-align: center;
                font-size: 7.5pt;
                color: #64748b;
                border-top: 1px solid #e2e8f0;
                padding-top: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include_once __DIR__ . '/../components/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <?php include_once __DIR__ . '/../components/navbar.php'; ?>
            
            <div class="dashboard-content">
                <!-- Screen-Visible Header with Print Option -->
                <div class="results-action-bar no-print">
                    <div>
                        <h1><i class="fas fa-file-invoice" style="color: var(--primary);"></i> Student Result</h1>
                        <p class="page-subtitle">Cumulative Grade Point Average (CGPA) and official semester scorecards</p>
                    </div>
                    <div class="header-actions">
                        <button type="button" class="btn-print-result" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Result
                        </button>
                    </div>
                </div>

                <!-- Print-Only Official Document Header -->
                <div class="print-only print-header">
                    <div class="print-header-top">
                        <div class="print-brand">
                            <div class="print-brand-badge">B</div>
                            <div>
                                <div class="print-inst-name">BSTUDENTOS ACADEMY</div>
                                <div class="print-inst-sub">Official Academic Transcript & Semester Grade Report</div>
                            </div>
                        </div>
                        <div class="print-header-title-box">
                            <h1>Student Result</h1>
                            <div class="print-header-date">Date Issued: <?php echo date('F d, Y'); ?></div>
                        </div>
                    </div>

                    <!-- Student Details Box in Printout -->
                    <div class="print-student-info-card">
                        <div class="print-info-item">
                            <span class="print-info-label">Student Name</span>
                            <span class="print-info-value"><?php echo htmlspecialchars($studentName); ?></span>
                        </div>
                        <div class="print-info-item">
                            <span class="print-info-label">Roll / Student ID</span>
                            <span class="print-info-value"><?php echo htmlspecialchars($rollNumber); ?></span>
                        </div>
                        <div class="print-info-item">
                            <span class="print-info-label">Degree Program</span>
                            <span class="print-info-value"><?php echo htmlspecialchars($degreeProgram); ?></span>
                        </div>
                    </div>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success); background: rgba(34, 197, 94, 0.1);"><i class="fas fa-medal"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">3.79</span>
                            <span class="stat-label">Cumulative GPA (CGPA)</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">98</span>
                            <span class="stat-label">Credits Completed</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--ai-accent); background: rgba(139, 92, 246, 0.1);"><i class="fas fa-trophy"></i></div>
                        <div class="stat-content">
                            <span class="stat-number">Top 5%</span>
                            <span class="stat-label">Class Standing</span>
                        </div>
                    </div>
                </div>

                <?php foreach ($semesters as $semName => $semData): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($semName); ?></h3>
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <span class="badge badge-primary">SGPA: <?php echo $semData['sgpa']; ?></span>
                                <span class="badge badge-secondary"><?php echo $semData['credits']; ?> Credits</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Title</th>
                                            <th>Credits</th>
                                            <th>Marks</th>
                                            <th>Letter Grade</th>
                                            <th>Grade Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($semData['courses'] as $crs): ?>
                                            <tr>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($crs['code']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($crs['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($crs['credits']); ?></td>
                                                <td><?php echo htmlspecialchars($crs['marks']); ?>%</td>
                                                <td><span class="badge badge-success"><?php echo htmlspecialchars($crs['grade']); ?></span></td>
                                                <td><strong><?php echo number_format($crs['points'], 1); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Print-Only Official Certification & Signatures -->
                <div class="print-only print-footer">
                    <div class="print-signature-row">
                        <div class="print-sig-box">
                            <div class="print-sig-line"></div>
                            <div class="print-sig-title">Prepared By (Academic Office)</div>
                        </div>
                        <div class="print-sig-box">
                            <div class="print-sig-line"></div>
                            <div class="print-sig-title">Controller of Examinations</div>
                        </div>
                    </div>
                    <div class="print-disclaimer">
                        This is an official computer-generated Student Result issued by BSTUDENTOS Academic Management System. Valid without manual signature or seal alteration.
                    </div>
                </div>
            </div>
            <?php include_once __DIR__ . '/../components/footer.php'; ?>
        </main>
    </div>

    <script src="../assets/js/utils.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
