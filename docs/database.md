# Database Design & Relational Schema

## 1. Schema Specifications
- **Engine**: MySQL / MariaDB (InnoDB)
- **Character Set**: `utf8mb4`
- **Collation**: `utf8mb4_unicode_ci`
- **Total Tables**: 54 interconnected relational tables

---

## 2. Key Table Clusters

### 2.1 Identity, Access & Security
- `roles`: System role definitions (Super Admin, Admin, Faculty, Student).
- `permissions` & `role_permissions`: Granular permission flags.
- `users`: Core identity table (first_name, last_name, email, password_hash, role_id, is_active).
- `user_sessions`: Active device tokens, IP addresses, and user-agent records.
- `login_logs` & `audit_logs`: Detailed compliance and audit trail records.
- `rate_limits`: IP-based request throttling tracker.

### 2.2 Academic Organization & Profiles
- `departments`: Academic faculties and departments.
- `courses`: Degree programs (B.Tech, B.Sc, BCA, etc.) tied to departments.
- `subjects`: Modules and courses tied to semesters and departments.
- `student_profiles`: Enrollment numbers, roll numbers, semesters, CGPA.
- `faculty_profiles`: Employee IDs, designations, specializations.
- `admin_profiles`: Department associations and admin roles.
- `class_schedules`: Day, time, room, and faculty assignments.

### 2.3 Learning, Evaluation & Attendance
- `assignments`: Due dates, max marks, subject linkage, and attachments.
- `assignment_submissions`: Student submissions, marks, feedback, submission files.
- `attendance`: Date-wise student presence/absence/late logging.
- `exams`: Internal, midterm, semester, and quiz examinations.
- `questions` & `question_options`: Question bank for exams and quizzes.
- `exam_attempts` & `exam_answers`: Student attempt tracking and responses.
- `results`: Final calculated marks, grades, and GPA impact.
- `performance`: Semester-wise SGPA and CGPA trends.

### 2.4 Productivity & Knowledge Base
- `tasks`: Student and faculty todo items with priorities and status.
- `goals`: Academic and career milestones.
- `calendar_events`: Academic calendar and schedule milestones.
- `study_sessions`: Pomodoro and self-study tracking logs.
- `notes`: Markdown and rich notes categorized by subject.
- `documents` & `files`: Course materials, syllabi, uploaded documents.

### 2.5 Artificial Intelligence & RAG
- `ai_conversations` & `ai_messages`: Multi-turn conversational history with Gemini.
- `document_chunks`: Extracted text chunks and embeddings for PDF Q&A RAG pipeline.
- `ai_study_plans`: Generated dynamic study roadmaps.
- `ai_quizzes` & `ai_quiz_questions`: AI-generated mock tests from uploaded documents or subjects.
- `ai_recommendations`: Personalized learning resource and revision recommendations.
- `ai_usage_logs`: Token consumption, latency, and cost tracking.

---

## 3. Database Initialization
To import the database schema and sample data into your local MySQL instance:

```bash
# Using MySQL CLI
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS studentos_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p studentos_ai < database/schema.sql
mysql -u root -p studentos_ai < database/seed.sql
```
Or via phpMyAdmin:
1. Open `http://localhost/phpmyadmin/`
2. Create database named `studentos_ai`
3. Click **Import** and select `database/schema.sql`
4. Click **Import** and select `database/seed.sql`
