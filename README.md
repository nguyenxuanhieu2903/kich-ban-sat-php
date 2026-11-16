# Kịch Bản Sát V16 — Web-based Booking Platform

An autonomous, lightweight web booking platform for tabletop role-playing games (Jubensha / murder mystery / kịch bản sát).

> [!NOTE]
> **Milestone Status:** Milestone **P0.4 (Platform Technical Foundation)** is **ACCEPTED**.<br>
> The core technical architecture, routing, middleware, exception sanitization, and automated test baselines are verified. Business domain portals (Player, DM, Admin) represent approved target MVP scope scheduled for subsequent development milestones.

---

## 1. Project Overview

Kịch Bản Sát V16 provides an autonomous web booking platform tailored for tabletop role-playing and mystery games.

- **Current Milestone:** P0.4 Foundation accepted (see [docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md](docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md)).
- **Implementation Status:** Platform infrastructure and health verification endpoints are active. All business portals are planned MVP scope.
- **Specification Baseline:** V15 is the approved business baseline. V16 inherits and extends V15's approved business requirements with more detailed implementation-oriented specifications. [PRODUCT_SPEC_V16_Draft.md](PRODUCT_SPEC_V16_Draft.md) is the draft successor to V15 and remains subject to formal review and approval.

---

## 2. Project Scope

The approved MVP scope consists of three planned portals:

- **Player Portal (Planned):** Browse game catalog, create and join rooms, schedule game sessions, simulate deposit payments, request room departure, and track session status.
- **DM Portal (Planned):** Receive game assignments, accept or decline session hosting, review player rosters, and inspect venue assignments.
- **Admin Portal (Planned):** Manage game rooms and physical venues, dispatch DMs, review session proposals, process refunds, and manage room reopenings.

For full business requirements and task roadmaps, see [PRODUCT_SPEC_V16_Draft.md](PRODUCT_SPEC_V16_Draft.md) and [IMPLEMENTATION_PLAN_V16_Draft.md](IMPLEMENTATION_PLAN_V16_Draft.md).

---

## 3. Project Structure

The repository uses a **Feature-oriented + Layered Architecture** separating the Native PHP backend, Vanilla JavaScript frontend, and project documentation:

```text
Booking_kich-ban-sat_PHP/
├── backend/                       # Native PHP Backend Application
│   ├── app/                       # Application source code (PSR-4 App\)
│   │   ├── Core/                  # Framework-less HTTP core (Router, Middleware, Request, Response)
│   │   ├── Features/              # [Planned] Feature vertical slices (.gitkeep)
│   │   ├── Infrastructure/        # Config parser and PDO Database connection factory
│   │   └── Shared/                # [Planned] Cross-cutting domain helpers (.gitkeep)
│   ├── bootstrap/                 # Application bootstrap (app.php)
│   ├── database/                  # Connection diagnostic tool, [Planned] migrations & seeders
│   ├── public/                    # Web server document root (index.php Front Controller)
│   ├── routes/                    # API route declarations (api.php)
│   ├── tests/                     # Automated PHPUnit test suites (Feature/ & Unit/)
│   ├── .env.example               # Template environment configuration
│   ├── composer.json              # Autoloading and dependency definitions
│   ├── composer.lock              # Locked dependencies (PHPUnit 11.5.58)
│   └── phpunit.xml                # PHPUnit configuration
│
├── frontend/                      # Vanilla JavaScript Single Page Application
│   ├── index.html                 # Main HTML entry point
│   ├── public/                    # [Planned] Static assets (.gitkeep)
│   ├── src/                       # Frontend source modules
│   │   ├── api/                   # HTTP client abstractions (api-client.js, health-api.js)
│   │   ├── components/            # [Planned] Reusable UI components (.gitkeep)
│   │   ├── layouts/               # [Planned] Layout wrappers (.gitkeep)
│   │   ├── pages/                 # Page controllers (currently pages/health/health-page.js)
│   │   ├── styles/                # CSS styles (reset.css, variables.css, main.css)
│   │   ├── utils/                 # Configuration and helpers (config.js)
│   │   └── app.js                 # Frontend application entry script
│   └── tests/                     # Node.js automated tests (api-client.test.mjs)
│
├── docs/                          # Architecture, audit, and setup documentation
├── .gitignore                     # Git ignore rules
├── IMPLEMENTATION_PLAN_V16_Draft.md # Implementation plan (draft under review)
├── PRODUCT_SPEC_V16_Draft.md      # Technical specification (draft successor to V15)
└── README.md                      # Project documentation and onboarding guide
```

### Key Directory Responsibilities

| Directory | Status | Responsibility |
| :--- | :---: | :--- |
| `backend/app/Core/` | **Implemented** | Framework-less HTTP core: Router, Middleware, Request, Response, and ExceptionHandler. |
| `backend/app/Features/` | *Planned* (`.gitkeep`) | Location for future feature vertical slices (Controllers, Services, Repositories). |
| `backend/app/Infrastructure/` | **Implemented** / *Planned* | Environment configuration reader (`Config.php`) and PDO `ConnectionFactory.php`. Logging is planned. |
| `backend/database/` | **Implemented** / *Planned* | Connection verification script (`verify-connection.php`). Migrations and seeders are planned for Phase 1. |
| `backend/tests/` | **Implemented** | Automated PHPUnit test suites: Feature integration and Unit tests. |
| `frontend/src/` | **Implemented** / *Planned* | Fetch API client, health page controller, styles, and config. Visual components and portal views are planned. |
| `frontend/tests/` | **Implemented** | Automated Node.js frontend tests for API client abort, timeout, and error handling. |
| `docs/` | **Implemented** / *Planned* | Architecture decision records (`adr/`), audit and acceptance reports (`audit/`), and developer guides (`setup/`). |

*(Note: Local-only directories `backend/vendor/`, `backend/.phpunit.cache/`, and local `backend/.env` are excluded from version control via `.gitignore`).*

---

## 4. Architecture

Kịch Bản Sát V16 follows a **Feature-oriented + Layered Architecture** with zero external runtime frameworks. On the backend, an internal **Model-View-Controller (MVC)** pattern separates HTTP delivery, application logic, and database access.

### System Flow Diagram

```text
Browser (Vanilla JS ES Modules)
   │
   │ fetch() [JSON over HTTP]
   ▼
backend/public/index.php (Front Controller)
   │
   ▼
App\Core\Router -> App\Core\Middleware (CORS, Security)
   │
   ▼
[Target Feature Layers: Phase 1+]
Controller (Input Validation & Response Envelope)
   │
   ▼
Application Service (Business Logic & State Guards)
   │
   ▼
Repository (PDO Prepared Statements)
   │
   ▼
MySQL Database (utf8mb4, Strict Mode)
```

- **Existing Foundation (Milestone P0.4):** Front Controller, HTTP Router, CORS Middleware, Request/Response abstractions, ExceptionHandler with credential redaction, and PDO Connection Factory. Dispatches `GET /api/health`.
- **Target Feature Architecture (Phase 1+):** Business logic, domain controllers, application services, repositories, and relational database tables will be introduced modularly per feature vertical slice.
- **Data Security Rule:** All queries involving dynamic or external input must use PDO prepared statements with bound parameters. String concatenation of untrusted input is strictly prohibited.

---

## 5. Technology Stack

| Technology | Minimum / Required Version | Verified Workstation Baseline | Purpose |
| :--- | :--- | :--- | :--- |
| **PHP CLI** | 8.2+ | PHP 8.4.27 (cli) ZTS x64 | Backend API runtime & development server |
| **PHP Extensions** | Mandatory | `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, `session` | Core runtime capabilities |
| **MySQL Server** | 8.0+ | MySQL Community Server 8.0.44 | Relational database (service: `MySQL80`) |
| **Composer** | 2.x | Composer 2.11-dev | PSR-4 autoloading & dev dependency management |
| **Node.js** | 20+ | Node.js 24.22.1 | Built-in test runner (`node --test`) for frontend |
| **PHPUnit** | ^11.5 | PHPUnit 11.5.58 | Backend automated testing |
| **Frontend** | Modern Browser | HTML5, CSS3, Vanilla JS ES Modules | Native browser client, zero build toolchain |
| **Git** | 2.40+ | Git 2.52.0.windows.1 | Local-first version control |
| **Platform** | Windows 10/11 x64 | Windows 11 Native (No Docker) | Development operating environment |

---

## 6. Installation & Setup

### Step 1: Open Terminal & Verify PATH
Open Windows PowerShell in the repository root:
```powershell
cd /path/to/Booking_kich-ban-sat_PHP
```
Ensure `php`, `composer`, `node`, and `mysql` are available in your PowerShell session. If not registered globally, add them to `$env:PATH` for the session (see reference paths in [docs/setup/PREREQUISITES.md](docs/setup/PREREQUISITES.md)).

### Step 2: Configure Environment Variables
Copy the template configuration:
```powershell
Copy-Item backend/.env.example backend/.env
```
Open `backend/.env` and update your local database credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Never commit `.env` to Git.

### Step 3: Install Backend Dependencies
```powershell
cd backend
composer install
cd ..
```

### Step 4: Verify Database Connectivity (Optional)
To verify PDO connectivity against your local MySQL service:
```powershell
php backend/database/verify-connection.php
```
*(Requires a running MySQL service and an existing database matching `DB_DATABASE`. Table migrations will be introduced in Phase 1).*

For detailed environment audit notes, see [docs/setup/ENVIRONMENT_AUDIT.md](docs/setup/ENVIRONMENT_AUDIT.md).

---

## 7. Running the Application

### 7.1. Start Backend API Server
```powershell
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```
Verify the health check endpoint at `http://127.0.0.1:8080/api/health`. Expected response:
```json
{"success":true,"data":{"status":"ok","application":"Kich Ban Sat","version":"16"}}
```

### 7.2. Start Frontend Server
In a separate terminal:
```powershell
php -S 127.0.0.1:3000 -t frontend
```
Open `http://127.0.0.1:3000` in your browser to access the Platform Foundation health status interface.

---

## 8. Testing

Execute automated test suites from the repository root:

```powershell
# 1. Backend PHPUnit test suite (52 tests, 190 assertions)
cd backend
.\vendor\bin\phpunit
composer validate
cd ..

# 2. Frontend Node.js native test suite (7 tests)
node --test frontend/tests/api-client.test.mjs
```

> [!NOTE]
> The metrics above represent the verified **Foundation baseline**. Feature-specific tests will be added alongside feature implementation in Phase 1+. For full verification logs, see [docs/setup/FOUNDATION_VERIFICATION.md](docs/setup/FOUNDATION_VERIFICATION.md).

---

## 9. Git Workflow

- **Branch Structure:**
  - `main`: Production-ready, accepted milestone releases.
  - `develop`: Integration branch for active work.
  - Feature branches: `feat/<name>`, `fix/<name>`, `test/<name>`.
- **Workflow Rules:**
  - All feature branches branch off `develop` and merge back into `develop` via Pull Requests.
  - Releases are merged from `develop` into `main` upon milestone acceptance.
  - All tests (`phpunit`, `node --test`) and `composer validate` must pass before opening a Pull Request.
  - Peer review is required prior to merging.
- **Commit Formatting:** `<type>: <short description>` (`feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `security`).
- **Discipline:** Never commit `.env` files, credentials, or `vendor/` directories.

---

## 10. Documentation

For in-depth architectural and operational records, refer to:
- [PRODUCT_SPEC_V16_Draft.md](PRODUCT_SPEC_V16_Draft.md) — Product & technical specification (draft successor to V15).
- [IMPLEMENTATION_PLAN_V16_Draft.md](IMPLEMENTATION_PLAN_V16_Draft.md) — Multi-phase implementation roadmap.
- [docs/adr/ADR-0001-project-foundation.md](docs/adr/ADR-0001-project-foundation.md) — Foundation architectural decision record.
- [docs/setup/PREREQUISITES.md](docs/setup/PREREQUISITES.md) — Development workstation prerequisites.
- [docs/setup/ENVIRONMENT_AUDIT.md](docs/setup/ENVIRONMENT_AUDIT.md) — Workstation runtime audit and extension checks.
- [docs/setup/FOUNDATION_VERIFICATION.md](docs/setup/FOUNDATION_VERIFICATION.md) — Detailed verification procedures and command runbooks.
- [docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md](docs/audit/P0_4_FOUNDATION_ACCEPTANCE_REPORT.md) — Formal P0.4 Milestone Acceptance Report.
