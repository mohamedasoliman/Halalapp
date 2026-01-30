# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HalalApp is a Laravel 11 backend API and admin panel for the HalalKiwi mobile app (Flutter). It manages halal products, restaurants, mosques, and community directories for the Muslim community in New Zealand.

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
- `web.php` - Public web routes (includes admin-route.php and ravi-route.php)

### API Authentication

Mobile API uses a custom `api_key` middleware (`app/Http/Middleware/apimiddleware.php`) that validates `X-API-Key` header against the app's `APP_KEY` from `.env`.

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
- `MasjidModel/Masjid` - Mosque information
- `ResturantModel/Resturant` - Restaurant/vendor data
- `Role/CustomRole` - User roles for RBAC
- `jsondata`, `jsonmeta`, `json2` - Dynamic directory data
- `User` - Admin/user accounts

### Controllers

- `ApiController` - Main mobile API with fuzzy search implementation
- `Admin/ProductController/ProductController` - Product CRUD + CSV import
- `Admin/MasjidControllers/MasjidManagementController` - Mosque management
- `Admin/ResturantControllers/ResturantManagementController` - Restaurant management
- `JsondataController` - Dynamic directory CRUD (both admin and API)
- `*ContactMessageController` - Various contact form handlers that send emails

### Global Helpers

Custom helpers are autoloaded from `app/Helpers/`:
- `CustomHelper.php` - Role-related functions (`getLoginUserRoleName()`, `getRoleNameBYId()`)
- `Helper.php` - Additional utility functions

### Key Dependencies

- `spatie/laravel-permission` - Role-based access control
- `yajra/laravel-datatables-oracle` - DataTables for admin lists
- `intervention/image-laravel` - Image processing
- `league/csv` - CSV import/export

## Server Deployment

Production server: HostGator shared hosting at `halalapp@108.167.141.140`

```bash
# SSH into server
ssh halalapp@108.167.141.140

# Server app path
/home5/halalapp/halalapp

# Server composer (older version)
php ~/.composer/2022-10-27_14-39-29-2.4.4-old.phar install
```

See `DEPLOYMENT.md` for full deployment instructions.

## Important Notes

- The `.env` file on the server contains production database credentials - never overwrite it
- Product search uses MySQL SOUNDEX for fuzzy matching (see `ApiController::allListing`)
- `halal_status = 0` means halal, `halal_status = 1` means not halal (inverted logic)
- Admin authentication uses a separate `admin` guard defined in `config/auth.php`
- User uploads are stored in `public/upload/` and are not tracked in git
