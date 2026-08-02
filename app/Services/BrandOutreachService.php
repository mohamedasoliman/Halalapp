<?php

namespace App\Services;

use App\Jobs\SendBrandOutreachBatch;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
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
            ->whereIn('status', ['draft', 'queued', 'sending', 'uncertain'])
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
                ->whereIn('status', ['draft', 'queued', 'sending', 'uncertain'])
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

    public function queueDrafts(Collection $batches, ?int $limit = null): array
    {
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

        foreach ($batches->where('status', 'draft') as $batch) {
            if (count($queued) >= $capacity
                || ! $scheduledAt->copy()->timezone($timezone)->isSameDay($localDay)) {
                break;
            }

            $batch->loadMissing('brand');
            if ($batch->kind === 'clarification') {
                $this->assertClarificationReadyForQueue($batch);
            }
            if (! $this->hasVerifiedEmail($batch->brand)
                || $batch->brand->outreach_paused_at !== null
                || ($batch->kind !== 'clarification' && $batch->brand->response !== null)) {
                continue;
            }

            $batch->update([
                'status' => 'queued',
                'recipient_email' => $batch->brand->email,
                'scheduled_at' => $scheduledAt,
                'failed_at' => null,
                'error' => null,
            ]);

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
