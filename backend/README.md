# S-M-A — Advance Tutorial (PHP Backend Edition)

This is the original project with its backend rewritten from
Next.js/Prisma to plain PHP + MySQL, so it can run on any standard PHP
hosting instead of requiring a Node runtime.

## Folders

- **`frontend/`** — unchanged. Same vanilla HTML/CSS/JS as the original.
- **`backend-php/`** — the new PHP backend. **This is what you deploy.**
  See `backend-php/README.md` for setup, architecture, and what's tested.
- **`backend-nodejs-reference/`** — the original Node/Prisma backend, kept
  only as a reference for anyone comparing behavior or porting the
  remaining scaffolded modules (teachers, exams, fees, etc). Not needed
  to run the app and safe to delete.

## Quick start

```bash
cd backend-php
cp .env.example .env          # fill in DB credentials + AUTH_SECRET
mysql -u you -p yourdb < database/schema.sql
php database/seed.php         # optional demo data
php -S localhost:8000 -t public public/router.php
```

Then open `frontend/index.html` (or serve it with any static file
server) with:

```html
<script>window.__API_URL__ = "http://localhost:8000/api";</script>
```

added before the frontend's other scripts load.
