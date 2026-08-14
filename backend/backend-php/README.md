# S-M-A Backend (PHP)

A dependency-free PHP 8.1+ rewrite of the original Next.js/Prisma backend,
built to serve the exact same frontend (`frontend/`) with zero JS changes.
Verified end-to-end against a live MariaDB instance during development —
see "What was tested" below.

## Why dependency-free

No Composer packages are required to run this — JWT signing, password
hashing, and `.env` parsing are all implemented directly against PHP's
standard library. That's a deliberate choice: it means the project runs
on cheap shared PHP hosting (cPanel, etc.) with nothing more than PHP
itself and a MySQL database, no `composer install` step, no CLI access
required on the host.

## Requirements

- PHP 8.1+ with the `pdo_mysql` and `mbstring` extensions
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite` (a `.htaccess` is included), or Nginx with an
  equivalent `try_files` rule, or `php -S` for local development

## Setup

```bash
cd php-backend
cp .env.example .env
# edit .env: set DB_* credentials and a random AUTH_SECRET
#   php -r "echo bin2hex(random_bytes(32));"

mysql -u youruser -p yourdatabase < database/schema.sql
php database/seed.php   # optional demo data, see credentials below
```

**Local development server** (no Apache/Nginx needed):

```bash
php -S localhost:8000 -t public public/router.php
```

**Production (Apache):** point the vhost's document root at `public/`.
The included `public/.htaccess` funnels all requests through
`public/index.php`.

**Production (Nginx):** point `root` at `public/` and add:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Connecting the frontend

The frontend is unmodified. Set the API origin before `api.js` loads:

```html
<script>window.__API_URL__ = "https://api.yourdomain.com/api";</script>
```

If the frontend is served from a different origin than the API, set
`FRONTEND_URL` in `.env` to that exact origin (comma-separate multiple)
so the CORS + cookie flow works — cookies require an exact origin match,
not a wildcard.

## Demo credentials (after running the seeder)

All seeded accounts share the password `Password123!`.

| Role | Email |
|---|---|
| Super Admin | superadmin@example.com |
| School Admin | admin@example.com |
| Headmaster | headmaster@example.com |
| Accountant | accountant@example.com |
| Teacher | teacher@example.com |
| Student | student@example.com |
| Parent | parent@example.com |

**Change or remove these before any production deployment.**

## Architecture

```
public/index.php          Front controller — entry point for every request
public/.htaccess          Apache rewrite rules
public/router.php         Router for PHP's built-in dev server only

bootstrap.php              Autoloader, env loading, global error handler

src/Config/
  Env.php                  .env parser
  Database.php              PDO connection + transaction() helper

src/Core/
  Request.php               Wraps $_GET/$_POST/php://input/headers/cookies
  Response.php               JSON envelope helpers (ok/fail/paginated/...)
  Router.php                  Method+path matcher with {param} placeholders
  Cors.php                     CORS headers for a separately-hosted frontend

src/Support/
  Id.php                     cuid-style unique ID generator
  Password.php               bcrypt hash/verify wrapper
  Jwt.php                    Self-contained HS256 JWT sign/verify
  Validator.php              Zod-like rule-based input validation

src/Security/
  Auth.php                   Session cookie (JWT) issue/read/destroy
  Permissions.php            Role -> permission matrix

src/Middleware/
  AuthMiddleware.php          Requires a valid session
  RoleMiddleware.php          Requires a specific permission
  TenantMiddleware.php        Resolves the trusted schoolId from session ONLY

src/Logging/
  AuditLogger.php             Writes to audit_logs, never throws

src/Repositories/             All raw SQL lives here, never in controllers
  UserRepository.php
  StudentRepository.php
  DashboardRepository.php

src/Controllers/              Thin: validate -> call repository -> respond
  AuthController.php
  DashboardController.php
  StudentController.php

routes/api.php                Route table

database/schema.sql           Full MySQL schema — all 30 tables from the
                               original Prisma schema, so future modules
                               (teachers, classes, exams, fees, etc — see
                               Roadmap below) can be built on a complete
                               data model from day one.
database/seed.php             Demo data seeder
```

## What's implemented vs. scaffolded

Matches the *real, working* scope of the original project at the time of
this rewrite (see the original README's "Development Status"):

**Fully working PHP endpoints:**
- `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`
- `GET /api/dashboard/summary`
- `GET /api/dashboard/attendance?range=N`
- `GET /api/dashboard/student-demographics`
- `GET /api/dashboard/performance?page=&limit=&classId=&grade=`
- `GET /api/students?page=&limit=&search=&classId=&gender=&status=`
- `POST /api/students`

**Database only (schema present, no endpoints yet — same as the original
project's roadmap):** teachers, classes, subjects, exams, results, fees,
invoices, payments, expenses, events, announcements, messages,
notifications, timetables. Adding a module means: add a Repository class
with the SQL, a Controller with validate → call → respond, and 1-2 lines
in `routes/api.php` — follow `StudentRepository`/`StudentController` as
the template, exactly as the original TypeScript project's own comments
instructed for its own future phases.

## Multi-tenancy & security notes

- `schoolId` for every query is read **only** from the verified session
  (`TenantMiddleware::schoolId()`), never from request input — this is
  the same non-negotiable rule the original `tenant.middleware.ts` stated.
- Sessions are HTTP-only, `SameSite=Lax` cookies containing a signed JWT.
  They are never readable by JS and never stored in `localStorage`.
- Passwords are hashed with bcrypt (cost 12) — hashes are cross-compatible
  with the original Node bcryptjs implementation.
- Every mutating action that matters (`LOGIN`, `LOGOUT`, `CREATE_STUDENT`)
  is written to `audit_logs`, matching the original `logAudit()` calls.

## What was tested

This isn't just code that type-checks — during development it was run
against a live MariaDB instance and exercised over real HTTP:

- All 30 tables created cleanly from `database/schema.sql`
- `database/seed.php` populated demo users/school/class/subjects
- Full login → cookie → `/auth/me` → dashboard endpoints → create student →
  list student → logout → post-logout 401 flow, all via `curl` against a
  running `php -S` instance
- Wrong-password and unauthenticated-request cases correctly return 401
