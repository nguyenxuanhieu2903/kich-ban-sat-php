# ADR-0001: Project Foundation & Technology Baseline

**Status:** Accepted (Foundation) / Proposed (Open items documented)  
**Date:** 2026-11-16  
**Authors:** Senior Principal Software Architect, Technical Lead  
**Deciders:** Project Lead, Engineering Team  

---

## 1. Context & Problem Statement

The **Kịch Bản Sát** project is transitioning from a legacy heterogeneous stack (Next.js/React + Java Spring Boot + PostgreSQL) to an unified, lightweight, native stack in **V16**. The project is a web-based booking platform for tabletop role-playing games (murder mystery games / kịch bản sát) with multi-portal access (Player, DM, Admin).

The engineering objective is to initialize a clean, reproducible, secure, and maintainable foundation without external framework bloat, running natively on Windows without container overhead.

---

## 2. Decision Drivers

- **Business Fidelity:** Retain all accepted business state machines, rules, and invariants from V15.
- **Architectural Clarity:** Maintain clean separation of concerns using Feature-oriented + Layered MVC.
- **Zero Framework Bloat:** Avoid large frameworks (Laravel, Symfony, React, Vue) to keep runtime overhead minimal, execution transparent, and dependencies lean.
- **Native Windows Development:** Run directly on Windows without Docker or virtualization friction.
- **Security & Integrity:** Prevent SQL injection via PDO prepared statements, prevent secret leakage, follow OWASP principles.

---

## 3. Considered Options

1. **Option A:** Full-stack framework (e.g., Laravel / Next.js) — *Rejected* by project specification and user direction.
2. **Option B:** Micro-framework (Slim / Lumen) — *Rejected* as external frameworks are strictly prohibited.
3. **Option C:** Native PHP Feature-oriented MVC + Vanilla JS ES Modules + Native MySQL PDO — *Selected*.

---

## 4. Decision Outcome

### 4.1. Backend Architecture
- **Runtime:** Native PHP 8.4.27 on Windows.
- **Architecture:** Feature-oriented + Layered MVC (`Core`, `Infrastructure`, `Shared`, `Features`).
- **HTTP Routing & Dispatch:** Custom lightweight Front Controller (`backend/public/index.php`), Request, Response, Router, and Middleware pipeline.
- **Persistence:** Direct PDO with MySQL, strict error mode (`PDO::ERRMODE_EXCEPTION`), prepared statements, UTF-8 (`utf8mb4`).
- **Autoloading:** Composer with PSR-4 autoloading (`App\` -> `app/`, `Tests\` -> `tests/`).
- **Unit Testing:** PHPUnit 11.x (compatible with PHP 8.4).

### 4.2. Frontend Architecture
- **Stack:** Standard HTML5, CSS3, and Vanilla JavaScript with ES Modules (`type="module"`).
- **Communication:** Standard browser `fetch()` API consuming backend REST JSON endpoints.
- **Build System:** None (no Webpack, Vite, or npm build steps required). Modules load natively in modern browsers.

### 4.3. Version Control & Repository
- **Tool:** Local Git repository.
- **Branches:** `main` (production-ready baseline), `develop` (integration branch).
- **Hosting / Remote:** Local Git only for foundation phase. No remote pushes or public repositories.

---

## 5. Status of Technical Decisions

| Decision Item | Status | Approved Detail |
| :--- | :--- | :--- |
| PHP Native MVC Core | **Accepted** | Custom router, request/response, middleware, controller |
| Frontend Vanilla JS/HTML/CSS | **Accepted** | ES Modules, Fetch API, no framework |
| MySQL Database Engine | **Accepted** | MySQL Community Server 8.0.44 (LTS 8.4 compatible) |
| Composer PSR-4 Autoload | **Accepted** | Autoloading without framework runtime |
| PDO Persistence Pattern | **Accepted** | Prepared statements, Repository pattern, no ORM |
| Containerization (Docker) | **Rejected** | Prohibited in V16 |
| PHP Frameworks (Laravel, Symfony) | **Rejected** | Prohibited in V16 |
| Frontend Frameworks (React, Vue) | **Rejected** | Prohibited in V16 |
| Database Migration Tool | **Open Decision** | Custom lightweight migration runner vs Phinx to be reviewed |
| Session / Token Authentication | **Proposed** | Session/cookie authentication proposed for MVP web portal |

---

## 6. Consequences & Mitigations

- **Positive:**
  - Fast execution speed and near-instant local bootstrapping.
  - Zero framework version lock-in or breaking upgrade cycles.
  - Transparent debugging without opaque framework magic.
  - Absolute adherence to project specification and constraints.

- **Negative / Risks:**
  - Boilerplate routing, validation, and middleware must be maintained within the project core.
  - *Mitigation:* Keep the core minimal, strictly tested, and focused solely on requirements.
