# Kịch Bản Sát V16 — Web-based Booking Platform

An autonomous, lightweight web booking platform for tabletop role-playing games (Jubensha / murder mystery / kịch bản sát) featuring multi-portal access (Player, DM, Admin) with state-machine-driven lifecycle management.

---

## 1. Project Overview

Kịch Bản Sát V16 provides a seamless booking experience for tabletop mystery games:
- **Player Portal:** Browse catalog, form/join game rooms, schedule sessions, pay simulated deposits, request room leave, and track game progress.
- **DM Portal:** Manage assignments, accept/decline game hosting, review participants and venue details.
- **Admin Portal:** Manage rooms, physical spaces, DM dispatching, session proposals, refunds, and room reopenings.

V16 inherits the complete business domain, state machines, policies, and invariants from the approved **V15 Business Specification** (`PRODUCT_SPEC_V15_Final.md`), implemented using a clean, native PHP MVC backend and vanilla JavaScript frontend.

---

## 2. Architecture

The system utilizes a **Feature-oriented + Layered MVC Architecture** with strict separation of concerns:

```text
Browser (Vanilla JS ES Modules)
   │
   │ fetch() [JSON over HTTP]
   ▼
backend/public/index.php (Front Controller)
   │
   ▼
App\Core\Router (Method & Path Dispatching)
   │
   ▼
App\Core\Middleware (CORS, Security, Content-Type)
   │
   ▼
Controller (Validates Input & Dispatches Requests)
   │
   ▼
Application Service (Business Orchestration & State Guards)
   │
   ▼
Repository (PDO Prepared Statements)
   │
   ▼
MySQL Database (utf8mb4, Strict Mode)
```

### Architectural Principles:
1. **Zero External Frameworks:** Pure Native PHP and Vanilla JavaScript. No Laravel, Symfony, React, or Vue.
2. **Layered Boundaries:** Controllers never execute SQL; Services never manipulate HTTP requests directly; Repositories never render presentation data; Frontend never directly touches the database.
3. **Prepared Statements Exclusively:** Every parameter query uses bound PDO parameters to eliminate SQL injection vulnerabilities.
4. **Native Windows Operation:** No Docker or virtual container overhead.

---

## 3. Technology Stack

- **Backend Runtime:** PHP Native (>= 8.2, verified with PHP 8.4.27 CLI).
- **Persistence:** MySQL Community Server 8.0 / 8.4 LTS via PDO with prepared statements.
- **Dependency & Autoloading:** Composer (PSR-4 autoloading only).
- **Testing:** PHPUnit 11.x.
- **Frontend:** Standard HTML5, CSS3, Vanilla JavaScript (ES Modules, Fetch API).
- **Version Control:** Git (Local-first workflow, `main` and `develop` branches).

---

## 4. Requirements & Prerequisites

- **Operating System:** Windows 10/11 x64.
- **PHP CLI:** PHP 8.4.x (or 8.2+) with `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, and `session` extensions enabled.
- **Composer:** Composer 2.x.
- **MySQL:** MySQL Server 8.0.x / 8.4.x running as a local service.
- **Git:** Git 2.40+.

See detailed setup in [docs/setup/PREREQUISITES.md](file:///d:/LapTrinhWeb/Project/Booking_kich-ban-sat_PHP/docs/setup/PREREQUISITES.md) and audit in [docs/setup/ENVIRONMENT_AUDIT.md](file:///d:/LapTrinhWeb/Project/Booking_kich-ban-sat_PHP/docs/setup/ENVIRONMENT_AUDIT.md).

---

## 5. Installation & Local Setup

### Step 1: Clone & Environment Setup
Clone or navigate to the project directory:
```powershell
cd d:\LapTrinhWeb\Project\Booking_kich-ban-sat_PHP
```

Ensure PHP and Composer are accessible in your PowerShell terminal:
```powershell
$env:PATH = "C:\Setup_Environment\php-8.4.27;C:\Setup_Environment\composer;C:\Program Files\MySQL\MySQL Server 8.0\bin;$env:PATH"
```

### Step 2: Configure Environment Variables
Copy the template configuration in `backend/`:
```powershell
Copy-Item backend/.env.example backend/.env
```
Edit `backend/.env` with your local database credentials:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_v16
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 3: Install Backend Dependencies
Run composer from the `backend/` directory:
```powershell
cd backend
composer install
cd ..
```

---

## 6. Local Development & Running the Application

### 6.1. Start the Backend API Server
Start PHP's built-in development server from `backend/public`:
```powershell
php -S 127.0.0.1:8080 -t backend/public
```
The backend health check will be accessible at:
```text
http://127.0.0.1:8080/api/health
```

### 6.2. Start the Frontend Server
Serve the frontend directory statically:
```powershell
php -S 127.0.0.1:3000 -t frontend
```
Open `http://127.0.0.1:3000` in your web browser.

---

## 7. Testing

Execute the PHPUnit automated test suite:
```powershell
cd backend
.\vendor\bin\phpunit
```

---

## 8. Git Workflow & Discipline

- **Branches:**
  - `main`: Production-ready, verified releases.
  - `develop`: Primary integration branch.
  - `feature/<name>`: Specific vertical slices or tasks.
- **Commit Format:** `<type>: <short description>`
  - Allowed types: `feat`, `fix`, `chore`, `refactor`, `test`, `docs`, `security`.
- **Commit Rules:**
  - Never commit `.env` or sensitive credentials.
  - Review `git status` and `git diff` before staging.
  - Ensure all acceptance criteria and tests pass before committing.
