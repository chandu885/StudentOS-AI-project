# System Architecture & Technical Design

## 1. Executive Summary
StudentOS AI is an enterprise-grade academic operating system and learning intelligence platform designed for higher education institutions. It unites administrative operations, faculty teaching workflows, student productivity, and generative AI services into a cohesive, high-performance web platform.

---

## 2. High-Level Architecture

StudentOS AI utilizes a decoupled, layered service-oriented architecture:

```
+-------------------------------------------------------------------------+
|                              Client Layer                               |
|          Modern Browser / Responsive Mobile UI (CSS Grid/Flex)          |
+-------------------------------------------------------------------------+
                                    |
                                    | HTTP / HTTPS (JSON / Forms)
                                    v
+-------------------------------------------------------------------------+
|                         Presentation & Routing Layer                     |
|           frontend/ (Student, Faculty, Admin, Super Admin Portals)      |
|           Shared Components, Session Manager, Toast/Modal Engine        |
+-------------------------------------------------------------------------+
                                    |
                                    | cURL / Fetch API (Bearer JWT / Session Token)
                                    v
+-------------------------------------------------------------------------+
|                             API Gateway Layer                           |
|       backend/API/index.php + AuthMiddleware + RateLimitMiddleware       |
+-------------------------------------------------------------------------+
                                    |
        +---------------------------+---------------------------+
        |                                                       |
        v                                                       v
+-------------------------------+               +-------------------------------+
|         Service Layer         |               |           AI Layer            |
| AuthService, StudentService,  |               | AIService (Gemini 1.5 Flash), |
| FacultyService, AdminService, |               | Prompt Engine, RAG Ingestion, |
| AssignmentService, ExamService|               | Document Parser, Quiz Engine  |
+-------------------------------+               +-------------------------------+
        |                                                       |
        v                                                       v
+-------------------------------+               +-------------------------------+
|       Data Access Layer       |               |         Storage Layer         |
| Active Record & SQL Models    |               | storage/ (Uploads, Documents, |
| MySQL Database (InnoDB)       |               | Assignments, Temp, Backups)   |
+-------------------------------+               +-------------------------------+
```

---

## 3. Layer Breakdown

### 3.1 Presentation Layer (`frontend/`)
- **Portals**:
  - `student/`: Productivity tools, course tracking, AI study assistant, quizzes, schedule, notes, assignments.
  - `faculty/`: Course delivery, grading, marks entry, attendance management, question bank, syllabus tracking.
  - `admin/`: Institutional management, departments, courses, faculty and student admissions, timetable, system reports.
  - `super-admin/`: Tenant health, role-based access control, security policies, audit logs, AI telemetry, database backups.
- **Shared Components**:
  - `navbar.php`: Global navigation with system notification center, dynamic role indicators, profile switchers.
  - `sidebar.php`: Role-filtered collapsible navigation sidebar.
  - `cards.php`, `tables.php`, `modals.php`, `loading.php`, `notifications.php`: Unified UI design system.
- **Client Scripting & Styling**:
  - CSS System: CSS variables with deep dark palette (`#0B1020`), Inter typography, responsive media queries, and smooth CSS animations.
  - JavaScript System: `api.js` (JWT-injected fetch client), `utils.js` (toasts, modals, string sanitization), `charts.js` (Chart.js wrappers).

### 3.2 Backend API & Gateway (`backend/API/`)
- Single unified REST entry point at `backend/API/index.php` routing requests dynamically via URI mapping:
  - `/api/auth/*`
  - `/api/students/*`
  - `/api/faculty/*`
  - `/api/admin/*`
  - `/api/academic/*`
  - `/api/assignments/*`
  - `/api/attendance/*`
  - `/api/exams/*`
  - `/api/ai/*`
  - `/api/tasks/*`
  - `/api/notes/*`
  - `/api/notifications/*`
- Middleware pipeline executes prior to business logic:
  - `RateLimitMiddleware`: Prevents brute-force attacks and resource exhaustion.
  - `AuthMiddleware`: Validates authorization tokens and matches route permissions with user roles.

### 3.3 Service & Business Logic Layer (`backend/services/`)
- Domain-driven service classes orchestrate transactions and business validation:
  - `AuthService.php`: Login verification, JWT issuance, password reset dispatch, session audit logging.
  - `StudentService.php`: GPA calculation, credit progress, attendance statistics, submission handling.
  - `FacultyService.php`: Class rosters, marks calculation, question generation, course assignment.
  - `AdminService.php`: Department and course provisioning, user enrollment, metrics aggregation.
  - `AIService.php`: Interface with Google Gemini API, fallback heuristic engine, study prompt generation.

### 3.4 Data & Storage Layer (`backend/models/` & `storage/`)
- Relational schema hosted on MySQL (InnoDB engine) with full transactional integrity (`schema.sql`).
- Models encapsulate parameterized SQL queries using PHP's `mysqli` prepared statements to guarantee complete immunity against SQL injection.
- Isolated filesystem storage organized under `storage/` for assignments, study materials, student photos, and database backup dumps.
