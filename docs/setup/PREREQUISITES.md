# Development Prerequisites — Kịch Bản Sát V16

**Target Platform:** Windows Native (Windows 10/11 x64)  
**IDE:** Google Antigravity / Visual Studio Code  
**Version:** V16 Technical Foundation  

This document details the exact setup requirements for any developer working on Kịch Bản Sát V16.

---

## 1. Required Software Runtimes

| Software | Required Version | Discovered Path on Reference System | Purpose |
| :--- | :--- | :--- | :--- |
| **PHP CLI** | 8.2+ (Recommended: 8.4.27) | `C:\Setup_Environment\php-8.4.27\php.exe` | Backend execution & Built-in HTTP server |
| **Composer** | 2.x (Recommended: 2.11+) | `C:\Setup_Environment\composer\composer.phar` | Autoloading (PSR-4) & Dev dependencies |
| **MySQL Server** | 8.0.x or 8.4.x LTS | `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe` | Relational Database & State Persistence |
| **Git** | 2.40+ | `C:\Program Files\Git\cmd\git.exe` | Local Version Control |
| **Modern Browser** | Chrome / Edge / Firefox | Standard system browser | Frontend execution with ES Modules support |

---

## 2. Mandatory PHP Extensions

The following extensions must be enabled in `php.ini`:

1. `pdo` — Core database abstraction layer.
2. `pdo_mysql` — MySQL driver for PDO.
3. `mbstring` — Multi-byte string manipulation for Vietnamese text encoding (`UTF-8`).
4. `openssl` — Cryptographic functions, random bytes, and hashing.
5. `fileinfo` — MIME type detection for uploads.
6. `json` — JSON encoding/decoding for REST API.
7. `session` — Session management for web authentication.

To verify extensions in PowerShell:
```powershell
php -m
```

---

## 3. Environment Variable Configuration

### 3.1. Developer Session PATH (Windows PowerShell)
If `php` and `composer` are not in your persistent system PATH, run the following in your PowerShell terminal before starting work:

```powershell
$env:PATH = "C:\Setup_Environment\php-8.4.27;C:\Setup_Environment\composer;C:\Program Files\MySQL\MySQL Server 8.0\bin;$env:PATH"
```

Verify with:
```powershell
php -v
composer --version
mysql --version
git --version
```

### 3.2. Project Environment File (`.env`)
The backend relies on environment variables loaded from a project-local `.env` file located at `backend/.env`.

A template is provided at `backend/.env.example`. **Never commit `.env` into version control.**

Key variables:
- `APP_ENV`: `local` (or `testing`, `production`)
- `APP_DEBUG`: `true` (enables detailed error responses in development only)
- `APP_URL`: `http://127.0.0.1:8080`
- `DB_HOST`: `127.0.0.1`
- `DB_PORT`: `3306`
- `DB_DATABASE`: `booking_v16`
- `DB_USERNAME`: `root`
- `DB_PASSWORD`: `<your-local-password>`

---

## 4. Prohibited Technologies

Per project constraints, the following tools **must not** be introduced into the repository:
- **No Backend Frameworks:** No Laravel, Symfony, CodeIgniter, Slim, etc.
- **No Frontend Frameworks:** No React, Next.js, Vue, Angular, Svelte.
- **No Node.js / NPM build chains:** No Webpack, Vite, Tailwind CLI, Babel, or npm dependencies unless authorized.
- **No Containerization:** No Docker, Docker Compose, or Kubernetes.
- **No Heavy ORMs:** No Doctrine ORM, Eloquent. Database access is strictly native PDO.
