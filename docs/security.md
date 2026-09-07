# Security Architecture & Policies

## 1. Threat Mitigation Strategies

StudentOS AI incorporates defense-in-depth protections conforming to OWASP Top 10 web application security standards:

| Attack Vector | Countermeasure Implemented | Code Enforcement |
|:---|:---|:---|
| **SQL Injection** | Parameterized SQL Queries / Prepared Statements exclusively | `backend/models/*.php` |
| **Cross-Site Scripting (XSS)** | Mandatory HTML Entity Escaping on all client rendering | `escapeHTML()` in `helpers.php` & `utils.js` |
| **Cross-Site Request Forgery (CSRF)** | Token validation on state-modifying requests | `AuthMiddleware.php` & Bearer tokens |
| **Brute-Force & Credential Stuffing** | IP & Account lockout after 5 consecutive failed attempts | `RateLimitMiddleware.php` |
| **Session Hijacking** | `HttpOnly`, `SameSite=Lax`, and device fingerprint binding | `frontend/includes/config.php` |
| **Arbitrary File Upload** | Strict MIME-type validation, size limits, and safe renaming | `backend/utils/Validator.php` |

---

## 2. Input Validation & Data Sanitization
- All client inputs are filtered through `backend/utils/Validator.php`.
- Emails: Validated via `filter_var(..., FILTER_VALIDATE_EMAIL)`.
- Numeric IDs: Cast strictly to integers (`(int)$_GET['id']`).
- Text content: Stripped of control characters and normalized to valid UTF-8.

---

## 3. Session Hardening
PHP session cookies are initialized with secure security directives:
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
```
Upon login and privilege escalation, `session_regenerate_id(true)` is invoked to prevent session fixation attacks.

---

## 4. File Storage Hardening
- Uploaded files in `storage/` are not executable directly (`.htaccess` in storage disallows script execution).
- Uploaded filenames are sanitized and stored with randomized UUID identifiers to prevent directory traversal attacks (`../../path`).
- Maximum upload size is strictly capped at 20MB.

---

## 5. Audit Logging & Compliance
- Every administrative action (user creation, role modification, settings change, export) is recorded in `audit_logs`.
- Every authentication attempt (success, failure, IP, user-agent) is recorded in `login_logs`.
