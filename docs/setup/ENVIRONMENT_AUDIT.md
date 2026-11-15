# Development Environment Audit — Kịch Bản Sát V16

**Date of Audit:** 2026-11-16  
**Auditor:** Technical Lead / Senior Principal Software Architect  
**Operating System:** Windows Native (Windows 11 / x64)  
**Workspace Root:** `D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP`  

---

## 1. Executive Summary

An exhaustive environment audit was conducted on the development workstation. The system has all necessary runtimes (PHP 8.4, Composer 2.11-dev, MySQL 8.0.44, Git 2.52) installed locally. All mandatory PHP extensions and PDO drivers are compiled and enabled. The MySQL service (`MySQL80`) is active and reachable via PDO.

| Component | Target in Spec | Actual Discovered State | Status | Action / Requirement |
| :--- | :--- | :--- | :--- | :--- |
| **PHP** | 8.4.x / compatible | **PHP 8.4.27 (cli)** (ZTS Visual C++ 2022 x64)<br>Location: `C:\Setup_Environment\php-8.4.27\php.exe` | **READY** | Set session PATH or invoke explicitly |
| **PHP Extensions** | pdo, pdo_mysql, mbstring, openssl, fileinfo, json, session | All 7 extensions active (`php -m` verified) | **READY** | None needed |
| **PDO Drivers** | mysql | `mysql`, `sqlite` available | **READY** | Direct MySQL PDO verified |
| **Composer** | Composer 2.x | **Composer 2.11-dev**<br>Location: `C:\Setup_Environment\composer\composer.phar` (`composer.bat`) | **READY** | Run via PHP CLI or session PATH |
| **Git** | Git 2.x | **git version 2.52.0.windows.1**<br>Location: `C:\Program Files\Git\cmd\git.exe` | **READY** | Available in system PATH |
| **MySQL Server** | MySQL 8.4 LTS (proposed) | **MySQL 8.0.44 Community Server**<br>Location: `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe`<br>Windows Service: `MySQL80` (Running) | **READY** (Version discrepancy noted) | Keep 8.0.44; record as open decision |
| **Node.js / npm** | Prohibited (no framework) | Not required for Vanilla JS / HTML / CSS | **EXCLUDED** | No action |
| **Containerization** | Prohibited (no Docker) | Windows native execution | **CONFIRMED** | No Docker needed |

---

## 2. Runtime & Executable Inspection Details

### 2.1. PHP Runtime
- **Version:** `PHP 8.4.27 (cli) (built: Oct 20 2026 04:19:51) (ZTS Visual C++ 2022 x64)`
- **Binary Path:** `C:\Setup_Environment\php-8.4.27\php.exe`
- **Loaded php.ini:** `C:\Setup_Environment\php-8.4.27\php.ini`
- **Active Extensions:**
  - `Core`, `date`, `pcre`, `zlib`, `filter`, `hash`, `json`, `SPL`, `Reflection`, `standard`
  - `session` (Active)
  - `openssl` (Active)
  - `mbstring` (Active)
  - `fileinfo` (Active)
  - `PDO` (Active)
  - `pdo_mysql` (Active)
  - `mysqli`, `mysqlnd` (Active)
  - `curl`, `dom`, `gd`, `iconv`, `intl`, `libxml`, `SimpleXML`, `xml`, `zip` (Active)
- **Available PDO Drivers:** `['mysql', 'sqlite']`

### 2.2. Composer
- **Version:** `Composer version 2.11-dev+5147ea1a01c124f461090a72bee6ea6de02a67a6 (2.11-dev) 2026-11-13 10:42:45`
- **Execution Script:** `C:\Setup_Environment\composer\composer.phar`
- **Runner Batch:** `C:\Setup_Environment\composer\composer.bat`
- **PHP Association:** Uses `C:\Setup_Environment\php-8.4.27\php.exe`

### 2.3. MySQL Database Server
- **Version:** `8.0.44` (MySQL Community Server - GPL)
- **Client Path:** `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe`
- **Service Name:** `MySQL80`
- **Service Status:** `Running` (Automatic start)
- **Connection Test:** Successfully connected using PDO over `127.0.0.1:3306` with charset `utf8mb4`.
- **Note on Spec Discrepancy:** The draft specification (`PRODUCT_SPEC_V16_Draft.md`) proposed MySQL 8.4 LTS. The machine has MySQL 8.0.44 actively running. Both versions support `utf8mb4_0900_ai_ci`, window functions, CTEs, and JSON data types. MySQL 8.0.44 is fully sufficient for MVP needs.

### 2.4. Git & Existing Repository Status
- **Git Version:** `2.52.0.windows.1`
- **Git Binary:** `C:\Program Files\Git\cmd\git.exe`
- **Repository State:** A `.git` directory was pre-initialized in `D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP`, currently on branch `master` with no commits.
- **Untracked files present:**
  - `IMPLEMENTATION_PLAN_V16_Draft.md` (Preserved intact)
  - `PRODUCT_SPEC_V16_Draft.md` (Preserved intact)
- **Remote:** No remotes configured (`git remote -v` is empty). Local Git discipline will be enforced.

---

## 3. Specification Baseline Discovery

The following authoritative documents are present in the workspace root and were inspected:
1. `PRODUCT_SPEC_V16_Draft.md` (Version: V16 Draft 0.2)
2. `IMPLEMENTATION_PLAN_V16_Draft.md` (Version: V16 Draft 0.1)

In addition, baseline V15 references were located in sibling directories:
- `D:\LapTrinhWeb\Project\Booking_kich-ban-sat\PRODUCT_SPEC_V15_Final.md`
- `D:\LapTrinhWeb\Project\Booking_kich-ban-sat\IMPLEMENTATION_PLAN_V15_Final.md`

All business logic, states, deadlines, and policies from V15 are preserved as the business source of truth.

---

## 4. PATH and Environment Isolation Strategy

The commands `php`, `composer`, and `mysql` are not registered in the persistent system-wide PATH. In accordance with safety rules:
- **No persistent global system changes** will be made without explicit user request.
- Within development and test automation sessions, the local process environment variable `$env:PATH` will be prepended:
  ```powershell
  $env:PATH = "C:\Setup_Environment\php-8.4.27;C:\Setup_Environment\composer;C:\Program Files\MySQL\MySQL Server 8.0\bin;$env:PATH"
  ```
- All scripts and automation can also reference the direct binary paths:
  - PHP: `C:\Setup_Environment\php-8.4.27\php.exe`
  - Composer: `C:\Setup_Environment\php-8.4.27\php.exe C:\Setup_Environment\composer\composer.phar`
  - MySQL: `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe`

---

## 5. Audit Acceptance Verification

- [x] Workspace root identified (`D:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP`).
- [x] Both V16 draft documents thoroughly read and cross-referenced with V15.
- [x] Actual runtime versions verified via live execution.
- [x] Missing global PATH aliases noted without modifying system configuration.
- [x] Existing specification documents preserved without modification.
- [x] No unauthorized frameworks or containerization introduced.
