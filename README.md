# School LMS

[![Live demo](https://img.shields.io/badge/Live_Demo-tech4projects.online-2563eb?style=for-the-badge)](https://tech4projects.online/lms)
[![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20?logo=laravel)](https://laravel.com)
[![Tests](https://img.shields.io/badge/Tests-PHPUnit-22c55e)](.github/workflows/tests.yml)

A connected school operations platform for principals, teachers, students, and parents. The product brings academics, assessment, communication, fees, attendance, and day-to-day workflows into one role-aware experience.

## Product highlights

- Four purpose-built stakeholder experiences with role and permission enforcement
- Complete CRUD workflows for classes, subjects, assignments, quizzes, results, fees, library, events, notices, and shared records
- Student submission and quiz-attempt flows with teacher grading
- Parent communication, leave, PTM, support, document, and progress experiences
- Responsive modal-based creation flows and seeded demo data
- Feature tests around authentication, authorization, CRUD, and multi-role workflows

## Stack

PHP 8.3+ · Laravel 13 · Blade · Tailwind CSS 4 · SQLite/MySQL · PHPUnit

## Run locally

```bash
git clone https://github.com/vikask2-hub/school-lms.git
cd school-lms
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/lms`. The sign-in screen provides one-click demo access for every stakeholder role.

## Quality and security

The application uses form validation, CSRF protection, policy-style middleware checks, ORM-bound queries, rate-limited authentication, and server-side session handling. Production credentials, databases, logs, and generated assets are intentionally excluded from this repository.

---

Built by [Vikask2](https://github.com/vikask2-hub) · [View the complete product portfolio](https://tech4projects.online/)
