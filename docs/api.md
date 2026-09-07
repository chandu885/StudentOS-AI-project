# RESTful API Reference

## 1. Base URL & Protocol
- **Base Endpoint**: `http://localhost/StudentOS-AI-project/backend/api`
- **Format**: JSON (`Content-Type: application/json`)
- **Authentication**: Bearer Token via Header `Authorization: Bearer <JWT_TOKEN>` or `X-Session-Token: <SESSION_TOKEN>`

---

## 2. Standard Response Envelope

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "error": "Detailed human-readable error description.",
  "code": 400
}
```

---

## 3. Core API Endpoints

### 3.1 Authentication (`/auth`)
| Method | Endpoint | Description | Auth Required |
|:---|:---|:---|:---:|
| `POST` | `/auth/login` | Authenticate user and return JWT + session info | No |
| `POST` | `/auth/register` | Student self-registration | No |
| `POST` | `/auth/logout` | Terminate session and invalidate token | Yes |
| `POST` | `/auth/forgot-password` | Request password reset email | No |
| `POST` | `/auth/reset-password` | Reset password using one-time token | No |
| `GET` | `/auth/me` | Fetch currently authenticated user payload | Yes |

### 3.2 Students (`/students`)
| Method | Endpoint | Description | Auth Required |
|:---|:---|:---|:---:|
| `GET` | `/students/dashboard` | Aggregated dashboard stats, attendance %, GPA | Student |
| `GET` | `/students/profile` | Detailed student profile | Student |
| `PUT` | `/students/profile` | Update contact information | Student |
| `GET` | `/students/subjects` | Enrolled subjects and course instructors | Student |
| `GET` | `/students/schedule` | Weekly timetable and upcoming lectures | Student |
| `GET` | `/students/performance` | Semester GPA trends and subject breakdowns | Student |

### 3.3 Faculty (`/faculty`)
| Method | Endpoint | Description | Auth Required |
|:---|:---|:---|:---:|
| `GET` | `/faculty/dashboard` | Classes count, student count, pending grading | Faculty |
| `GET` | `/faculty/students` | List students enrolled in taught courses | Faculty |
| `POST` | `/faculty/marks` | Record or update student exam marks | Faculty |
| `POST` | `/faculty/attendance` | Bulk mark attendance for a class session | Faculty |

### 3.4 Academic & Courses (`/academic`)
| Method | Endpoint | Description | Auth Required |
|:---|:---|:---|:---:|
| `GET` | `/academic/departments` | List all departments | Any |
| `GET` | `/academic/courses` | List all academic programs and degrees | Any |
| `GET` | `/academic/subjects` | List subjects filtered by course or semester | Any |

### 3.5 Assignments (`/assignments`)
| Method | Endpoint | Description | Auth Required |
|:---|:---|:---|:---:|
| `GET` | `/assignments` | List assignments for current user/subject | Any |
| `POST` | `/assignments` | Create a new assignment | Faculty / Admin |
| `POST` | `/assignments/{id}/submit` | Upload student submission | Student |
| `GET` | `/assignments/{id}/submissions`| View all submissions for an assignment | Faculty |
| `POST` | `/assignments/submissions/{id}/grade`| Grade and provide feedback | Faculty |

### 3.6 AI Services (`/ai`)
| Method | Endpoint | Description | Auth Required |
|:---|:---|:---|:---:|
| `POST` | `/ai/assistant` | Send conversational study prompt | Any |
| `POST` | `/ai/pdf-qa` | Ingest document & ask contextual question | Any |
| `POST` | `/ai/planner` | Generate personalized study timetable | Student |
| `POST` | `/ai/quiz` | Generate multiple-choice quiz questions | Any |
| `POST` | `/ai/summarize` | Summarize long texts or lecture notes | Any |
| `GET` | `/ai/recommendations` | Get dynamic topic recommendations | Student |

### 3.7 Tasks, Notes & Notifications
| Method | Endpoint | Description | Auth Required |
|:---|:---|:---|:---:|
| `GET/POST/PUT` | `/tasks` | Full CRUD operations for personal tasks | Any |
| `GET/POST/PUT` | `/notes` | Full CRUD operations for subject notes | Any |
| `GET` | `/notifications` | Fetch unread and read alerts | Any |
| `POST` | `/notifications/read` | Mark notifications as read | Any |
