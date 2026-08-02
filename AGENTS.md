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

- `halal_status`: `0 = Halal`, `1 = Not Halal`, `2 = Unreviewed`, `3 = Mashbooh`
- Mashbooh is an investigated but unresolved concern, not a final verdict. Do not resolve requests or send final-verdict notifications for status `3`.
- `products.notes` is displayed to app users. Keep it optional and concise; never put dates, proof paths, communication IDs, or other audit metadata there.
- Follow `docs/product-assessment-guidelines.md` for product evidence and ingredient decisions, including the approved ethanol/carrier rule.
- An exact-product manufacturer statement that a product is halal suitable is sufficient unless reliable exact-product evidence establishes a conflicting prohibited ingredient or process. Vegetarian/vegan suitability alone is not equivalent to halal suitability.
- Shared equipment is acceptable when cleaning or sanitation between runs is confirmed; halal-specific or independently validated cleaning is not required.
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
- Sending is throttled through the `outreach` database queue. `sending` and `uncertain` batches are visible but never retryable; reconcile them manually. Failed batches require review before returning them to draft. Do not add automatic SMTP retries.
- Contact-form-only brands remain manual. Never email placeholder watcher addresses ending in `@halalkiwi.com`.

## Manufacturer Reply Processing

- The authoritative procedure is **Flow: Check Manufacturer Emails** in `../../AGENTS.md`; read it before accessing the products mailbox or resolving requests.
- Use Message-ID / `brand_communications.email_message_id` for idempotency. Do not use the mailbox Seen flag as proof that a reply was processed.
- Prefer the `[HK-...]` outreach reference to map replies to `brand_outreach_batches`; otherwise require exact barcodes and verified brand/thread evidence.
- Save the original email and attachments before changing a verdict. Log every substantive inbound reply in `brand_communications`.
- Present per-barcode recommendations and obtain explicit approval before changing `halal_status`, updating brand response scope, sending follow-ups, or notifying users.
- Linked-user lookup must union `prioritisation_requests.user_email` with `request_watchers.user_email`, validate/deduplicate addresses, and exclude `@halalkiwi.com` placeholders.
- Record approved inbound evidence with `brands:record-reply`, then resolve exact barcodes with `requests:resolve --communication-id=...`; this shared path preserves notes, requires proof for inbound evidence, includes direct and watcher emails, and records retry-safe delivery state.
- If missing user-supplied packaging, ingredients, or barcode photos block exact matching, preview with `requests:request-information {barcode}`. After explicit approval only, send with a unique stable `--event` and `--send`. This is the required shared template path for both manufacturer-reply and prioritisation flows; do not send ad-hoc user emails.
- Retry failed/pending recipients with `requests:resolve --retry-event='...'`; sent recipients are not resent.
- SMTP transport exceptions are recorded as `uncertain`, not retryable failures. Reconcile them manually before changing their state; an uncertain message may already have been accepted by the mail server.
- Notification deliveries left in `sending` are reported by the retry command but excluded from retry; they may represent an interrupted process after SMTP acceptance.
- Mark/move a mailbox message as processed only after proof storage, inbound logging, approved DB changes, and notification attempts are recorded.

## Daily Prioritisation Workflow

- The authoritative operating procedure is **Flow: Daily Prioritisation Processing** in `../../AGENTS.md`; read it before processing a daily batch.
- Keep product discovery separate from manufacturer outreach:
  - Plain `silent` and `new_product` records are identity-research work. High-confidence identities may only be proposed as active, unreviewed products (`halal_status = 2`).
  - `prioritise` records are manufacturer-outreach work for existing products.
  - A `silent` request with a later watcher is effectively deliberate because the current API does not promote its type. Include watcher creation timestamps in daily classification.
- Freeze one complete `Pacific/Auckland` day and retain the request/watcher cutoff throughout the run.
- Present the research and outreach plan before any DB write or email send. Never send drafts, resolve verdicts, or classify halal/not halal without the required approval.
- User information requests must use `requests:request-information`: preview first, report the exact barcode and eligible recipient count, then use `--event='...' --send` only after approval. New events use notification type `information_request`; `photo_request` is a legacy rendering alias.
- HostGator is for DB/Laravel operations only; perform Open Food Facts and web research locally or from Hetzner.
- After all ordinary identity sources fail, use the exact-barcode Mustakshif fallback defined in `../../AGENTS.md` before proposing a user information request. Treat it only as identity discovery, use the persistent lookup ledger to prevent repeated placeholder creation, ignore all external verdict fields, and store only validated local images.
- Daily audit artifacts belong under `Halal Kiwi/Products/Prioritisation_Daily/{YYYY-MM-DD}/` in the Halal Kiwi Google Drive.

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
