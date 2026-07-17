# Halal Kiwi Admin API

Laravel 12 backend and administration panel for the Halal Kiwi consumer app.

## Local setup

```bash
composer install
npm install
php artisan serve
npm run dev
```

Copy `.env.example` to `.env`, configure the local database, and run `php artisan migrate` before starting the application.

## Validation

```bash
php artisan test
npm run build
```

## Deployment

Production is hosted on HostGator and deployed from `main` with `./deploy.sh`. The script preserves admin-managed JSON files and stops if a migration fails.

Manufacturer outreach is review-first and disabled by default. See `AGENTS.md` before preparing or queueing outreach.
