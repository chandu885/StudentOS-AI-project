# Quality Assurance & Testing Suite

## 1. Testing Strategy
StudentOS AI employs a multi-level verification pipeline to guarantee stability, security, and performance:

1. **Syntax & Static Verification**: Automated PHP CLI linting across all controllers, views, models, and helper scripts.
2. **Unit & Service Testing**: Validation of authentication, session management, and RBAC algorithms (`tests/test_auth.php`).
3. **API Integration Testing**: Automated endpoint validation using cURL and Postman collections.
4. **End-to-End User Flow Testing**: Verifying complete journeys for Super Admin, Admin, Faculty, and Student users.

---

## 2. Running Automated Syntax Linting
To verify that every PHP file in both backend and frontend conforms to valid PHP syntax:

```powershell
# In PowerShell:
Get-ChildItem -Path "frontend", "backend", "tests" -Recurse -Filter *.php | ForEach-Object {
    $res = & "D:\xampp\php\php.exe" -l $_.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Output "ERROR in file: $($_.FullName)"
        Write-Output $res
    }
}
Write-Output "Syntax verification passed!"
```

---

## 3. Running Authentication & Integration Tests
Ensure MySQL is started in XAMPP and `studentos_ai` database is seeded:

```powershell
& "D:\xampp\php\php.exe" tests/test_auth.php
```

Expected Output:
```
Login Result:
Success: YES
User: Alex Johnson
Role: student
Token Generated: YES
```

---

## 4. Manual Verification Checklist
- [x] **Super Admin Portal**: Verify dashboard metrics, user management, audit logs, and system health status.
- [x] **Admin Portal**: Verify department, course, faculty, and student registration pages.
- [x] **Faculty Portal**: Verify course list, student rosters, marks recording, and attendance toggle.
- [x] **Student Portal**: Verify dashboard, timetable, assignment submissions, notes, and AI study assistant.
- [x] **Responsive Layout**: Verify hamburger sidebar drawer toggle on mobile viewports (< 768px).
- [x] **Modal & Toast Feedback**: Verify notifications display properly when actions complete.
