<?php

namespace App\Services;

use App\Jobs\SendBrandOutreachBatch;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Support\ProductBarcode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class BrandOutreachService
{
    public function __construct(private readonly RequestNotificationService $notifications) {}

    public function prepareInitialOutreach(): array
    {
        $createdBrands = 0;
        $readyRequests = 0;
        $missingContacts = 0;

        $requestsByBrand = PrioritisationRequest::query()
            ->active()
            ->where('status', 'pending')
            ->whereNotNull('brand_name')
            ->where('brand_name', '!=', '')
            ->orderBy('brand_name')
            ->get(['id', 'brand_name'])
            ->filter(fn (PrioritisationRequest $request) => $this->isUsableBrandName($request->brand_name))
            ->groupBy(fn (PrioritisationRequest $request) => $this->normalizeBrandName($request->brand_name));

        // Group case, spacing, and smart-punctuation variants before creating outreach.
        $brandsByName = Brand::query()
            ->orderByDesc('id')
            ->get()
            ->keyBy(fn (Brand $brand) => $this->normalizeBrandName($brand->name));

        foreach ($requestsByBrand as $normalizedName => $requests) {
            $brandName = trim((string) $requests->first()->brand_name);
            $brand = $brandsByName->get($normalizedName);
            if (! $brand) {
                $brand = Brand::create([
                    'name' => $brandName,
                    'contact_type' => 'email',
                    'contact_research_status' => 'pending',
                    'notes' => 'Created from pending prioritisation requests. Contact research required.',
                ]);
                $brandsByName->put($normalizedName, $brand);
                $createdBrands++;
            }

            if (! $this->hasVerifiedEmail($brand)) {
                $missingContacts++;

                continue;
            }

            if ($brand->response !== null || $brand->last_contacted_at !== null || $brand->outreach_paused_at !== null) {
                continue;
            }

            $readyRequests += PrioritisationRequest::query()
                ->whereIn('id', $requests->pluck('id'))
                ->update(['status' => 'ready_for_outreach']);
        }

        $draftsCreated = $this->createInitialDrafts();

        return compact('createdBrands', 'readyRequests', 'missingContacts', 'draftsCreated');
    }

    public function createInitialDrafts(): int
    {
        $requestsByBrand = PrioritisationRequest::query()
            ->active()
            ->where('status', 'ready_for_outreach')
            ->whereNotNull('brand_name')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (PrioritisationRequest $request) => $this->normalizeBrandName($request->brand_name));

        $brandsByName = Brand::query()
            ->orderByDesc('id')
            ->get()
            ->keyBy(fn (Brand $brand) => $this->normalizeBrandName($brand->name));

        $openRequestIds = BrandOutreachBatch::query()
            ->whereIn('status', ['draft', 'approved', 'review_required', 'queued', 'sending', 'uncertain'])
            ->get(['request_ids'])
            ->flatMap(fn (BrandOutreachBatch $batch) => $batch->request_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique();

        $created = 0;
        foreach ($requestsByBrand as $normalizedName => $requests) {
            $brand = $brandsByName->get($normalizedName);
            if (! $brand
                || ! $this->hasVerifiedEmail($brand)
                || $brand->response !== null
                || $brand->last_contacted_at !== null
                || $brand->outreach_paused_at !== null) {
                continue;
            }

            $available = $requests->reject(fn (PrioritisationRequest $request) => $openRequestIds->contains($request->id));
            foreach ($this->productGroups($available)->chunk(max(1, config('outreach.products_per_email', 10))) as $productChunk) {
                $this->createDraft($brand, $productChunk, 'initial', 0);
                $created++;
            }
        }

        return $created;
    }

    public function createFollowUpDrafts(): int
    {
        $maxFollowUps = count(config('outreach.follow_up_days', [7, 14]));
        $brands = Brand::query()
            ->whereNull('response')
            ->whereNull('outreach_paused_at')
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->where('follow_up_count', '<', $maxFollowUps)
            ->get();

        $created = 0;
        foreach ($brands as $brand) {
            if (! $this->hasVerifiedEmail($brand)) {
                continue;
            }

            $hasOpenFollowUp = $brand->outreachBatches()
                ->where('kind', 'follow_up')
                ->whereIn('status', ['draft', 'approved', 'review_required', 'queued', 'sending', 'uncertain'])
                ->exists();
            if ($hasOpenFollowUp) {
                continue;
            }

            $requests = PrioritisationRequest::query()
                ->active()
                ->where('status', 'contacted')
                ->where('brand_name', $brand->name)
                ->orderBy('created_at')
                ->get();

            foreach ($this->productGroups($requests)->chunk(max(1, config('outreach.products_per_email', 10))) as $productChunk) {
                $this->createDraft($brand, $productChunk, 'follow_up', $brand->follow_up_count + 1);
                $created++;
            }
        }

        return $created;
    }

    public function createClarificationDraft(
        Brand $brand,
        BrandCommunication $sourceCommunication,
        string $eventReference,
        string $subject,
        string $body,
        array $barcodes,
        array $references = [],
    ): BrandOutreachBatch {
        if (! $this->hasVerifiedEmail($brand)) {
            throw new InvalidArgumentException('The selected brand does not have a verified email contact.');
        }
        if ($sourceCommunication->direction !== 'inbound') {
            throw new InvalidArgumentException('The source communication must be an inbound manufacturer message.');
        }

        $eventReference = trim($eventReference);
        $subject = trim($subject);
        $body = trim($body);
        if ($eventReference === '' || mb_strlen($eventReference) > 500) {
            throw new InvalidArgumentException('A stable clarification event reference of at most 500 characters is required.');
        }
        if ($subject === '' || mb_strlen($subject) > 500) {
            throw new InvalidArgumentException('A clarification subject of at most 500 characters is required.');
        }
        if ($body === '' || mb_strlen($body) > 50000) {
            throw new InvalidArgumentException('A clarification body of at most 50,000 characters is required.');
        }

        $barcodes = collect($barcodes)
            ->map(fn ($barcode) => trim((string) $barcode))
            ->filter()
            ->uniqueStrict()
            ->values();
        if ($barcodes->isEmpty() || $barcodes->contains(fn (string $barcode) => preg_match('/^\d{8,14}$/D', $barcode) !== 1)) {
            throw new InvalidArgumentException('At least one exact 8-14 digit barcode is required.');
        }

        $evidenceBarcodes = collect($sourceCommunication->barcodes_mentioned ?? [])
            ->map(fn ($barcode) => trim((string) $barcode));
        $uncovered = $barcodes->reject(fn (string $barcode) => $evidenceBarcodes->containsStrict($barcode));
        if ($uncovered->isNotEmpty()) {
            throw new InvalidArgumentException('The source communication does not cover exact barcode(s): '.$uncovered->implode(', '));
        }

        $productsByBarcode = Product::query()
            ->whereIn('Barcode', $barcodes->all())
            ->get(['product_name', 'Barcode'])
            ->keyBy(fn (Product $product) => (string) $product->Barcode);
        $missingProducts = $barcodes->reject(fn (string $barcode) => $productsByBarcode->has($barcode));
        if ($missingProducts->isNotEmpty()) {
            throw new InvalidArgumentException('No exact product row exists for barcode(s): '.$missingProducts->implode(', '));
        }

        $inReplyTo = $this->normalizeMessageId($sourceCommunication->email_message_id);
        if ($inReplyTo === null) {
            throw new InvalidArgumentException('The inbound source communication has no valid Message-ID for threading.');
        }

        $references = collect($references)
            ->prepend($inReplyTo)
            ->map(fn ($messageId) => $this->normalizeMessageId($messageId))
            ->filter()
            ->uniqueStrict()
            ->values()
            ->all();
        $requestIds = PrioritisationRequest::query()
            ->active()
            ->whereIn('barcode', $barcodes->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $products = $barcodes->map(fn (string $barcode) => [
            'name' => (string) $productsByBarcode->get($barcode)->product_name,
            'barcode' => $barcode,
        ])->all();
        $eventKey = hash('sha256', $eventReference);
        $values = [
            'brand_id' => $brand->id,
            'kind' => 'clarification',
            'follow_up_number' => 0,
            'status' => 'draft',
            'recipient_email' => strtolower(trim((string) $brand->email)),
            'subject' => $subject,
            'message_body' => $body,
            'products' => $products,
            'request_ids' => $requestIds,
            'source_communication_id' => $sourceCommunication->id,
            'event_reference' => $eventReference,
            'in_reply_to_message_id' => $inReplyTo,
            'reference_message_ids' => $references,
        ];

        try {
            return DB::transaction(function () use ($eventKey, $values) {
                $existing = BrandOutreachBatch::query()->where('event_key', $eventKey)->lockForUpdate()->first();
                if ($existing) {
                    $this->assertSameClarification($existing, $values);

                    return $existing;
                }

                return BrandOutreachBatch::create([
                    'reference' => $this->nextReference(Brand::findOrFail($values['brand_id'])),
                    'event_key' => $eventKey,
                    ...$values,
                ]);
            });
        } catch (QueryException $exception) {
            $existing = BrandOutreachBatch::query()->where('event_key', $eventKey)->first();
            if (! $existing) {
                throw $exception;
            }
            $this->assertSameClarification($existing, $values);

            return $existing;
        }
    }

    public function approveScheduledBatches(Collection $batches, Carbon $notBefore, string $approvalReference): array
    {
        $approvalReference = trim($approvalReference);
        if ($approvalReference === '' || mb_strlen($approvalReference) > 500) {
            throw new InvalidArgumentException('An approval reference of at most 500 characters is required.');
        }
        if (! $notBefore->isFuture()) {
            throw new InvalidArgumentException('The scheduled release time must be in the future.');
        }

        return DB::transaction(function () use ($batches, $notBefore, $approvalReference) {
            $batchIds = $batches->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
            $locked = BrandOutreachBatch::with('brand')
                ->whereIn('id', $batchIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($locked->count() !== $batchIds->count()) {
                throw new LogicException('One or more selected outreach batches no longer exist.');
            }

            foreach ($batchIds as $batchId) {
                $batch = $locked->get($batchId);
                if ($batch->status === 'approved') {
                    $sameApproval = $batch->not_before_at?->equalTo($notBefore)
                        && hash_equals((string) $batch->approval_reference, $approvalReference);
                    if (! $sameApproval) {
                        throw new LogicException("Batch {$batch->reference} already has a different scheduled approval. Cancel or return it to draft before changing it.");
                    }

                    continue;
                }
                if ($batch->status !== 'draft') {
                    throw new LogicException("Batch {$batch->reference} is {$batch->status}; only reviewed drafts can receive a scheduled approval.");
                }
                if ($reason = $this->scheduledApprovalReviewReason($batch, false)) {
                    throw new LogicException("Batch {$batch->reference} cannot be approved: {$reason}");
                }
            }

            foreach ($batchIds as $batchId) {
                $batch = $locked->get($batchId);
                if ($batch->status === 'approved') {
                    continue;
                }
                $batch->update([
                    'status' => 'approved',
                    'recipient_email' => strtolower(trim((string) $batch->brand->email)),
                    'approved_at' => now(),
                    'not_before_at' => $notBefore,
                    'approval_reference' => $approvalReference,
                    'review_required_at' => null,
                    'scheduled_at' => null,
                    'failed_at' => null,
                    'error' => null,
                ]);
            }

            return $batchIds->all();
        });
    }

    public function releaseScheduledApprovals(?int $limit = null): array
    {
        if (! config('outreach.enabled')) {
            throw new LogicException('Manufacturer outreach is disabled. Due approvals remain held until outreach is enabled.');
        }

        $this->assertDeliveryConfiguration();

        $due = BrandOutreachBatch::with('brand')
            ->where('status', 'approved')
            ->whereNotNull('not_before_at')
            ->where('not_before_at', '<=', now())
            ->orderBy('not_before_at')
            ->orderBy('id')
            ->get();
        $eligible = collect();
        $reviewRequired = [];

        foreach ($due as $batch) {
            if ($reason = $this->scheduledApprovalReviewReason($batch, true)) {
                if ($this->markScheduledApprovalForReview($batch, $reason)) {
                    $reviewRequired[$batch->id] = $reason;
                }

                continue;
            }

            $eligible->push($batch);
        }

        $queued = $this->queueDrafts($eligible, $limit, 'approved');

        foreach ($eligible->whereNotIn('id', $queued) as $batch) {
            $batch->refresh();
            if ($batch->status === 'review_required') {
                $reviewRequired[$batch->id] = (string) $batch->error;
            }
        }

        return [
            'due' => $due->count(),
            'queued' => $queued,
            'review_required' => $reviewRequired,
            'deferred' => BrandOutreachBatch::query()
                ->whereIn('id', $due->pluck('id'))
                ->where('status', 'approved')
                ->count(),
        ];
    }

    public function queueDrafts(Collection $batches, ?int $limit = null, string $requiredStatus = 'draft'): array
    {
        if (! in_array($requiredStatus, ['draft', 'approved'], true)) {
            throw new InvalidArgumentException('Only draft or approved outreach batches can be queued.');
        }
        if (! config('outreach.enabled')) {
            throw new LogicException('Manufacturer outreach is disabled. Verify SMTP, SPF, DKIM and DMARC, then set OUTREACH_ENABLED=true.');
        }

        $this->assertDeliveryConfiguration();

        $dailyLimit = max(1, (int) config('outreach.daily_limit', 20));
        $timezone = config('outreach.timezone', 'Pacific/Auckland');
        $localDay = now($timezone);
        $alreadyScheduled = BrandOutreachBatch::query()
            ->whereIn('status', ['queued', 'sending', 'uncertain', 'sent'])
            ->whereBetween('scheduled_at', [
                $localDay->copy()->startOfDay()->utc(),
                $localDay->copy()->endOfDay()->utc(),
            ])
            ->count();
        $capacity = max(0, $dailyLimit - $alreadyScheduled);
        if ($limit !== null) {
            $capacity = min($capacity, max(0, $limit));
        }

        $spacing = max(1, (int) config('outreach.spacing_minutes', 3));
        $scheduledAt = now();
        $queued = [];

        foreach ($batches->where('status', $requiredStatus) as $batch) {
            if (count($queued) >= $capacity
                || ! $scheduledAt->copy()->timezone($timezone)->isSameDay($localDay)) {
                break;
            }

            $batch = BrandOutreachBatch::with('brand')->find($batch->id);
            if (! $batch || $batch->status !== $requiredStatus) {
                continue;
            }
            if ($requiredStatus === 'approved') {
                if ($reason = $this->scheduledApprovalReviewReason($batch, true)) {
                    $this->markScheduledApprovalForReview($batch, $reason);

                    continue;
                }
            }
            if ($batch->kind === 'clarification') {
                $this->assertClarificationReadyForQueue($batch);
            }
            if (! $this->hasVerifiedEmail($batch->brand)
                || $batch->brand->outreach_paused_at !== null
                || ($batch->kind !== 'clarification' && $batch->brand->response !== null)) {
                continue;
            }

            $updates = [
                'status' => 'queued',
                'scheduled_at' => $scheduledAt,
                'failed_at' => null,
                'error' => null,
            ];
            if ($requiredStatus === 'draft') {
                $updates['recipient_email'] = strtolower(trim((string) $batch->brand->email));
            }
            $claimed = BrandOutreachBatch::query()
                ->whereKey($batch->id)
                ->where('status', $requiredStatus)
                ->update($updates);
            if ($claimed !== 1) {
                continue;
            }

            SendBrandOutreachBatch::dispatch($batch->id)
                ->onConnection(config('outreach.queue_connection', 'database'))
                ->onQueue(config('outreach.queue', 'outreach'))
                ->delay($scheduledAt);

            $queued[] = $batch->id;
            $scheduledAt = $scheduledAt->copy()->addMinutes($spacing);
        }

        return $queued;
    }

    public function claimForSending(BrandOutreachBatch $batch): bool
    {
        $claimed = BrandOutreachBatch::query()
            ->whereKey($batch->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'sending',
                'error' => null,
            ]);

        if ($claimed === 1) {
            $batch->refresh();
        }

        return $claimed === 1;
    }

    public function recordSent(BrandOutreachBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = BrandOutreachBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'sent') {
                return;
            }
            if ($batch->status !== 'sending') {
                throw new LogicException("Outreach batch {$batch->reference} is not claimed for sending.");
            }

            $batch->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'error' => null,
            ]);

            BrandCommunication::create([
                'brand_id' => $batch->brand_id,
                'direction' => 'outbound',
                'subject' => $batch->subject,
                'body_preview' => $batch->kind === 'clarification'
                    ? mb_substr((string) $batch->message_body, 0, 2000)
                    : ucfirst(str_replace('_', ' ', $batch->kind)).' inquiry for '.count($batch->products).' product(s).',
                'barcodes_mentioned' => collect($batch->products)->pluck('barcode')->values()->all(),
                'action_taken' => $batch->kind === 'clarification'
                    ? "Clarification batch {$batch->reference} sent in reply to inbound communication #{$batch->source_communication_id}."
                    : "Outreach batch {$batch->reference} sent.",
            ]);

            if ($batch->kind === 'clarification' && $batch->source_communication_id) {
                $source = BrandCommunication::query()->lockForUpdate()->findOrFail($batch->source_communication_id);
                $sourceNote = "Clarification batch {$batch->reference} sent; awaiting manufacturer response.";
                $source->update([
                    'processing_status' => 'awaiting_response',
                    'action_taken' => trim(implode("\n", array_filter([$source->action_taken, $sourceNote]))),
                    'processed_at' => now(),
                ]);
            }

            $brand = Brand::query()->lockForUpdate()->findOrFail($batch->brand_id);
            $brandUpdates = ['last_contacted_at' => now()];
            if ($batch->kind !== 'clarification') {
                $followUpCount = $batch->kind === 'follow_up' ? $batch->follow_up_number : 0;
                $brandUpdates['follow_up_count'] = $followUpCount;
                $brandUpdates['next_follow_up_at'] = $this->nextFollowUpAt($followUpCount);
            }
            $brand->update($brandUpdates);

            foreach (PrioritisationRequest::whereIn('id', $batch->request_ids)->get() as $request) {
                $note = "{$batch->reference} sent ".now()->toDateString().'. WAITING.';
                $request->update([
                    'status' => 'contacted',
                    'notes' => trim(implode("\n", array_filter([$request->notes, $note]))),
                ]);
            }
        });
    }

    public function notifyWatchers(BrandOutreachBatch $batch): array
    {
        if ($batch->kind !== 'initial') {
            return ['sent' => 0, 'failed' => 0, 'uncertain' => 0, 'sending' => 0, 'skipped' => 0];
        }

        $requests = PrioritisationRequest::with('watchers')->whereIn('id', $batch->request_ids)->get();
        if ($requests->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'uncertain' => 0, 'sending' => 0, 'skipped' => 0];
        }

        $result = ['sent' => 0, 'failed' => 0, 'uncertain' => 0, 'sending' => 0, 'skipped' => 0];
        foreach ($requests->groupBy(fn (PrioritisationRequest $request) => (string) $request->barcode) as $barcode => $barcodeRequests) {
            $first = $barcodeRequests->first();
            $eventReference = "outreach-contacted:{$batch->reference}:barcode:{$barcode}";
            $this->notifications->prepareEvent(
                $eventReference,
                $barcodeRequests,
                'contacted',
                $first->product_name ?? 'your requested product',
                (string) $barcode,
            );
            $delivery = $this->notifications->deliverEvent($eventReference);
            foreach ($result as $key => $count) {
                $result[$key] += $delivery[$key];
            }
        }

        return $result;
    }

    public function hasVerifiedEmail(Brand $brand): bool
    {
        return $brand->contact_type === 'email'
            && $brand->contact_research_status === 'verified'
            && filter_var($brand->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function createDraft(Brand $brand, Collection $productChunk, string $kind, int $followUpNumber): BrandOutreachBatch
    {
        $reference = $this->nextReference($brand);
        $displayBrand = trim(explode('(', $brand->name)[0]);
        $prefix = $kind === 'follow_up' ? 'Follow-up: ' : '';

        return BrandOutreachBatch::create([
            'reference' => $reference,
            'brand_id' => $brand->id,
            'kind' => $kind,
            'follow_up_number' => $followUpNumber,
            'status' => 'draft',
            'recipient_email' => $brand->email,
            'subject' => "{$prefix}Halal Suitability Inquiry [{$reference}] - {$displayBrand}",
            'products' => $productChunk->map(fn (array $group) => [
                'name' => $group['name'],
                'barcode' => $group['barcode'],
            ])->values()->all(),
            'request_ids' => $productChunk->flatMap(fn (array $group) => $group['request_ids'])->unique()->values()->all(),
        ]);
    }

    private function productGroups(Collection $requests): Collection
    {
        return $requests
            ->groupBy(function (PrioritisationRequest $request) {
                $barcode = trim((string) $request->barcode);

                return preg_match('/^\d{8,14}$/', $barcode) === 1
                    ? "barcode:{$barcode}"
                    : "request:{$request->id}";
            })
            ->map(function (Collection $barcodeRequests) {
                $barcode = trim((string) $barcodeRequests->first()->barcode);

                return [
                    'barcode' => $barcode !== '' ? $barcode : 'Not supplied',
                    'name' => $barcodeRequests->first()->product_name ?? 'Unknown Product',
                    'request_ids' => $barcodeRequests->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ];
            })
            ->values();
    }

    private function nextReference(Brand $brand): string
    {
        do {
            $reference = sprintf('HK-%s-%d-%s', now()->format('Ymd'), $brand->id, Str::upper(Str::random(4)));
        } while (BrandOutreachBatch::where('reference', $reference)->exists());

        return $reference;
    }

    private function nextFollowUpAt(int $completedFollowUps): ?Carbon
    {
        $days = array_values(config('outreach.follow_up_days', [7, 14]));
        if (! array_key_exists($completedFollowUps, $days)) {
            return null;
        }

        $previousDay = $completedFollowUps === 0 ? 0 : $days[$completedFollowUps - 1];

        return now()->addDays(max(1, $days[$completedFollowUps] - $previousDay));
    }

    private function assertDeliveryConfiguration(): void
    {
        $mailer = config('outreach.mailer');
        $mailerConfig = is_string($mailer) ? config("mail.mailers.{$mailer}") : null;
        if (! is_array($mailerConfig)) {
            throw new LogicException('The dedicated manufacturer outreach mailer is not configured.');
        }

        if (($mailerConfig['transport'] ?? null) !== 'smtp') {
            return;
        }

        $username = strtolower(trim((string) ($mailerConfig['username'] ?? '')));
        $fromAddress = strtolower(trim((string) config('outreach.from_address')));
        if ($username === '' || $username !== $fromAddress || empty($mailerConfig['password'])) {
            throw new LogicException('Outreach SMTP must authenticate as the configured manufacturer From address.');
        }
    }

    private function isUsableBrandName(?string $brandName): bool
    {
        $brandName = $this->normalizeBrandName($brandName);

        return $brandName !== '' && ! in_array($brandName, ['?', 'unknown', 'n/a', 'na', 'none'], true);
    }

    private function normalizeBrandName(?string $brandName): string
    {
        $brandName = strtr((string) $brandName, [
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{2013}" => '-',
            "\u{2014}" => '-',
        ]);

        return Str::lower(Str::squish(Str::ascii($brandName)));
    }

    private function normalizeMessageId(mixed $messageId): ?string
    {
        $messageId = strtolower(trim((string) $messageId));
        if ($messageId === '') {
            return null;
        }

        $messageId = '<'.trim($messageId, "<> \t\n\r\0\x0B").'>';

        return preg_match('/^<[^<>\s]+@[^<>\s]+>$/D', $messageId) === 1 ? $messageId : null;
    }

    private function assertSameClarification(BrandOutreachBatch $batch, array $values): void
    {
        $same = (int) $batch->brand_id === (int) $values['brand_id']
            && $batch->kind === 'clarification'
            && strtolower(trim((string) $batch->recipient_email)) === $values['recipient_email']
            && (string) $batch->subject === $values['subject']
            && (string) $batch->message_body === $values['message_body']
            && (int) $batch->source_communication_id === (int) $values['source_communication_id']
            && (string) $batch->in_reply_to_message_id === $values['in_reply_to_message_id']
            && ($batch->products ?? []) === $values['products']
            && ($batch->request_ids ?? []) === $values['request_ids']
            && ($batch->reference_message_ids ?? []) === $values['reference_message_ids'];

        if (! $same) {
            throw new LogicException('This clarification event reference already exists with different content. Use the existing batch or choose a new stable event reference.');
        }
    }

    private function scheduledApprovalReviewReason(BrandOutreachBatch $batch, bool $isRelease): ?string
    {
        $batch->loadMissing('brand');
        $brand = $batch->brand;
        if (! $brand || ! $this->hasVerifiedEmail($brand)) {
            return 'the brand no longer has a verified email contact';
        }
        if ($brand->outreach_paused_at !== null) {
            return 'manufacturer outreach is paused for this brand';
        }
        if (strtolower(trim((string) $batch->recipient_email)) !== strtolower(trim((string) $brand->email))) {
            return 'the verified recipient changed after the draft was reviewed';
        }
        if ($batch->kind !== 'clarification' && $brand->response !== null) {
            return 'the brand has supplied a response since this outreach was prepared';
        }

        if ($batch->kind === 'clarification') {
            try {
                $this->assertClarificationReadyForQueue($batch);
            } catch (LogicException $exception) {
                return $exception->getMessage();
            }
        }

        if ($isRelease) {
            if (! $batch->approved_at || ! $batch->not_before_at || trim((string) $batch->approval_reference) === '') {
                return 'the durable approval record is incomplete';
            }
            if ($batch->not_before_at->isFuture()) {
                return 'the approved release time has not arrived';
            }
            if ($brand->last_contacted_at?->greaterThanOrEqualTo($batch->approved_at)) {
                return 'the brand was contacted again after this batch was approved';
            }
            if (BrandCommunication::query()
                ->where('brand_id', $batch->brand_id)
                ->where('direction', 'inbound')
                ->when($batch->source_communication_id, fn ($query) => $query->where('id', '!=', $batch->source_communication_id))
                ->where('created_at', '>=', $batch->approved_at)
                ->exists()) {
                return 'a manufacturer reply arrived after this batch was approved';
            }
        }

        $barcodes = collect($batch->products ?? [])
            ->pluck('barcode')
            ->map(fn ($barcode) => trim((string) $barcode))
            ->uniqueStrict()
            ->values();
        if ($barcodes->isEmpty() || $barcodes->contains(fn (string $barcode) => preg_match('/^\d{8,14}$/D', $barcode) !== 1)) {
            return 'the batch does not contain only exact 8-14 digit product barcodes';
        }

        $eligibleProducts = Product::query()
            ->whereIn('Barcode', $barcodes->all())
            ->where('status', 1)
            ->where('halal_status', 2)
            ->pluck('Barcode')
            ->map(fn ($barcode) => (string) $barcode)
            ->uniqueStrict();
        $ineligibleBarcodes = $barcodes->reject(fn (string $barcode) => $eligibleProducts->containsStrict($barcode));
        if ($ineligibleBarcodes->isNotEmpty()) {
            return 'product identity/status changed or is no longer active and unreviewed for barcode(s): '.$ineligibleBarcodes->implode(', ');
        }

        $requestIds = collect($batch->request_ids ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($batch->kind !== 'clarification' && $requestIds->isEmpty()) {
            return 'the batch has no linked prioritisation requests';
        }
        if ($requestIds->isNotEmpty()) {
            $requests = PrioritisationRequest::query()->whereIn('id', $requestIds)->get(['id', 'barcode', 'brand_name', 'status']);
            $activeRequestIds = $requests
                ->whereNotIn('status', ['resolved', 'dead_end'])
                ->filter(function (PrioritisationRequest $request) use ($barcodes, $brand) {
                    $requestKey = ProductBarcode::key((string) $request->barcode);

                    return $requestKey !== null
                        && $this->normalizeBrandName($request->brand_name) === $this->normalizeBrandName($brand->name)
                        && $barcodes->contains(
                            fn (string $barcode) => ProductBarcode::key($barcode) === $requestKey,
                        );
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique();
            $ineligibleRequestIds = $requestIds->diff($activeRequestIds);
            if ($ineligibleRequestIds->isNotEmpty()) {
                return 'linked prioritisation request(s) were resolved, closed, removed, or changed barcode: '.$ineligibleRequestIds->implode(', ');
            }
        }

        return null;
    }

    private function markScheduledApprovalForReview(BrandOutreachBatch $batch, string $reason): bool
    {
        return BrandOutreachBatch::query()
            ->whereKey($batch->id)
            ->where('status', 'approved')
            ->update([
                'status' => 'review_required',
                'review_required_at' => now(),
                'error' => $reason,
            ]) === 1;
    }

    private function assertClarificationReadyForQueue(BrandOutreachBatch $batch): void
    {
        $source = BrandCommunication::query()->find($batch->source_communication_id);
        $inReplyTo = $this->normalizeMessageId($batch->in_reply_to_message_id);
        $sourceMessageId = $this->normalizeMessageId($source?->email_message_id);
        $barcodes = collect($batch->products ?? [])->pluck('barcode')->map(fn ($barcode) => trim((string) $barcode));
        $sourceBarcodes = collect($source?->barcodes_mentioned ?? [])->map(fn ($barcode) => trim((string) $barcode));
        $references = collect($batch->reference_message_ids ?? []);

        $valid = $source?->direction === 'inbound'
            && trim((string) $source->proof_path) !== ''
            && trim((string) $batch->event_reference) !== ''
            && trim((string) $batch->event_key) !== ''
            && trim((string) $batch->message_body) !== ''
            && $barcodes->isNotEmpty()
            && $barcodes->every(fn (string $barcode) => preg_match('/^\d{8,14}$/D', $barcode) === 1 && $sourceBarcodes->containsStrict($barcode))
            && $inReplyTo !== null
            && $inReplyTo === $sourceMessageId
            && $references->containsStrict($inReplyTo);

        if (! $valid) {
            throw new LogicException("Clarification batch {$batch->reference} is missing exact inbound evidence, proof, idempotency, body, or thread metadata and cannot be queued.");
        }
    }
}
