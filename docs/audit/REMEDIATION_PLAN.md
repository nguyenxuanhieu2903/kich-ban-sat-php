# FOUNDATION REMEDIATION PLAN
## Kịch Bản Sát V16 — Technical Architecture & Security Remediation

---

- **Document Version:** 1.0
- **Auditor:** Senior Principal Software Architect
- **Target Baseline:** Foundation Commit `300de3f` on branch `develop`
- **Objective:** Provide actionable, step-by-step remediation specifications for developers to resolve all findings identified during P0.2 Foundation Source Code Audit.

---

## 1. Remediation Overview & Sprint Allocation

| Phase | Finding IDs | Description | Target Timeline |
|---|---|---|---|
| **Phase A (Blockers)** | F-01, F-02, F-03, F-04, F-05 | Security, Auth, Hollow Test & Port Configuration Alignment | Must fix before Phase 1 |
| **Phase B (Architecture)** | F-06, F-07, F-08, F-09, F-10, F-11, F-14 | DI, Route Robustness, Group Middleware, Native Migrations | Sprint 1 Enabler |
| **Phase C (Hardening)** | F-12, F-13, F-15, F-16, F-17 | PDO Traces, Savepoints, PATCH helper, Config validation | Sprint 1 Debt |

---

## 2. Phase A: Blocker Remediation Specifications

### 2.1. Task A-1: Unify Development Ports Across Documentation & Config (F-05)
- **Problem:** `README.md` and `backend/.env.example` instruct running backend on port `8000`, while `frontend/src/utils/config.js` connects to port `8080`.
- **Target Port:** Standardize on port **`8080`** for Backend API and port **`3000`** for Frontend (since port 8000 was identified as occupied by sibling project `Booking_kich-ban-sat_Laravel`).
- **Files to Modify:**
  1. `README.md`
  2. `backend/.env.example`
  3. `frontend/src/pages/health/health-page.js`
- **Specification:**
  - In `README.md:121`: Change `php -S 127.0.0.1:8000 -t backend/public` to `php -S 127.0.0.1:8080 -t backend/public`.
  - In `README.md:125`: Change `http://127.0.0.1:8000/api/health` to `http://127.0.0.1:8080/api/health`.
  - In `backend/.env.example:5`: Change `APP_URL=http://127.0.0.1:8000` to `APP_URL=http://127.0.0.1:8080`.
  - In `frontend/src/pages/health/health-page.js:90`: Change `127.0.0.1:8000` to `127.0.0.1:8080`.

---

### 2.2. Task A-2: Add Session Credentials to Frontend API Client (F-02)
- **Problem:** `frontend/src/api/api-client.js` omits `credentials: 'include'`, dropping session cookies across cross-origin requests.
- **File to Modify:** `frontend/src/api/api-client.js`
- **Diff Specification:**
  ```javascript
  // In frontend/src/api/api-client.js, line 34:
  - const response = await fetch(url, {
  -   ...options,
  -   headers,
  -   signal: controller.signal,
  - });
  + const response = await fetch(url, {
  +   credentials: options.credentials || 'include',
  +   ...options,
  +   headers,
  +   signal: options.signal || controller.signal,
  + });
  ```

---

### 2.3. Task A-3: Eliminate DOM Cross-Site Scripting (XSS) (F-01)
- **Problem:** `HealthPage` renders dynamic error and status strings directly into `innerHTML`.
- **File to Modify:** `frontend/src/pages/health/health-page.js`
- **Specification:**
  1. Add an `escapeHtml` helper function:
     ```javascript
     function escapeHtml(str) {
       if (str === null || str === undefined) return '';
       return String(str)
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;')
         .replace(/'/g, '&#39;');
     }
     ```
  2. Escape all dynamic values interpolated in HTML:
     ```javascript
     // Replace:
     <strong>Error [${error.code || 'UNKNOWN'}]:</strong> ${error.message}
     // With:
     <strong>Error [${escapeHtml(error.code || 'UNKNOWN')}]:</strong> ${escapeHtml(error.message)}
     ```
     Also escape `healthData.status`, `healthData.application`, `healthData.version`, and `latency`.

---

### 2.4. Task A-4: Sanitize Exception Messages in Error Logging (F-04)
- **Problem:** `ExceptionHandler::logError()` logs raw `$e->getMessage()`, which may contain database credentials or secrets.
- **File to Modify:** `backend/app/Core/Exceptions/ExceptionHandler.php`
- **Diff Specification:**
  ```php
  // In ExceptionHandler.php line 62:
      protected static function logError(Throwable $e): void
      {
  -       $safeTrace = preg_replace('/(password|token|secret|key)=([^&\s]+)/i', '$1=***', $e->getTraceAsString());
  -       $logMessage = sprintf(
  -           "[%s] %s in %s:%d\nTrace:\n%s\n",
  -           date('Y-m-d H:i:s'),
  -           $e->getMessage(),
  -           $e->getFile(),
  -           $e->getLine(),
  -           $safeTrace
  -       );
  +       $pattern = '/(password|token|secret|key|pwd)=([^&\s]+)/i';
  +       $safeMessage = preg_replace($pattern, '$1=***', $e->getMessage());
  +       $safeTrace = preg_replace($pattern, '$1=***', $e->getTraceAsString());
  +       $logMessage = sprintf(
  +           "[%s] %s in %s:%d\nTrace:\n%s\n",
  +           date('Y-m-d H:i:s'),
  +           $safeMessage,
  +           $e->getFile(),
  +           $e->getLine(),
  +           $safeTrace
  +       );
  ```

---

### 2.5. Task A-5: Replace Hollow Malformed JSON Unit Test (F-03)
- **Problem:** `RequestResponseTest::testCaptureDetectsMalformedJson()` manually decodes broken JSON and throws its own `BadRequestException`, never testing `Request::capture()`.
- **File to Modify:** `backend/tests/Unit/RequestResponseTest.php`
- **Specification:**
  Test `Request` parsing via a method that feeds malformed input or tests the parsing logic directly:
  ```php
  public function testCaptureDetectsMalformedJson(): void
  {
      $this->expectException(BadRequestException::class);
      $this->expectExceptionMessage('Malformed JSON');

      // Create a mockable or helper method to test JSON parsing directly
      Request::parseJsonBody('{"broken": json');
  }
  ```
  Or add a public static helper `Request::createFromJson(string $method, string $path, string $jsonBody, array $headers)` which is called by `capture()` and can be thoroughly unit tested.

---

## 3. Phase B: Architecture & Infrastructure Enablers

### 3.1. Task B-1: Implement Controller Dependency Injection (F-06)
- **Problem:** `Application::invokeHandler()` instantiates controllers via `new $class()`, forbidding constructor DI.
- **File to Modify:** `backend/app/Core/Application.php`
- **Specification:**
  Introduce a container/resolver callback:
  ```php
  /** @var (callable(class-string): object)|null */
  protected static $container = null;

  public static function setContainer(callable $resolver): void
  {
      self::$container = $resolver;
  }

  protected function resolveController(string $class): object
  {
      if (self::$container !== null) {
          return (self::$container)($class);
      }
      return new $class();
  }
  ```
  This allows controllers to declare typed constructor dependencies (e.g. `GameService`, `BookingRepository`) resolved via a simple factory closure.

---

### 3.2. Task B-2: Route Parameter URL-Decoding & Static Regex Escaping (F-07, F-08, F-09)
- **File to Modify:** `backend/app/Core/Router.php`
- **Specification:**
  1. **URL-Decoding (F-08):** In `Router::match()` line 93:
     ```php
     $params[$paramName] = rawurldecode($matches[$paramName]);
     ```
  2. **Static Segment Escaping (F-07):** In `Router::compileRoute()`:
     Split the path by `{...}` delimiters, run `preg_quote()` on the static segments, and reconnect with named capture groups.
  3. **Route Priority (F-09):** Sort routes during matching or registration so static routes are checked before parameterized routes:
     ```php
     usort($this->routes, function ($a, $b) {
         return count($a['paramNames']) <=> count($b['paramNames']);
     });
     ```

---

### 3.3. Task B-3: Route Grouping & Route-Level Middleware (F-10)
- **File to Modify:** `backend/app/Core/Router.php`
- **Specification:**
  Add support for grouping:
  ```php
  public function group(array $attributes, callable $callback): void
  {
      $previousPrefix = $this->groupPrefix;
      $previousMiddleware = $this->groupMiddleware;

      $this->groupPrefix .= '/' . trim($attributes['prefix'] ?? '', '/');
      $this->groupMiddleware = array_merge($this->groupMiddleware, $attributes['middleware'] ?? []);

      $callback($this);

      $this->groupPrefix = $previousPrefix;
      $this->groupMiddleware = $previousMiddleware;
  }
  ```
  Store route-level middleware in route definitions and execute them within the pipeline runner before invoking the controller action.

---

### 3.4. Task B-4: Lightweight Native PHP Migration Runner (F-14)
- **Problem:** Database migrations are empty and no migration tool is configured.
- **Specification:**
  Create a single, native CLI migration runner `backend/database/migrate.php`:
  1. Creates a `schema_migrations (version VARCHAR(255) PRIMARY KEY, executed_at TIMESTAMP)` table if it does not exist.
  2. Reads all `.sql` files in `backend/database/migrations/` ordered alphabetically (e.g. `001_create_users_table.sql`).
  3. Executes unapplied migrations within a transaction.
  4. Records executed versions in `schema_migrations`.
  - Zero external dependencies.
  - Native PHP PDO execution.
  - Deterministic for all 6 developers.

---

## 4. Verification & Testing Procedure

Following remediation, developers must execute the following verification suite:

```powershell
# 1. Syntax linting
$files = Get-ChildItem -Path backend -Filter "*.php" -Recurse | Where-Object { $_.FullName -notmatch "vendor" }
foreach ($f in $files) { php -l $f.FullName }

# 2. Automated tests
cd backend
.\vendor\bin\phpunit

# 3. Test malformed JSON handling
# Verify that Request::capture() properly returns 400 Bad Request on invalid payloads

# 4. Verify CORS and credentials
# Test OPTIONS preflight with credentials from http://127.0.0.1:3000

# 5. Live E2E Health Check
# Start backend on 8080, start frontend on 3000, verify connection card shows "Connected" in green with latency
```
