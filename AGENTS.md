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
- Future approvals must name exact IDs: `php artisan brands:outreach --approve --batch=ID --not-before='YYYY-MM-DD HH:MM' --approval-reference='...'`. This stores approval without sending. `brands:release-approved` is preview-only; the scheduler uses `--release` and revalidates due approvals before queueing.
- Changed contacts, replies, product verdicts, inactive products, or closed/changed linked requests move a due approval to `review_required`. Return it to draft and obtain fresh explicit approval; never auto-retry it.
- Keep `OUTREACH_ENABLED=false` until the `products@halalkiwi.com` SMTP path and SPF, DKIM, and DMARC are verified.
- Sending is throttled through the `outreach` database queue. `sending` and `uncertain` batches are visible but never retryable; reconcile them manually. Failed batches require review before returning them to draft. Do not add automatic SMTP retries.
- Contact-form-only brands remain manual. Never email placeholder watcher addresses ending in `@halalkiwi.com`.

## Manufacturer Reply Processing

- The authoritative procedure is **Flow: Check Manufacturer Emails** in `../../AGENTS.md`; read it before accessing the products mailbox or resolving requests.
- Use Message-ID / `brand_communications.email_message_id` for capture idempotency. Do not use the mailbox Seen flag as proof that a reply was processed. A logged multi-barcode reply remains unfinished while any `brand_communication_barcode_dispositions` row is `pending_review` or `review_required`; include `partially_processed` communications in the next review.
- Prefer the `[HK-...]` outreach reference to map replies to `brand_outreach_batches`; otherwise require exact barcodes and verified brand/thread evidence.
- Save the original email and attachments before changing a verdict. Log every substantive inbound reply in `brand_communications`.
- Present per-barcode recommendations and obtain explicit approval before changing `halal_status`, updating brand response scope, sending follow-ups, or notifying users.
- Linked-user lookup must union `prioritisation_requests.user_email` with `request_watchers.user_email`, validate/deduplicate addresses, and exclude `@halalkiwi.com` placeholders.
- Record approved inbound evidence with `brands:record-reply`; it creates one processing row per exact barcode. Resolve verdicts with `requests:resolve --communication-id=...`, which marks only that barcode applied. Record approved non-verdict outcomes with `brands:record-disposition COMMUNICATION_ID BARCODE DISPOSITION --reason='...'`, where `DISPOSITION` is exactly `kept_unreviewed`, `needs_clarification`, or `no_action`. The communication may be complete only after every scoped barcode is terminal; never set its aggregate status directly.
- Prepare approved reply-thread questions with `brands:clarification --communication-id=... --event='...' --subject='...' --body-file='...' --barcode=...`. This creates an idempotent draft only. After the draft is reviewed, queue that exact ID with `brands:outreach --kind=clarification --queue --batch=ID`. Clarifications may be sent to brands marked `partial`, require strict exact-barcode inbound evidence and saved proof, preserve Message-ID thread headers, and can never be bulk-sent with `--all`.
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
  - The API transactionally merges submissions by canonical barcode. Later photos are cumulative, recipients become watchers, and deliberate submissions promote an existing `silent` request to `new_product` or, when the exact product exists, `prioritise`.
  - The schema migration applies that correction to legacy watched `silent` rows as well: product matches become `prioritise`; unmatched rows become pending `new_product` discovery.
- Freeze one complete `Pacific/Auckland` day and retain the request/watcher cutoff throughout the run.
- Start by previewing `php artisan brands:release-approved`; report approved, due, deferred, and `review_required` batches in chat and in the final daily summary.
- Present the research and outreach plan before any DB write or email send. Never send drafts, resolve verdicts, or classify halal/not halal without the required approval.
- User information requests must use `requests:request-information`: preview first, report the exact barcode and eligible recipient count, then use `--event='...' --send` only after approval. New events use notification type `information_request`; `photo_request` is a legacy rendering alias.
- HostGator is for DB/Laravel operations only; perform Open Food Facts and web research locally or from Hetzner.
- After all ordinary identity sources fail, use the exact-barcode Mustakshif fallback defined in `../../AGENTS.md` before proposing a user information request. Treat it only as identity discovery, use the persistent lookup ledger to prevent repeated placeholder creation, ignore all external verdict fields, and store only validated local images.
- Daily audit artifacts belong under `Halal Kiwi/Products/Prioritisation_Daily/{YYYY-MM-DD}/` in the Halal Kiwi Google Drive.

## App Support Safety

- The authoritative procedure is **Flow: Check App Support Emails** in `../../AGENTS.md`; read it before reviewing or recording `appsupport@halalkiwi.com` messages.
- This subsystem is limited to the exact appsupport mailbox and the `support_*` tables. Proposed handoffs and linked IDs/barcodes are metadata only; support capture and triage must never mutate products, prioritisation requests, brands, manufacturer communications, outreach batches, restaurants, or masjids.
- `/api/contact-us` durably captures a ticket before attempting its internal mailbox notification. Submission UUID and payload fingerprint protect retries; mailbox ingestion uses Message-ID and exact thread headers, never Seen state or fuzzy sender matching. Classify a message as the audited internal notification only when authenticated sender/envelope, deterministic Message-ID/source-message ID, marker and reference match one stored app submission, plus UUID when the original has one.
- `support:record-email` is preview-only unless `--record` is supplied, requires a reviewed `--since` cutover for operational imports, and must never change mailbox files or flags. Attachments are private; downloads and notification-email forwarding stay disabled until an audited safety-review process exists. Intake must enforce configured per-requester/global daily quotas and the free-disk guard. Do not add automatic deletion; a future closed-case retention purge requires separate approval and must preserve audit records.
- Admin replies are drafts until separately approved. They may send only through the dedicated `support` SMTP configuration authenticated as `appsupport@halalkiwi.com`, and `SUPPORT_MAIL_ENABLED` defaults to false. Never reuse the manufacturer outreach transport.
- Never reply from webmail; use the `/admin/support` draft, approval, and delivery-audit path.
- Treat `sending` and `uncertain` support deliveries as potentially accepted and never auto-retry them. Reconcile them only through the audited admin action with evidence, and never reconcile an active `sending` lease. Closing a ticket requires a reason and no unresolved customer reply draft/delivery.

## Deployment Notes

- Deploy script: `./deploy.sh`
- Preserve production `.env`
- For the first deployment of the per-barcode disposition and active-request uniqueness migrations, take a verified backup, enable a brief intake maintenance window, and pause outreach workers. Do not migrate while a batch is `sending`; inspect `sending`/`uncertain` state and active duplicate groups before running migrations, then verify disposition/request backfills before resuming.
- Keep storage/public upload paths intact
- See `DEPLOYMENT.md` for rollback/troubleshooting

## Useful Commands

```bash
php artisan migrate
php artisan cache:clear
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan db:backup --keep=14
```
