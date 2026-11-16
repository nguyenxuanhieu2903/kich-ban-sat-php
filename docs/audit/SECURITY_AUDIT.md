# SECURITY & SECRETS AUDIT REPORT
## Kịch Bản Sát V16 — Application Security Assessment

---

- **Audit Date:** 2026-11-16
- **Auditor:** Application Security Engineer & Lead Architect
- **Target System:** Kịch Bản Sát V16 Foundation
- **Repository Root:** `D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP`
- **Active Git Branch:** `develop`
- **Audit Scope:** Source code, Git commit history, configuration templates, error logging, CORS policy, and browser security.

---

## 1. Executive Security Scorecard

| Assessment Domain | Status | Key Findings |
|---|:---:|---|
| **Hardcoded Secrets & Git Hygiene** | **PASS** | No credentials, API keys, or private tokens found in code or Git history. |
| **Database Injection (SQLi)** | **PASS** | True PDO prepared statements (`PDO::ATTR_EMULATE_PREPARES = false`). |
| **Cross-Site Scripting (XSS)** | **HIGH RISK (P1)** | DOM-based XSS in frontend `health-page.js` due to unescaped `innerHTML` interpolation. |
| **Security Logging & Redaction** | **HIGH RISK (P1)** | `ExceptionHandler::logError()` logs raw `$e->getMessage()` without password sanitization. |
| **CORS Configuration** | **MEDIUM RISK (P2)** | Wildcard origin reflection with credentials vulnerability; missing `Vary: Origin` header. |
| **Session & Cookie Transport** | **HIGH RISK (P1)** | Frontend `api-client.js` omits `credentials: 'include'`, blocking session cookie transit. |
| **Error & Information Leakage** | **LOW RISK (P3)** | Production masking active when `APP_DEBUG=false`; debug mode exposes file paths and traces. |

---

## 2. In-Depth Vulnerability Analysis

### 2.1. VULN-01: DOM-Based Cross-Site Scripting (XSS) in Frontend
- **OWASP Category:** A03:2021 — Injection
- **Severity:** **P1 (High)**
- **Affected File:** `frontend/src/pages/health/health-page.js:84-108`
- **Mechanism:**
  The `HealthPage` component sets `this.container.innerHTML` using an interpolated template string containing dynamic values:
  ```javascript
  bodyContent = `
    <div style="margin-bottom: 1rem; color: var(--color-error);">
      <strong>Error [${error.code || 'UNKNOWN'}]:</strong> ${error.message}
    </div>
    ${error.details ? `<pre class="code-block">${JSON.stringify(error.details, null, 2)}</pre>` : ''}
  `;
  this.container.innerHTML = `... ${bodyContent} ...`;
  ```
- **Exploitation Scenario:**
  If an attacker sends an invalid URL or payload that is reflected in the backend error message (for example: `GET /api/<img src=x onerror=alert(document.domain)>`), the backend `NotFoundException` constructs the error message: `Route not found: GET /<img src=x onerror=alert(document.domain)>`. When the frontend API client receives this 404 response, `error.message` contains the unescaped script tag, which executes in the browser when injected into `innerHTML`.
- **Remediation:**
  1. Add an HTML sanitizer / escaping utility:
     ```javascript
     function escapeHtml(str) {
       return String(str)
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;')
         .replace(/'/g, '&#39;');
     }
     ```
  2. Escape all dynamic interpolated strings (`escapeHtml(error.message)`) or construct DOM elements using `document.createElement()` and `textContent`.

---

### 2.2. VULN-02: Sensitive Exception Message Leakage in Error Logs
- **OWASP Category:** A09:2021 — Security Logging and Monitoring Failures
- **Severity:** **P1 (High)**
- **Affected File:** `backend/app/Core/Exceptions/ExceptionHandler.php:59-73`
- **Mechanism:**
  In `ExceptionHandler::logError(Throwable $e)`:
  ```php
  protected static function logError(Throwable $e): void
  {
      // Safe sanitization: strip any potential passwords from logs
      $safeTrace = preg_replace('/(password|token|secret|key)=([^&\s]+)/i', '$1=***', $e->getTraceAsString());
      $logMessage = sprintf(
          "[%s] %s in %s:%d\nTrace:\n%s\n",
          date('Y-m-d H:i:s'),
          $e->getMessage(),
          $e->getFile(),
          $e->getLine(),
          $safeTrace
      );

      error_log($logMessage);
  }
  ```
  The regex sanitization is only applied to `$safeTrace`. The exception message itself (`$e->getMessage()`) is formatted directly into `$logMessage` without redaction.
- **Evidence:**
  During PHPUnit test runs, `testUncaughtExceptionInProductionHidesDetails()` threw an exception with message `'Database password secret revealed'`. Because `$e->getMessage()` was unredacted, it was printed verbatim to the system log/console output:
  ```text
  [2026-11-16 10:55:38] Database password secret revealed in .../ApplicationTest.php:90
  ```
- **Remediation:**
  Apply credential sanitization to `$e->getMessage()` as well:
  ```php
  $safeMessage = preg_replace('/(password|token|secret|key)=([^&\s]+)/i', '$1=***', $e->getMessage());
  ```

---

### 2.3. VULN-03: Arbitrary Origin Reflection with Credentials in CORS
- **OWASP Category:** A01:2021 — Broken Access Control
- **Severity:** **P2 (Medium)**
- **Affected File:** `backend/app/Core/Middleware/CorsMiddleware.php:36-43,53-56,64-67`
- **Mechanism:**
  In `CorsMiddleware`:
  ```php
  foreach ($this->allowedOrigins as $allowed) {
      if ($allowed === '*' || strcasecmp($allowed, $origin) === 0) {
          $isAllowed = true;
          $matchedOrigin = $origin;
          break;
      }
  }
  ...
  if ($isAllowed) {
      $headers['Access-Control-Allow-Origin'] = $matchedOrigin;
      $headers['Access-Control-Allow-Credentials'] = 'true';
  }
  ```
  If `ALLOWED_ORIGINS` is configured with `*` (wildcard), `$matchedOrigin` is assigned the incoming request's `Origin` header, effectively reflecting *any* origin while simultaneously asserting `Access-Control-Allow-Credentials: true`. The W3C CORS specification forbids `Access-Control-Allow-Origin: *` with credentials, but dynamically reflecting the origin bypasses browser wildcard restrictions, exposing authenticated session cookies to any requesting site.
- **Secondary Issue — Missing `Vary: Origin`:**
  Responses that dynamically reflect origins must return `Vary: Origin` to prevent HTTP caches from serving authenticated responses to unauthorized origins.
- **Remediation:**
  1. If `$allowed === '*'`: do not permit credentials (`Access-Control-Allow-Credentials` must not be sent).
  2. Always attach `Vary: Origin` to any response containing `Access-Control-Allow-Origin`.

---

### 2.4. VULN-04: Credential Exposure in PDO Constructor Exception Trace
- **OWASP Category:** A02:2021 — Cryptographic Failures / Information Leakage
- **Severity:** **P2 (Medium)**
- **Affected File:** `backend/app/Infrastructure/Database/ConnectionFactory.php:45-54`
- **Mechanism:**
  ```php
  } catch (PDOException $e) {
      $safeHost = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
      $safeDb = htmlspecialchars($database, ENT_QUOTES, 'UTF-8');
      throw new DatabaseException(
          sprintf('Failed to connect to database [%s] on host [%s:%d]: %s', $safeDb, $safeHost, $port, $e->getMessage()),
          (int) $e->getCode(),
          $e
      );
  }
  ```
  In PHP PDO, when `new PDO($dsn, $username, $password, ...)` fails, the `PDOException` stack frame contains the constructor parameters, including `$password`. By chaining `$e` as `$previous` in `DatabaseException`, any code that dumps `$exception->getTrace()` or converts `$exception` to string exposes the database password in plain text.
- **Remediation:**
  Do not pass `$e` as `$previous` unless the trace arguments have been scrubbed, or ensure `zend.exception_ignore_args = 1` in production `php.ini`.

---

## 3. Secrets & Repository Audit

### 3.1. Git History Scanning
- Executed `git log -S "password"`, `git log -S "secret"`, `git log -S "key"`.
- Findings:
  - Commit `1a1b59b` (initial repository): Only spec documents and `.gitignore`.
  - Commit `5cb2e6e` (directory scaffolding): Empty `.gitkeep` files.
  - Commit `214646a` (backend core): No secrets found.
  - Commit `01a7dc0` (PDO infrastructure): Contains `.env.example` with standard dummy placeholder: `DB_PASSWORD=your_database_password_here`.
  - Commit `759ac2f` (frontend foundation): No secrets found.
  - Commit `300de3f` (foundation integration): No secrets found.
- Verdict: **PASS**. Zero live credentials were ever committed to Git history.

### 3.2. Local Workspace Scanning
- Searched workspace for `.env` or temporary credential dumps.
- Only `backend/.env.example` exists.
- The working `.env` file is properly ignored by `.gitignore` and not present in the workspace.
- No remote is configured (`git remote -v` is empty).

---

## 4. HTTP & Browser Security Headers

### 4.1. Response Headers Audit (`App\Core\Response`)
Default headers emitted on every JSON response:
- `Content-Type: application/json; charset=utf-8` (PASS)
- `X-Content-Type-Options: nosniff` (PASS — prevents MIME-sniffing)
- `X-Frame-Options: DENY` (PASS — prevents clickjacking)
- `X-XSS-Protection: 1; mode=block` (PASS — legacy browser protection)
- `Referrer-Policy: strict-origin-when-cross-origin` (PASS — prevents referrer leakage)

### 4.2. Recommended Header Additions
- `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

---

## 5. Security Action Checklist

- [ ] **SEC-01:** Implement `escapeHtml()` in `frontend/src/pages/health/health-page.js` to eliminate DOM XSS.
- [ ] **SEC-02:** Update `ExceptionHandler::logError()` to sanitize `$e->getMessage()`.
- [ ] **SEC-03:** Add `credentials: 'include'` to `frontend/src/api/api-client.js`.
- [ ] **SEC-04:** Update `CorsMiddleware` to include `Vary: Origin` and disallow credentials when origin is `*`.
- [ ] **SEC-05:** Scrub sensitive arguments from `PDOException` before re-throwing `DatabaseException`.
