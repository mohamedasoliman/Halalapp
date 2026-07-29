# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HalalApp is a Laravel 12 backend API and admin panel for the HalalKiwi mobile app (Flutter). It manages halal products, restaurants, mosques, and community directories for the Muslim community in New Zealand.

## Local Development Setup

```bash
# First-time setup (already done if you're reading this)
cp .env.example .env
touch database/database.sqlite
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build

# Create admin user (if needed)
php artisan tinker --execute="\App\Admin::create(['name'=>'Admin','email'=>'admin@halalkiwi.com','password'=>bcrypt('password'),'role_id'=>1,'status'=>1]);"
```

## Common Commands

```bash
# Development server
php artisan serve

# Build frontend assets
npm run dev          # Development with hot reload
npm run build        # Production build

# Database
php artisan migrate                    # Run migrations
php artisan migrate:rollback           # Rollback last migration
php artisan db:seed                    # Seed database
php artisan db:backup                  # Manual database backup (daily auto at 3 AM)
php artisan db:backup --keep=14        # Backup with custom rotation count

# Cache management
php artisan config:cache               # Cache configuration
php artisan route:cache                # Cache routes
php artisan cache:clear                # Clear application cache
php artisan config:clear && php artisan route:clear && php artisan view:clear  # Clear all caches

# Testing
php artisan test                       # Run PHPUnit tests
./vendor/bin/phpunit                   # Run PHPUnit directly
./vendor/bin/phpunit --filter=TestName # Run specific test

# Code quality
./vendor/bin/pint                      # Laravel Pint code formatting

# Deployment (from local machine)
./deploy.sh                            # One-command deploy to HostGator
```

## Architecture

### Route Structure

Routes are split across multiple files in `routes/`:
- `api.php` - Mobile app API endpoints (protected by `api_key` middleware)
- `admin-route.php` - Admin panel routes (protected by `auth:admin` middleware)
- `web.php` - Public web routes (includes admin-route.php)
- `console.php` - Scheduled tasks (daily db:backup at 3:00 AM)

### API Authentication

Mobile API uses a custom `api_key` middleware (`app/Http/Middleware/apimiddleware.php`) that validates `X-API-Key` header against `API_KEY` from `.env` (falls back to `APP_KEY` if `API_KEY` not set). Contact form endpoints have additional rate limiting (5 requests/min per IP).

### Key API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/listing` | POST | Search products (fuzzy search with SOUNDEX) |
| `/api/listingcode` | POST | Search by barcode (exact match) |
| `/api/masjid` | POST | Get mosque details |
| `/api/resturant` | GET | Get restaurants |
| `/api/jsondata/{id}` | GET | Get directory data |
| `/api/contact-us` | POST | Submit contact message |
| `/api/fatwa-contact-us` | POST | Submit fatwa inquiry |
| `/api/events-contact-us` | POST | Submit event inquiry |
| `/api/kiwisaver-contact` | POST | Submit KiwiSaver inquiry |

### Models

Located in `app/Models/`:
- `ProductModel/Product` - Halal products with barcode, category, halal_status
- `MasjidModel/Masjid` - Mosque information (soft deletes enabled)
- `ResturantModel/Resturant` - Restaurant/vendor data (soft deletes enabled)
- `Role/CustomRole` - User roles for RBAC
- `jsondata`, `jsonmeta`, `json2` - Dynamic directory data
- `User` - Admin/user accounts

### Controllers

- `ApiController` - Main mobile API with fuzzy search implementation + cached product listings
- `Admin/ProductController/ProductController` - Product CRUD + CSV import (invalidates product cache on mutations)
- `Admin/AdminController` - Dashboard with stats (product counts, mosques, restaurants, admins)
- `Admin/MasjidControllers/MasjidManagementController` - Mosque management
- `Admin/ResturantControllers/ResturantManagementController` - Restaurant management
- `Admin/Users/UsersController` - User CRUD management
- `Admin/Configurations/BackgroundImageController` - Background image management
- `JsondataController` - Dynamic directory CRUD (both admin and API)
- `*ContactMessageController` - Contact form handlers with FormRequest validation

### FormRequest Validation (API)

Located in `app/Http/Requests/Api/`:
- `ProductSearchRequest` - Validates search, per_page, page, halal_only params
- `ContactRequest` - Validates contact form (subject, email, name, body, attachment)
- `FatwaContactRequest` - Validates fatwa inquiry form
- `EventsContactRequest` - Validates events inquiry form (includes URL validation on link field)

All return JSON 422 responses on validation failure (overridden `failedValidation`).

### Global Helpers

Custom helpers are autoloaded from `app/Helpers/`:
- `CustomHelper.php` - Role-related functions (`getLoginUserRoleName()`, `getRoleNameBYId()`)
- `Helper.php` - Additional utility functions

### Key Dependencies

- `spatie/laravel-permission` - Role-based access control
- `yajra/laravel-datatables-oracle` - DataTables for admin lists
- `intervention/image-laravel` - Image processing
- `league/csv` - CSV import/export

### Admin Panel Views

Blade templates in `resources/views/admin/`:
- `layouts/app.blade.php` - Main layout (includes sidebar and header)
- `layouts/loginapp.blade.php` - Auth pages layout
- `include/sidebar.blade.php` - Navigation sidebar
- `include/menuheader.blade.php` - Top header bar
- `products/index.blade.php` - Products DataTable with modals
- `masjid/masjid_index.blade.php` - Mosques management
- `JsonData/create.blade.php` - Dynamic directory builder

## Server Deployment

Production server: HostGator shared hosting at `halalapp@108.167.141.140`
Live URL: https://halalapp.info

```bash
# One-command deploy
./deploy.sh

# SSH into server
ssh halalapp@108.167.141.140

# Server app path
/home5/halalapp/halalapp

# Server composer (older version required)
php ~/.composer/2022-10-27_14-39-29-2.4.4-old.phar install
```

See `DEPLOYMENT.md` for full deployment instructions including troubleshooting and rollback procedures.

## Known Issues and Gotchas

### Route Naming
The codebase uses both `Route::resource()` and explicit routes. Some resource actions are excluded to avoid duplicate route names:
```php
Route::resource('users', UsersController::class)->except(['edit', 'destroy']);
```
Custom implementations exist at `users/edit/{id}` and `user/delete/{id}`.

**Product routes have two delete endpoints:**
- `product.delete` (POST) - expects ID in request body
- `product.destroy` (DELETE `food/{id}`) - expects ID in URL

The admin JavaScript uses `product.destroy` for delete operations via AJAX with `method: 'DELETE'` and CSRF header.

### Destructive Admin Routes Use Proper HTTP Methods
All destructive admin operations use DELETE (for deletes) or POST (for status toggles) instead of GET. The Blade views send these via AJAX with the `X-CSRF-TOKEN` header. This applies to: product delete/status, masjid delete/deleteall, restaurant delete/deleteall, jsondata delete, user delete/status, admin delete/status.

### Local vs Production Database
- Local development uses SQLite (`database/database.sqlite`)
- Production uses MySQL on HostGator (PHP 8.3.30)
- Some migrations have `Schema::hasTable()` checks to handle existing tables gracefully
- `composer.json` has `config.platform.php` set to `8.3.30` to match the server — prevents pulling packages requiring PHP 8.4+
- **Production requires `cache` and `cache_locks` tables** for rate limiting (throttle middleware). If missing, API returns 500 errors. Create with:
  ```sql
  CREATE TABLE cache (`key` VARCHAR(255) PRIMARY KEY, value MEDIUMTEXT NOT NULL, expiration INT NOT NULL);
  CREATE TABLE cache_locks (`key` VARCHAR(255) PRIMARY KEY, owner VARCHAR(255) NOT NULL, expiration INT NOT NULL);
  ```

### Laravel 12 Upgrade Notes
- Upgraded from Laravel 11 to 12 on Feb 2026
- `route('name', '')` pattern for JS URL building no longer works — use `url('path')` instead
- `$dates` property is deprecated — use `$casts` with `'datetime'` values instead
- Laravel default local disk root changed from `storage/app` to `storage/app/private` — Mailables check both paths
- Product images are stored in `public_html/public/upload/product_images/` (NOT in halalapp/public/) — do NOT delete public_html

### PSR-4 Autoloading Warnings
Two controllers don't follow PSR-4 naming (class name doesn't match file path). These warnings appear during `composer dump-autoload` but don't affect functionality:
- `CsvImportController`, `ApiController`

### Product Listing Cache
Non-search product listings are cached for 10 minutes using version-based keys (`products:v{ver}:list:{filter}:{perPage}:{page}`). The cache version is incremented on any product mutation (create, update, delete, import, status change) in `ProductController`. Search queries are not cached.

### Database Backup
Custom `db:backup` artisan command (`app/Console/Commands/BackupDatabase.php`) supports MySQL (mysqldump) and SQLite (file copy). Runs daily at 3:00 AM via Laravel scheduler. Backups stored in `storage/app/backups/` with `--keep=7` rotation by default.

### User Information Requests
- Use `php artisan requests:request-information {barcode}` to preview the active request and eligible deduplicated recipient count without writing delivery rows or sending email.
- After explicit approval, send with a unique stable event reference: `php artisan requests:request-information {barcode} --event='information-request:{date-or-batch}:{barcode}' --send`.
- This shared path is required by both manufacturer-reply and daily prioritisation workflows. It validates and deduplicates direct requester and watcher addresses, excludes `@halalkiwi.com` placeholders, records per-recipient delivery state, and uses the standard information-request template.
- New deliveries use `information_request`; `photo_request` remains a legacy template alias only.

## Important Notes

- The `.env` file on the server contains production database credentials - never overwrite it
- `API_KEY` in `.env` is used for mobile API auth (separate from `APP_KEY`); falls back to `APP_KEY` if not set
- Product search uses MySQL SOUNDEX for fuzzy matching (see `ApiController::allListing`)
- `halal_status = 0` means Halal, `1` means Not Halal, `2` means Unreviewed, and `3` means Mashbooh
- Mashbooh is not a final verdict and must not resolve prioritisation requests or trigger final-verdict notifications
- Admin authentication uses a separate `admin` guard defined in `config/auth.php`
- User uploads are stored in `public/upload/` and are not tracked in git
- The admin panel UI uses `public/assets/css/modern-admin.css` for styling (Outfit font, glass-morphic design, dark sidebar)
- Masjid and Resturant models use soft deletes (`deleted_at` column) — records are hidden, not destroyed
- `Auth::routes()` has register, verify, confirm, and reset disabled (admin-only app)
- GitHub repository: https://github.com/mohamedasoliman/Halalapp (branch: `main`)

### Product Images (Hybrid URL Support)
Product images support both local filenames and external URLs:
- Local: `product_image = "5007023.jpg"` → served from `/public/upload/product_images/`
- External: `product_image = "https://example.com/image.jpg"` → passed through as-is

The `ApiController::getProductImageUrl()` helper detects which type and constructs the appropriate URL. CSV import accepts both formats.

### CSV Import/Export
- **Import:** Upload CSV via admin panel; headers must match: `Product Name, Product Image, Barcode, Halal Status, Certification Status, Category, Notes, Ingredients`
- **Export:** Downloads all products as CSV with same format for round-trip editing
