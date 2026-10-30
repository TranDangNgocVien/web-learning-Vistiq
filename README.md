# Vistiq

Vistiq is a single Laravel application with clearly separated learner and admin surfaces.

## Architecture

- `app/Domain`: shared models and business services.
- `app/Learner`: learner controllers, policies, requests, models, and services. Web routes live at `/`; API routes use `/api/v1`.
- `app/Admin`: admin controllers, policies, requests, jobs, and services. Web routes live under `/admin`; API routes use `/api/v1/admin`.
- `routes/web/learner.php` and `routes/web/admin.php`: surface-specific route definitions.
- `resources/views/pages`: learner and admin page views; admin views are under `pages/admin`.
- `resources/css` and `resources/js`: separate `learner` and `admin` entry points.

Both surfaces use the same database, session, authentication guard, and domain layer. The URL namespace and route middleware provide the boundary between user and admin capabilities.

## Local setup

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve --port=8000
```

The application is available at `http://localhost:8000`; the admin surface is `http://localhost:8000/admin`.
