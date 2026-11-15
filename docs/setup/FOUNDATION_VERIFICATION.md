# Foundation Verification Report — Kịch Bản Sát V16

**Verification Date:** 2026-11-16  
**Auditor:** Senior Principal Software Architect / Technical Lead  
**Operating System:** Windows Native (Windows 11 x64)  
**Workspace:** `D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP`  

---

## 1. Executive Status Scorecard

| Area | Status | Verification Summary |
| :--- | :---: | :--- |
| **1. Environment** | **PASS** | PHP 8.4.27 CLI, MySQL 8.0.44 Server, Composer 2.11-dev, Git 2.52 verified. |
| **2. Repository** | **PASS** | Local Git initialized, `main` & `develop` branches created, strict `.gitignore` active. |
| **3. Composer** | **PASS** | Valid `composer.json`, PSR-4 autoloading (`App\`, `Tests\`), `composer.lock` committed. |
| **4. Backend Core** | **PASS** | Pure Native PHP MVC: Application, Router, Request, Response, Middleware, ExceptionHandler. |
| **5. Database Connectivity** | **PASS** | PDO connection with `ERRMODE_EXCEPTION`, `utf8mb4`, prepared statements verified against live MySQL service (`MySQL80`). |
| **6. Frontend Foundation** | **PASS** | Vanilla HTML5/CSS3/ES Modules, Fetch API client, health check UI component with loading/success/error states. |
| **7. Full Integration** | **PASS** | End-to-end request dispatch verified over HTTP: Browser/Client -> Front Controller -> Router -> Handler -> JSON Response -> Frontend UI. |
| **8. Automated Tests** | **PASS** | 18 PHPUnit tests (Unit & Feature), 53 assertions, 0 failures. |
| **9. Git Commit Discipline** | **VERIFIED** | 5 atomic commits for 5 sequential phases with zero secrets or unreviewed files. |

---

## 2. Environment & Runtime Versions

- **PHP CLI:** `8.4.27 (cli) (built: Oct 20 2026 04:19:51) (ZTS Visual C++ 2022 x64)`
  - Binary: `C:\Setup_Environment\php-8.4.27\php.exe`
  - Loaded Configuration File: `C:\Setup_Environment\php-8.4.27\php.ini`
  - Verified Extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, `session`, `curl`, `intl`, `zip`.
  - Available PDO Drivers: `mysql`, `sqlite`.
- **Composer:** `Composer version 2.11-dev+5147ea1a01c124f461090a72bee6ea6de02a67a6 (2026-11-13)`
  - Location: `C:\Setup_Environment\composer\composer.phar`
- **Database Server:** `MySQL Community Server 8.0.44 for Win64 on x86_64`
  - Windows Service: `MySQL80` (Running)
  - Port: `3306`
  - Charset: `utf8mb4` / Collation: `utf8mb4_unicode_ci`
- **Git Version:** `git version 2.52.0.windows.1`
  - Binary: `C:\Program Files\Git\cmd\git.exe`

---

## 3. Commands Executed & Evidence

### 3.1. Syntax Linting
```powershell
$phpFiles = Get-ChildItem -Path backend -Filter "*.php" -Recurse | Where-Object { $_.FullName -notmatch "vendor" }
foreach ($file in $phpFiles) { php -l $file.FullName }
```
**Output:** 23 files checked, 0 syntax errors detected.

### 3.2. Composer Validation & Autoloader Optimization
```powershell
cd backend
composer validate
composer dump-autoload --optimize
```
**Output:**
```text
./composer.json is valid
Generating optimized autoload files
Generated optimized autoload files containing 1538 classes
```

### 3.3. PHPUnit Automated Test Execution
```powershell
cd backend
.\vendor\bin\phpunit --testdox
```
**Output:**
```text
PHPUnit 11.5.58 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.27
Configuration: D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP\backend\phpunit.xml

Application (Tests\Feature\Application)
 ✔ Health endpoint returns success json
 ✔ Unknown route returns 404 json
 ✔ Unsupported method returns 405 with allow header
 ✔ Exception handler handles custom bad request
 ✔ Uncaught exception in production hides details

Database Connection (Tests\Unit\DatabaseConnection)
 ✔ Connection failure throws database exception without leaking credentials
 ✔ Transaction helper commits successfully
 ✔ Transaction helper rolls back on exception
 ✔ Check connectivity returns false on broken connection

Request Response (Tests\Unit\RequestResponse)
 ✔ Request normalizes method and path
 ✔ Response generates valid json
 ✔ Response error formatting
 ✔ Capture detects malformed json

Router (Tests\Unit\Router)
 ✔ Matches valid route
 ✔ Throws not found for unknown route
 ✔ Throws method not allowed when path exists for different method
 ✔ Matches route with parameters
 ✔ Matches route with multiple parameters

OK (18 tests, 53 assertions)
```

### 3.4. Live HTTP API & CORS Verification
Server started on `127.0.0.1:8080`:
```powershell
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```

1. **GET `/api/health`:**
   - Status: `200 OK`
   - Content-Type: `application/json; charset=utf-8`
   - Response Body:
     ```json
     {
       "success": true,
       "data": {
         "status": "ok",
         "application": "Kich Ban Sat",
         "version": "16"
       }
     }
     ```
2. **OPTIONS `/api/health` (CORS Preflight from `http://127.0.0.1:3000`):**
   - Status: `204 No Content`
   - Header `Access-Control-Allow-Origin`: `http://127.0.0.1:3000`
   - Header `Access-Control-Allow-Methods`: `GET, POST, PUT, PATCH, DELETE, OPTIONS`
   - Header `Access-Control-Allow-Credentials`: `true`
3. **GET `/api/nonexistent`:**
   - Status: `404 Not Found`
   - Response: `{"success":false,"error":{"code":"NOT_FOUND","message":"Route not found: GET /api/nonexistent"}}`
4. **POST `/api/health`:**
   - Status: `405 Method Not Allowed`
   - Header `Allow`: `GET`
   - Response: `{"success":false,"error":{"code":"METHOD_NOT_ALLOWED","message":"HTTP method POST is not supported for path /api/health"}}`

### 3.5. Live MySQL Database Connectivity
Execution of `backend/database/verify-connection.php`:
```powershell
php backend/database/verify-connection.php
```
**Output:**
```text
============================================
  Kịch Bản Sát V16 - DB Connectivity Check  
============================================
Target Host: 127.0.0.1:3306
Database:    booking_v16
User:        root
Status:      Connecting...
RESULT:      SUCCESS
Server:      MySQL 8.0.44
PDO Mode:    ERRMODE_EXCEPTION enabled
Emulation:   PDO::ATTR_EMULATE_PREPARES disabled
```

---

## 4. Known Limitations & Open Architectural Decisions

1. **MySQL Server Version:**
   - Draft specification proposed MySQL 8.4 LTS.
   - Workstation has active MySQL 8.0.44 Community Server.
   - *Status:* Fully compatible with UTF-8 (`utf8mb4`), window functions, CTEs, and JSON operations required for MVP. Documented as an approved operational choice.
2. **Database Migration Tool:**
   - Remains an **OPEN** decision. No migration package (Phinx, Doctrine Migrations, or bespoke SQL runner) was prematurely installed.
3. **Authentication Strategy:**
   - Session/cookie authentication is proposed in V16 draft; concrete session stores and user identity tables will be established during Phase 1/Phase 2 business domain implementation.
4. **Port Allocation:**
   - Port `8000` was observed occupied by another local project on the workstation (`Booking_kich-ban-sat_Laravel`).
   - The V16 development topology defaults to port `8080` for Backend API and port `3000` for Frontend static server to prevent port conflicts without killing existing user services.

---

## 5. Chronological Git Commit History

```text
* 759ac2f (HEAD -> develop) feat: implement frontend foundation
* 01a7dc0 feat: add PDO database infrastructure
* 214646a feat: implement backend core foundation
* 5cb2e6e chore: scaffold project directory structure
* 1a1b59b (main) chore: initialize local project repository
```

All acceptance criteria for the Foundation stage have been met in full with verified automated and live execution evidence.
