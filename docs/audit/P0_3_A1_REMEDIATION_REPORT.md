# P0.3-A.1 TARGETED REMEDIATION REPORT
## Kịch Bản Sát V16 — API Client, CORS Cache Safety & Sensitive Data Protection

---

- **Date:** 2026-11-16
- **Auditor & Lead Architect:** Senior Principal Software Architect, Application Security Engineer & QA Lead
- **Workspace:** `D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP`
- **Git Branch:** `develop`
- **Initial Baseline Commit:** `5eb2980235b61de523a90655457d25347fdb5a79` (*fix(foundation): remediate security and blocker findings*)
- **Target Scope:** F-02 (API Client Cancellation/Timeout/Cleanup), F-11 (CORS Vary & Cache Safety), F-04 (Exception Sanitizer & Sensitive Data Protection).
- **Audit Decision:** **PASS**

---

## 1. Executive Summary

A targeted architectural and security remediation was conducted on the Kịch Bản Sát V16 Foundation to verify and resolve three specific residual risks identified in P0.3-A:

1. **F-02 (API Client):** Verified and fixed. Previously, any caller-initiated abort was mistakenly classified as `REQUEST_TIMEOUT`. Furthermore, event listeners attached to the caller's `AbortSignal` leaked across the request lifecycle without cleanup. The API client now cleanly distinguishes caller cancellation (`REQUEST_ABORTED`) from actual network timeouts (`REQUEST_TIMEOUT`), guarantees listener and timer cleanup in a `finally` block, and handles pre-aborted signals deterministically.
2. **F-11 (CORS & Cache Safety):** Verified and fixed. Previously, `CorsMiddleware` only appended `Vary: Origin` when an `Origin` header was present in the request. In accordance with RFC 7234, HTTP cache intermediaries require `Vary: Origin` consistently on all responses whenever representations vary based on origin allowlists (including origin-less requests). All responses now carry `Vary: Origin` consistently without overriding existing `Vary` values.
3. **F-04 (Exception Sanitizer):** Verified and fixed. Identified regex cascading gaps where secrets in PHP array dumps (`[password] => value`), secrets with spaces, URL query parameters (`?token=`), and Bearer tokens could escape redaction or be mangled. Refactored `ExceptionHandler::sanitize()` into a robust multi-pattern pipeline and implemented full exception chain traversal (`Caused by:`) in `formatLogMessage()`.

| Target Area | Findings Inspected | Required Fixes | No Change / Invalid | Status |
|:---|:---:|:---:|:---:|:---:|
| **API Client (F-02)** | 1 | 1 | 0 | **FIXED** |
| **CORS Middleware (F-11)** | 1 | 1 | 0 | **FIXED** |
| **Exception Sanitizer (F-04)** | 1 | 1 | 0 | **FIXED** |

**Final Assessment:** **PASS** (All 3 targeted findings resolved, verified by 45 PHPUnit tests with 141 assertions and 7 native Node.js API client tests).

---

## 2. Finding Analysis

### 2.1. F-02: API Client Abort, Timeout & Event Listener Cleanup

| Field | Content |
|---|---|
| **Finding ID** | **F-02** |
| **Original Risk** | Request cancellation indistinguishable from timeout; event listener memory leak on caller AbortSignal; timeout timers uncancelled on error. |
| **Actual Evidence** | In `frontend/src/api/api-client.js:82-84`, `if (err.name === 'AbortError')` unconditionally threw `ApiError('Request timed out...', 0, 'REQUEST_TIMEOUT')`. At line 39, `options.signal.addEventListener('abort', ...)` had no corresponding `removeEventListener`. |
| **Root Cause** | Conflation of all AbortErrors into timeouts, and omission of lifecycle cleanup in a `finally` block. |
| **Decision** | **FIX** |
| **Changed Files** | `frontend/src/api/api-client.js`<br>`frontend/tests/api-client.test.mjs` |
| **Remediation** | 1. Track `isTimeout` boolean in internal timeout timer.<br>2. Distinguish `REQUEST_TIMEOUT` from `REQUEST_ABORTED` (preserving `options.signal.reason`).<br>3. Enforce `clearTimeout(timeout)` and `options.signal.removeEventListener('abort', abortListener)` inside a guaranteed `finally` block.<br>4. Detect pre-aborted caller signals synchronously. |
| **Regression Risk** | **None.** Public method signatures (`get`, `post`, `put`, `delete`) and standard Promise resolution/rejection contracts remain 100% backward-compatible. |

---

### 2.2. F-11: CORS Vary Header & Cache Safety

| Field | Content |
|---|---|
| **Finding ID** | **F-11** |
| **Original Risk** | HTTP caches serving cached origin-specific responses to unauthorized origins or origin-less requests due to asymmetric `Vary: Origin` emission. |
| **Actual Evidence** | In `backend/app/Core/Middleware/CorsMiddleware.php:80,90`, `appendVaryHeader()` was guarded by `if ($origin !== null && $origin !== '')`. Requests without an Origin header did not receive `Vary: Origin`. |
| **Root Cause** | Conditional emission of `Vary: Origin` only when the request provided an Origin header, violating RFC 7234 cache variance principles. |
| **Decision** | **FIX** |
| **Changed Files** | `backend/app/Core/Middleware/CorsMiddleware.php`<br>`backend/tests/Unit/CorsMiddlewareTest.php` |
| **Remediation** | 1. Always append `Vary: Origin` on all responses in non-wildcard configurations (including origin-less requests and disallowed origins).<br>2. Preserve existing `Vary` header values (e.g. `Accept-Encoding`).<br>3. Prevent duplicate `Vary: Origin` entries. |
| **Regression Risk** | **None.** Existing allowlist logic and credentials handling remain unchanged. |

---

### 2.3. F-04: Sensitive Data Protection & Exception Sanitization

| Field | Content |
|---|---|
| **Finding ID** | **F-04** |
| **Original Risk** | Secrets escaping sanitization when presented in array dumps (`[password] => value`), URL query parameters (`?token=`), secrets with spaces, Bearer tokens, or chained exception traces. |
| **Actual Evidence** | In `backend/app/Core/Exceptions/ExceptionHandler.php:72-86`, `formatLogMessage()` only formatted `$e`, ignoring `$e->getPrevious()`. In `sanitize()`, regex patterns double-matched or stopped at spaces/special characters, allowing array dumps and complex secrets to leak. |
| **Root Cause** | Lack of chained exception recursion and reliance on single-pass regexes that did not account for PHP array dump formatting, URL query separators, and multi-word quoted secrets. |
| **Decision** | **FIX** |
| **Changed Files** | `backend/app/Core/Exceptions/ExceptionHandler.php`<br>`backend/tests/Unit/ExceptionHandlerTest.php` |
| **Remediation** | 1. Refactored `formatLogMessage()` to traverse `$current = $current->getPrevious()` recursively with `Caused by:` formatting.<br>2. Added dedicated URL query parameter redaction (`?token=***`).<br>3. Added Authorization header redaction (`Bearer ***`, `Basic ***`).<br>4. Implemented single-pass `preg_replace_callback` for key-value pairs supporting quotes, spaces, array dumps (`[password] => ***`), and special characters without mangling previously sanitized tokens.<br>5. Preserved diagnostic details for non-sensitive exceptions. |
| **Regression Risk** | **None.** JSON error response format (`{"success": false, "error": {"code": "...", "message": "..."}}`) remains strictly preserved. |

---

## 3. Test Results

### 3.1. Backend Automated Testing (PHPUnit 11.5.58)

```text
Runtime:       PHP 8.4.27
Configuration: D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\backend\phpunit.xml

Application (Tests\Feature\Application)
 ✔ Health endpoint returns success json
 ✔ Unknown route returns 404 json
 ✔ Unsupported method returns 405 with allow header
 ✔ Exception handler handles custom bad request
 ✔ Uncaught exception in production hides details

Cors Middleware (Tests\Unit\CorsMiddleware)
 ✔ Explicit allowed origin sets cors headers
 ✔ Unauthorized origin receives no cors access
 ✔ Request without origin header receives vary origin for cache safety
 ✔ Multiple allowed origins vary correctly across requests
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
 ✔ Sanitize password equals value
 ✔ Sanitize password fat comma in array dumps
 ✔ Sanitize token in url query
 ✔ Sanitize secret with spaces
 ✔ Sanitize secret with special characters
 ✔ Sanitize authorization bearer token
 ✔ Sanitize uri dsn credentials
 ✔ Non sensitive exception preserves diagnostic details
 ✔ Log error sanitizes message trace and chained exceptions
 ✔ Debug mode sanitizes client facing message without exposing internal traces
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

OK (45 tests, 141 assertions)
```

- **Total Tests:** 45 (100% Passed)
- **Total Assertions:** 141
- **Failures / Errors:** 0
- **Execution Time:** ~2.08s

### 3.2. Frontend Automated Testing (Native Node.js Test Suite)

```text
✔ F-02: Request succeeds and cleans up timer and event listener (18.7ms)
✔ F-02: Caller aborting in-flight request throws REQUEST_ABORTED, not REQUEST_TIMEOUT (30.4ms)
✔ F-02: Pre-aborted caller signal immediately throws REQUEST_ABORTED without hanging (0.4ms)
✔ F-02: True timeout triggers REQUEST_TIMEOUT with accurate duration (44.5ms)
✔ F-02: Network failure produces NETWORK_FAILURE error (1.0ms)
✔ F-02: HTTP error response creates normalized ApiError with status and details (0.6ms)
✔ F-02: Event listener cleanup verified on caller signal (0.8ms)

ℹ tests 7 | pass 7 | fail 0 | cancelled 0 | duration_ms 159.8ms
```

### 3.3. Static Syntax Validation
- **PHP Lint (`php -l`):** 25/25 files checked — 0 syntax errors detected.
- **Composer Validation:** `./composer.json is valid`.
- **JavaScript Lint (`node --check`):** 6/6 files checked — 0 syntax errors detected.

### 3.4. Live HTTP Integration & Cache Variance Verification
- `GET /api/health` without Origin: `Status: 200`, `Vary: Origin` (Confirmed).
- `GET /api/health` with `Origin: http://127.0.0.1:3000`: `Status: 200`, `Access-Control-Allow-Origin: http://127.0.0.1:3000`, `Vary: Origin` (Confirmed).
- `OPTIONS /api/health` with `Origin: http://127.0.0.1:3000`: `Status: 204`, `Access-Control-Allow-Origin: http://127.0.0.1:3000`, `Vary: Origin` (Confirmed).

---

## 4. Security Verification Checklist

- [x] **Request Cancellation Safety:** Caller aborts throw `ApiError` with `code = 'REQUEST_ABORTED'` and never masquerade as `REQUEST_TIMEOUT`.
- [x] **Resource Cleanup:** Both timeout timers (`clearTimeout`) and event listeners (`removeEventListener`) are guaranteed to clean up in a `finally` block on success, abort, timeout, or error.
- [x] **Cache Poisoning Prevention:** `Vary: Origin` is attached consistently on all responses across origin-less and origin-bearing requests.
- [x] **No Arbitrary Origin Reflection:** Wildcard configurations never reflect dynamic origins with credentials; unauthorized origins receive no `Access-Control-Allow-Origin` header.
- [x] **Comprehensive Credential Redaction:** Passwords, tokens, DSNs, Bearer headers, array dumps, and chained exceptions are stripped of sensitive values before logging.

---

## 5. Git Information

- **Current Branch:** `develop`
- **Previous HEAD:** `5eb2980235b61de523a90655457d25347fdb5a79`
- **Target Commit Message:** `fix(foundation): harden api cancellation cors and exception sanitization`
- **Files Changed in Scope:**
  ```text
  M backend/app/Core/Exceptions/ExceptionHandler.php
  M backend/app/Core/Middleware/CorsMiddleware.php
  M backend/tests/Unit/CorsMiddlewareTest.php
  M backend/tests/Unit/ExceptionHandlerTest.php
  M docs/audit/P0_3_A1_REMEDIATION_REPORT.md (new)
  M frontend/src/api/api-client.js
  M frontend/tests/api-client.test.mjs (new)
  ```

---

## 6. Remaining Risks & Open Architectural Decisions

1. **Authentication Strategy (ADR-0001):**
   - The project has not yet formally accepted an ADR choosing between *Session Cookie + CSRF Protection* vs *Bearer Token Authentication*.
   - `api-client.js` is now architecturally neutral and supports `options.credentials` seamlessly once decided.
2. **Controller Dependency Injection (F-06):**
   - Controllers currently instantiate via parameterless `new $class()`. Scheduled for resolution in P0.3-B.
3. **Database Migration Runner (F-14):**
   - Scheduled for resolution in P0.3-B.

---

## 7. Final Recommendation

### **PASS**

All 3 targeted findings (F-02, F-11, F-04) have been conclusively remediated, verified with automated tests, and regression-tested on the live HTTP server.

The Foundation is now in a secure, robust state to proceed to **P0.3-B (Architecture Enablers: Controller Dependency Injection, Route Grouping & Route Middleware, and Native Database Migration Runner)**.
