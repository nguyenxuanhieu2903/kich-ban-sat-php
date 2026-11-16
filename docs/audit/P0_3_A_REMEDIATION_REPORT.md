# P0.3-A REMEDIATION VERIFICATION REPORT
## Kịch Bản Sát V16 — Foundation Security & Blocker Remediation

---

- **Date:** 2026-11-16
- **Auditor & Lead Architect:** Senior Principal Software Architect, Application Security Engineer & QA Lead
- **Target Git Baseline:** Branch `develop` — Commit `efbba29` (descendant of `300de3f`)
- **Remediation Scope:** Findings F-01, F-02, F-03, F-04, F-05, F-11, F-12
- **Result:** **PASS WITH CONDITIONS** (All in-scope technical remediations verified; F-02 documented as blocked by architectural decision).

---

## 1. Remediation Summary Table

| Finding ID | Severity | Category | Status | Files Modified | Verification Evidence |
|:---:|:---:|:---|:---:|:---|:---|
| **F-01** | P1 | Security / DOM XSS | **FIXED** | `frontend/src/pages/health/health-page.js` | Refactored to safe DOM methods (`document.createElement()`, `textContent`, `replaceChildren()`). Verified immunity against `<img src=x onerror=alert(1)>`, `<script>`, and `"><svg onload=alert(1)>`. |
| **F-02** | P1 | Architecture / Auth | **BLOCKED BY ARCHITECTURAL DECISION** | `frontend/src/api/api-client.js` | Inspected `ADR-0001` (Section 5) and `PRODUCT_SPEC_V16_Draft.md` (Section 8.1); Session Cookie Authentication is in **Proposed** status. Refactored `api-client.js` to preserve `AbortSignal` composition and allow `credentials` option without unconditionally enforcing it. Requires Project Lead formal ADR approval. |
| **F-03** | P1 | Testing / Quality | **FIXED** | `backend/app/Core/Request.php`<br>`backend/tests/Unit/RequestResponseTest.php` | Extracted `Request::parseJsonPayload()` and introduced raw-body seam in `Request::capture()`. Replaced hollow test with 8 rigorous test cases covering valid objects, arrays, empty payloads, malformed syntax, and non-JSON content types. |
| **F-04** | P1 | Security / Logging | **FIXED** | `backend/app/Core/Exceptions/ExceptionHandler.php`<br>`backend/tests/Unit/ExceptionHandlerTest.php`<br>`backend/tests/Feature/ApplicationTest.php` | Implemented `ExceptionHandler::sanitize()` redacting secrets from both `$e->getMessage()` and `$e->getTraceAsString()`. Added `setLogger()` injection seam for silent testing and custom loggers. Verified zero credential leakage in logs. |
| **F-05** | P1 | Config / Port Alignment | **FIXED** | `README.md`<br>`backend/.env.example`<br>`frontend/src/pages/health/health-page.js`<br>`docs/setup/PREREQUISITES.md` | Standardized backend default port to `8080` (`http://127.0.0.1:8080`) and frontend to `3000` (`http://127.0.0.1:3000`). Verified live HTTP endpoints and CORS preflights over port 8080. |
| **F-11** | P2 | Security / CORS | **FIXED** | `backend/app/Core/Middleware/CorsMiddleware.php`<br>`backend/tests/Unit/CorsMiddlewareTest.php` | Disallowed wildcard origin `*` with credentials (fails safe by forcing `*` and omitting credentials); implemented `Vary: Origin` while preserving existing `Vary` values. Verified unauthorized origins receive no CORS access. |
| **F-12** | P2 | Security / PDO | **FIXED** | `backend/app/Infrastructure/Database/ConnectionFactory.php`<br>`backend/app/Infrastructure/Database/DatabaseException.php`<br>`backend/tests/Unit/DatabaseConnectionTest.php` | Removed `htmlspecialchars()` from infrastructure exception. Ceased attaching raw `PDOException` as `$previous` to prevent leaking plain-text passwords in `PDO::__construct()` frame arguments. Tested message, stack trace, and trace arguments. |

---

## 2. Technical Change Details

### 2.1. F-05: Port Standardization
- **Problem:** `README.md` and `backend/.env.example` instructed running backend on port `8000`, while `frontend/src/utils/config.js` connected to port `8080`.
- **Solution:** Standardized on port `8080` across all documentation and templates, leaving `frontend/src/utils/config.js` with its configurable `window.__APP_CONFIG__?.apiBaseUrl || 'http://127.0.0.1:8080/api'`.
- **Files Modified:**
  - `README.md` (lines 121, 125)
  - `backend/.env.example` (line 5)
  - `docs/setup/PREREQUISITES.md` (line 67)
  - `frontend/src/pages/health/health-page.js` (line 90)

### 2.2. F-01: DOM-Based XSS Elimination
- **Problem:** `HealthPage` interpolated dynamic values (`error.message`, `error.details`, `healthData.*`) directly into template literals passed to `innerHTML`.
- **Solution:** Rebuilt the DOM rendering pipeline using `document.createElement()`, `textContent`, `createTextNode()`, and `replaceChildren()`. `innerHTML` is only used as `innerHTML = ''` for fallback cleanup.
- **Verification:** Untrusted input such as `<img src=x onerror=alert(1)>` renders strictly as visible text and cannot be executed by the browser engine.

### 2.3. F-02: Authentication Strategy Status & API Client Preparation
- **Verification Findings:**
  - `docs/adr/ADR-0001-project-foundation.md` records *Session / Token Authentication* as **Proposed**.
  - `PRODUCT_SPEC_V16_Draft.md` (Section 8.1 & Section 13) records Session Cookie Authentication as an open architectural proposal requiring resolution of CSRF and cross-port cookie policies.
- **Action Taken:**
  - Maintained security neutrality without unilaterally committing to an unapproved credential policy.
  - Refactored `frontend/src/api/api-client.js` to:
    1. Accept `credentials: options.credentials || 'same-origin'`, enabling callers to pass `credentials: 'include'` when session authentication is finalized.
    2. Correctly compose the caller's `AbortSignal` with the internal timeout `AbortController`, preventing caller cancellation signals from being wiped out.
    3. Accurately report dynamic timeout values in `ApiError`.

### 2.4. F-03: Production JSON Parsing & Test Replacement
- **Problem:** `RequestResponseTest::testCaptureDetectsMalformedJson()` manually decoded JSON and threw its own exception, offering 0% coverage of `Request::capture()`.
- **Solution:**
  - Extracted `Request::parseJsonPayload(string $rawBody): array` into `App\Core\Request`.
  - Added an optional input seam `Request::capture(?string $rawBody = null): self` so tests can exercise the exact production parsing pipeline.
  - Case-insensitive `Content-Type` detection across both `$_SERVER` and HTTP headers.
  - Added 8 dedicated unit test cases covering objects, arrays, empty strings, malformed syntax, scalar payloads, and content-type bypassing.

### 2.5. F-04: Secure Exception Logging
- **Problem:** `ExceptionHandler::logError()` logged raw `$e->getMessage()`, leaking credentials to terminal/system log.
- **Solution:**
  - Implemented `ExceptionHandler::sanitize(string $input): string` with regex coverage for `key=value`, `key: value`, JSON-quoted `"password": "..."`, and URI DSN formats (`scheme://user:pass@host`).
  - Redacted both `$e->getMessage()` and `$e->getTraceAsString()` before formatting log messages.
  - Added a testable logger injection seam via `ExceptionHandler::setLogger(?callable $logger)`.
  - When `APP_DEBUG=true`, client responses return sanitized messages; when `APP_DEBUG=false`, responses return generic `Internal Server Error`.

### 2.6. F-11: CORS Hardening & Vary Header Handling
- **Problem:** Wildcard origin `*` was reflected with credentials; `Vary: Origin` was missing, risking cache poisoning.
- **Solution:**
  - Updated `CorsMiddleware`: if wildcard `*` is configured, `$allowCredentials` is forced to `false` and `Access-Control-Allow-Origin: *` is returned (no dynamic reflection).
  - Explicit origins return `Access-Control-Allow-Credentials: true` only when matched.
  - Implemented `appendVaryHeader()` which appends `, Origin` while preserving existing `Vary` headers (e.g., `Accept-Encoding`).
  - Added 7 unit tests in `Tests\Unit\CorsMiddlewareTest`.

### 2.7. F-12: Prevention of PDO Constructor Trace Leakage
- **Problem:** Catching `PDOException` in `ConnectionFactory` retained the raw exception as `$previous`, retaining `$password` in plain text inside `PDO::__construct()` frame arguments.
- **Solution:**
  - Removed `htmlspecialchars()` from `ConnectionFactory`.
  - Sanitized the PDO exception message via `ExceptionHandler::sanitize()`.
  - Omitted the raw `PDOException` from `$previous` when instantiating `DatabaseException`.
  - Verified that neither `$e->getMessage()`, `(string) $e`, nor trace frames contain the database password.

---

## 3. Testing & Verification Summary

### 3.1. Automated Test Results
- **Framework:** PHPUnit 11.5.58 on PHP 8.4.27 (Windows x64).
- **Previous Baseline:** 18 tests, 53 assertions.
- **New Remediation Baseline:** **36 tests, 117 assertions, 0 failures, 0 errors**.
- **Execution Time:** ~2.1 seconds.

```text
Application (Tests\Feature\Application)
 ✔ Health endpoint returns success json
 ✔ Unknown route returns 404 json
 ✔ Unsupported method returns 405 with allow header
 ✔ Exception handler handles custom bad request
 ✔ Uncaught exception in production hides details

Cors Middleware (Tests\Unit\CorsMiddleware)
 ✔ Explicit allowed origin sets cors headers
 ✔ Unauthorized origin receives no cors access
 ✔ Wildcard origin without credentials returns literal asterisk
 ✔ Wildcard with credentials fails safely without allowing credentials
 ✔ Options preflight returns 204 with allowed headers
 ✔ Preserves existing vary header values
 ✔ Does not duplicate vary origin if already present

Database Connection (Tests\Unit\DatabaseConnection)
 ✔ Connection failure throws database exception without leaking credentials
 ✔ Transaction helper commits successfully
 ✔ Transaction helper rolls back on exception
 ✔ Check connectivity returns false on broken connection

Exception Handler (Tests\Unit\ExceptionHandler)
 ✔ Sanitize redacts password in various formats
 ✔ Log error redacts secrets from message and trace
 ✔ Debug mode sanitizes client facing message
 ✔ Handles http exceptions without logging

Request Response (Tests\Unit\RequestResponse)
 ✔ Request normalizes method and path
 ✔ Response generates valid json
 ✔ Response error formatting
 ✔ Parse json payload valid object
 ✔ Parse json payload valid array
 ✔ Parse json payload empty body returns empty array
 ✔ Parse json payload invalid syntax throws bad request exception
 ✔ Parse json payload scalar throws bad request exception
 ✔ Capture parses valid json body
 ✔ Capture throws bad request exception on malformed json
 ✔ Capture ignores non json content type

Router (Tests\Unit\Router)
 ✔ Matches valid route
 ✔ Throws not found for unknown route
 ✔ Throws method not allowed when path exists for different method
 ✔ Matches route with parameters
 ✔ Matches route with multiple parameters

OK (36 tests, 117 assertions)
```

### 3.2. Static Syntax Linting
- **PHP Lint:** 25 files verified via `php -l` — 0 syntax errors.
- **Composer Validation:** `./composer.json is valid`.
- **JavaScript Lint:** 5 files verified via `node --check` — 0 syntax errors.

### 3.3. Live HTTP Integration Evidence
- Started PHP server on `127.0.0.1:8080`:
  - `GET /api/health` -> HTTP 200 `{"success":true,"data":{"status":"ok","application":"Kich Ban Sat","version":"16"}}`.
  - `OPTIONS /api/health` with `Origin: http://127.0.0.1:3000` -> HTTP 204, `Access-Control-Allow-Origin: http://127.0.0.1:3000`, `Access-Control-Allow-Credentials: true`, `Vary: Origin`.
  - `OPTIONS /api/health` with `Origin: http://evil.com` -> HTTP 204, no `Access-Control-Allow-Origin`, `Vary: Origin`.

---

## 4. Remaining Risks & Open Architectural Decisions

1. **Authentication Architecture Decision (F-02):**
   - Project Lead must formally accept an ADR approving either:
     - *Option A:* Session Cookie Authentication with CSRF protection and specific SameSite configuration.
     - *Option B:* Authorization Bearer Token Authentication (JWT or opaque token in HTTP header).
2. **Controller Dependency Injection (F-06):**
   - Controllers currently use parameterless instantiation in `Application::invokeHandler()`. Must be addressed in P0.3-B before Repositories and Services are built.
3. **Database Migration Runner (F-14):**
   - Team requires a deterministic CLI migration script to apply initial SQL schemas. Scheduled for P0.3-B.

---

## 5. Final Architecture Decision

### **PASS WITH CONDITIONS**

**Justification:**
All 5 critical blocker findings (F-01, F-03, F-04, F-05, F-12) and the P2 CORS hardening finding (F-11) have been fully remediated and verified through 36 automated unit and feature tests and live HTTP execution. F-02 is properly held in check awaiting Project Lead approval of the authentication ADR.

**Recommended Next Step:**
Execute **P0.3-B** (Architecture Enablers: Controller Dependency Injection, Route Grouping & Route-level Middleware, and Native Database Migration Runner).
