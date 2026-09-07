# Role-Based Access Control (RBAC)

## 1. Role Hierarchy
StudentOS AI enforces a strict multi-tier Role-Based Access Control model anchored in a unified `roles` database structure:

| Role ID | Role Name | System Purpose | Primary Interface |
|:---:|:---|:---|:---|
| **1** | `super_admin` | Global platform administration, security, backups, audit logs | `frontend/super-admin/` |
| **2** | `admin` | Departmental management, courses, faculty/student records | `frontend/admin/` |
| **3** | `faculty` | Course instruction, syllabus management, grading, attendance | `frontend/faculty/` |
| **4** | `student` | Learning management, AI study tools, assignments, exams | `frontend/student/` |

---

## 2. Permissions Matrix

| Module / Capability | Student | Faculty | Admin | Super Admin |
|:---|:---:|:---:|:---:|:---:|
| **View Own Dashboard & Profile** | Yes | Yes | Yes | Yes |
| **Submit Assignments & View Marks** | Yes | No | No | No |
| **AI Study Assistant & PDF Q&A** | Yes | Yes | Yes | Yes |
| **Grade Assignments & Record Attendance** | No | Yes | Yes | Yes |
| **Create Exams & Manage Question Bank** | No | Yes | Yes | Yes |
| **Manage Departments & Courses** | No | No | Yes | Yes |
| **Admit Students & Onboard Faculty** | No | No | Yes | Yes |
| **Generate Institutional Reports** | No | No | Yes | Yes |
| **Manage System Settings & API Keys** | No | No | No | Yes |
| **Access Security Logs & Audit Trails** | No | No | No | Yes |
| **Trigger Database Backups & Restore** | No | No | No | Yes |

---

## 3. Enforcement Mechanisms

### 3.1 Frontend Session Verification
Every portal page includes `frontend/includes/auth.php` and executes `requireRole()`:
```php
require_once __DIR__ . '/../includes/auth.php';
requireRole('student'); // Automatically redirects unauthorized visitors to their respective portal or login
```

### 3.2 Backend Middleware Enforcement
The API gateway verifies JWT claims and invokes role gates before reaching service actions:
```php
AuthMiddleware::authenticate();
AuthMiddleware::requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN]);
```

### 3.3 Dynamic Navigation & UI Masking
The shared sidebar (`frontend/components/sidebar.php`) and navbar dynamically inspect `$_SESSION['user']['role_name']`, rendering only relevant route groups and hiding administrative elements from non-privileged users.
