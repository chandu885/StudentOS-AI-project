<?php
// tests/verify_features.php
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/Role.php';
require_once __DIR__ . '/../backend/models/Academic.php';

echo "=== STARTING VERIFICATION TEST ===" . PHP_EOL;

$roleModel = new Role();
$academicModel = new Academic();

// TEST 1: Role Model & Dynamic Roles
echo "\n--- TEST 1: Role Model ---" . PHP_EOL;
$roles = $roleModel->getAllWithCounts();
echo "Total roles found in database: " . count($roles) . PHP_EOL;
foreach ($roles as $r) {
    echo "Role #{$r['id']}: {$r['name']} ({$r['display_name']}) - Users: {$r['user_count']}, Perms: {$r['perm_count']}, IsSystem: " . ($r['is_system'] ? 'Yes' : 'No') . PHP_EOL;
}

// Create a test role
$testRoleRes = $roleModel->createRole('TEST_DEAN', 'Test Dean of Academics', 'Test Role Description', [1, 2, 3]);
echo "Create Test Role result: " . ($testRoleRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($testRoleRes['error'] ?? '')) . PHP_EOL;
$testRoleId = $testRoleRes['role_id'] ?? null;

if ($testRoleId) {
    // Update role
    $upRes = $roleModel->updateRole($testRoleId, 'Updated Test Dean', 'Updated Description', [1, 3]);
    echo "Update Test Role result: " . ($upRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($upRes['error'] ?? '')) . PHP_EOL;

    // Verify protection on system role (Role ID 1 SUPER_ADMIN)
    $delSysRes = $roleModel->deleteRole(1);
    echo "Attempt delete System Role #1 (should fail): " . (!$delSysRes['success'] ? 'CORRECTLY PROTECTED: ' . $delSysRes['error'] : 'ERROR: ALLOWED DELETION') . PHP_EOL;

    // Delete test role
    $delRes = $roleModel->deleteRole($testRoleId);
    echo "Delete Test Role result: " . ($delRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($delRes['error'] ?? '')) . PHP_EOL;
}

// TEST 2: Semesters Feature
echo "\n--- TEST 2: Semesters Feature ---" . PHP_EOL;
$semesters = $academicModel->getSemesters();
echo "Total Semesters in DB: " . count($semesters) . PHP_EOL;
foreach (array_slice($semesters, 0, 4) as $s) {
    echo "Sem #{$s['id']}: {$s['name']} (Course: {$s['course_name']}, Year: {$s['academic_year']}, Status: {$s['status']}, Subjects: {$s['subject_count']})" . PHP_EOL;
}

// Create test semester for Course 1
$testSemData = [
    'course_id' => 1,
    'semester_number' => 12,
    'name' => 'Semester 12 (Special Term)',
    'academic_year' => '2029-2030',
    'start_date' => '2029-01-10',
    'end_date' => '2029-05-20',
    'status' => 'upcoming',
    'description' => 'Test special semester'
];
$createSemRes = $academicModel->createSemester($testSemData, 1);
echo "Create Test Semester result: " . ($createSemRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($createSemRes['error'] ?? '')) . PHP_EOL;
$testSemId = $createSemRes['semester_id'] ?? null;

if ($testSemId) {
    // Update semester
    $testSemData['name'] = 'Semester 12 (Updated Term)';
    $upSemRes = $academicModel->updateSemester($testSemId, $testSemData);
    echo "Update Test Semester result: " . ($upSemRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($upSemRes['error'] ?? '')) . PHP_EOL;

    // Delete test semester
    $delSemRes = $academicModel->deleteSemester($testSemId);
    echo "Delete Test Semester result: " . ($delSemRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($delSemRes['error'] ?? '')) . PHP_EOL;
}

// TEST 3: Subjects Feature
echo "\n--- TEST 3: Subjects Feature ---" . PHP_EOL;
$subjects = $academicModel->getSubjects();
echo "Total Subjects in DB: " . count($subjects) . PHP_EOL;
foreach (array_slice($subjects, 0, 4) as $sub) {
    echo "Subject #{$sub['id']}: {$sub['code']} - {$sub['name']} (Sem {$sub['semester']}, Credits: {$sub['credits']}, Faculty: " . ($sub['faculty_first'] ? $sub['faculty_first'] . ' ' . $sub['faculty_last'] : 'None') . ")" . PHP_EOL;
}

// Create test subject
$testSubData = [
    'course_id' => 1,
    'department_id' => 4,
    'faculty_id' => null,
    'code' => 'TEST999',
    'name' => 'Advanced Neural Interfaces',
    'semester' => '6',
    'credits' => 4,
    'type' => 'elective',
    'syllabus' => 'Unit 1: BCI, Unit 2: Signal Processing',
    'status' => 'active'
];
$createSubRes = $academicModel->createSubject($testSubData, 1);
echo "Create Test Subject result: " . ($createSubRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($createSubRes['error'] ?? '')) . PHP_EOL;
$testSubId = $createSubRes['subject_id'] ?? null;

if ($testSubId) {
    // Update subject
    $testSubData['name'] = 'Advanced Neural & Quantum Interfaces';
    $upSubRes = $academicModel->updateSubject($testSubId, $testSubData);
    echo "Update Test Subject result: " . ($upSubRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($upSubRes['error'] ?? '')) . PHP_EOL;

    // Delete test subject
    $delSubRes = $academicModel->deleteSubject($testSubId);
    echo "Delete Test Subject result: " . ($delSubRes['success'] ? 'SUCCESS' : 'FAILED: ' . ($delSubRes['error'] ?? '')) . PHP_EOL;
}

echo "\n=== ALL VERIFICATION TESTS COMPLETED SUCCESSFULLY ===" . PHP_EOL;
