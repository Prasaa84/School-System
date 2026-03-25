# SDS New System

Modernized School Data System (SDS) with:
- Backend: Laravel 13 (PHP 8.3)
- Frontend: Vue 3 + Vite + TypeScript + Pinia
- Database: MySQL

This project is split into:
- `backend/` -> Laravel REST API
- `frontend/` -> Vue web application

## Features

- Token-based API authentication
- Dashboard summary cards (latest-year based for students/grades/classes)
- Role-aware data scope:
  - `role_id = 1` (Administrator): sees all schools
  - Other roles: data filtered by user `census_id`
- Grades module
  - View data from `school_grade_tbl` (latest year)
  - Reports view
- Classes module
  - View data from `school_grade_class_tbl` (latest year)
  - Reports view
- Staff module
  - List + report summary
- Students list with search and pagination

## Requirements

- PHP `>= 8.3`
- Composer
- Node.js `>= 20`
- npm
- MySQL (local WAMP/XAMPP is fine)

## Project Setup

### 1) Clone and open

```bash
git clone <your-repo-url>
cd sds_new
```

### 2) Backend setup

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Update `backend/.env` database values:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sch_db
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations (if required for your environment):

```bash
php artisan migrate
```

Start backend:

```bash
php artisan serve
```

Backend URL usually: `http://127.0.0.1:8000`

### 3) Frontend setup

Open another terminal:

```bash
cd frontend
npm install
npm run dev
```

Frontend URL usually: `http://localhost:5173`

## Environment Notes

Frontend API base URL defaults to `/api/v1`.
If needed, set in frontend env file (`frontend/.env`):

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

## API (Current)

Base: `/api/v1`

Public:
- `GET /health`
- `POST /auth/login`

Protected (Bearer token):
- `GET /auth/me`
- `POST /auth/logout`
- `GET /modules`
- `GET /dashboard/summary`
- `GET /grades`
- `GET /grades/report`
- `GET /classes`
- `GET /classes/by-grade/{gradeId}`
- `GET /classes/report`
- `GET /staff`
- `GET /staff/report-summary`
- `GET /students`

## Running After Changes

If servers are already running:
- Usually browser refresh is enough.

If config/env changed:

```bash
cd backend
php artisan optimize:clear
```

## Build

Frontend production build:

```bash
cd frontend
npm run build
```

## Git Ignore (recommended)

```gitignore
.env
/backend/vendor
/frontend/node_modules
/backend/storage/*.key
/backend/storage/logs
/backend/storage/framework
/backend/storage/app/public
/backend/bootstrap/cache/*
!/backend/bootstrap/cache/.gitignore
```

## Notes for Future Android App

This backend is designed for reuse by mobile clients.
After web completion, Android app can be built on top of the same API (Flutter/React Native).

## License

Internal project / organization use.
