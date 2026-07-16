# AGENTS.md

Guidance for Codex when working in `AdminPanelFinal/Halalapp/`.

## CLAUDE.md Alignment

- If this repo/folder has a local `CLAUDE.md`, read and follow it first.
- If no local `CLAUDE.md` exists, use `../../CLAUDE.md` as the fallback reference.
- If `AGENTS.md` and `CLAUDE.md` conflict, follow the more specific instruction for this folder and flag the conflict in your response.

## Project Snapshot

- App: HalalApp admin panel + API
- Stack: Laravel 12 + Blade + Vite
- Role: backend for Halal Kiwi ecosystem
- Production: HostGator (`halalapp.info`)

## Setup / Run / Validate

```bash
composer install
npm install
php artisan serve
npm run dev
php artisan test
./vendor/bin/pint
```

## Route + Auth Structure

- API routes: `routes/api.php` (mobile app)
- Admin routes: `routes/admin-route.php`
- Web routes: `routes/web.php`
- API auth middleware: `api_key` (`X-API-Key`)
- Admin auth uses `auth:admin` guard

## Critical Domain Rules

- `halal_status`: `0 = Halal`, `1 = Not Halal`, `2 = Unreviewed`
- Use strict PHP checks (`=== '0'`), never loose comparisons for status
- Watcher/user emails ending in `@halalkiwi.com` are placeholders for users who did not provide an email; do not send user notifications to them
- Present findings and get explicit approval before destructive DB changes
- HostGator cannot perform outgoing HTTP reliably; plan tasks accordingly

## High-Risk Areas

- Product import/export and cache invalidation
- Brand outreach/request resolution workflows
- Scheduled commands (daily auto-processing/backup)
- Any bulk data replacement (must be user-approved first)

## Manufacturer Outreach Safety

- Use `/admin/outreach` to prepare and review manufacturer email batches.
- `php artisan brands:outreach --prepare` creates research records and drafts only; it never sends.
- Plain `php artisan brands:outreach` is preview-only. Queue only explicitly approved IDs with `--queue --batch=ID`.
- Keep `OUTREACH_ENABLED=false` until the `products@halalkiwi.com` SMTP path and SPF, DKIM, and DMARC are verified.
- Sending is throttled through the `outreach` database queue. Failed batches require manual review before retrying; do not add automatic SMTP retries.
- Contact-form-only brands remain manual. Never email placeholder watcher addresses ending in `@halalkiwi.com`.

## Deployment Notes

- Deploy script: `./deploy.sh`
- Preserve production `.env`
- Keep storage/public upload paths intact
- See `DEPLOYMENT.md` for rollback/troubleshooting

## Useful Commands

```bash
php artisan migrate
php artisan cache:clear
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan db:backup --keep=14
```
