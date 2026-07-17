<?php

namespace App\Services;

use App\Jobs\SendBrandOutreachBatch;
use App\Mail\UserNotificationEmail;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LogicException;

class BrandOutreachService
{
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
            ->whereIn('status', ['draft', 'queued'])
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
                ->whereIn('status', ['draft', 'queued'])
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

    public function queueDrafts(Collection $batches, ?int $limit = null): array
    {
        if (! config('outreach.enabled')) {
            throw new LogicException('Manufacturer outreach is disabled. Verify SMTP, SPF, DKIM and DMARC, then set OUTREACH_ENABLED=true.');
        }

        $dailyLimit = max(1, (int) config('outreach.daily_limit', 20));
        $timezone = config('outreach.timezone', 'Pacific/Auckland');
        $localDay = now($timezone);
        $alreadyScheduled = BrandOutreachBatch::query()
            ->whereIn('status', ['queued', 'sent'])
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
            if (! $this->hasVerifiedEmail($batch->brand)
                || $batch->brand->outreach_paused_at !== null
                || $batch->brand->response !== null) {
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

    public function recordSent(BrandOutreachBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = BrandOutreachBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'sent') {
                return;
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
                'body_preview' => ucfirst(str_replace('_', ' ', $batch->kind)).' inquiry for '.count($batch->products).' product(s).',
                'barcodes_mentioned' => collect($batch->products)->pluck('barcode')->values()->all(),
                'action_taken' => "Outreach batch {$batch->reference} sent.",
            ]);

            $brand = Brand::query()->lockForUpdate()->findOrFail($batch->brand_id);
            $followUpCount = $batch->kind === 'follow_up' ? $batch->follow_up_number : 0;
            $brand->update([
                'last_contacted_at' => now(),
                'follow_up_count' => $followUpCount,
                'next_follow_up_at' => $this->nextFollowUpAt($followUpCount),
            ]);

            foreach (PrioritisationRequest::whereIn('id', $batch->request_ids)->get() as $request) {
                $note = "{$batch->reference} sent ".now()->toDateString().'. WAITING.';
                $request->update([
                    'status' => 'contacted',
                    'notes' => trim(implode("\n", array_filter([$request->notes, $note]))),
                ]);
            }
        });
    }

    public function notifyWatchers(BrandOutreachBatch $batch): void
    {
        if ($batch->kind !== 'initial') {
            return;
        }

        $requests = PrioritisationRequest::with('watchers')->whereIn('id', $batch->request_ids)->get();
        foreach ($requests as $request) {
            foreach ($request->watchers as $watcher) {
                if ($this->shouldSkipWatcherEmail($watcher->user_email)) {
                    continue;
                }

                try {
                    Mail::to($watcher->user_email)->send(
                        new UserNotificationEmail('contacted', $request->product_name ?? 'your requested product', $request->barcode)
                    );
                } catch (\Throwable) {
                    // Manufacturer delivery is authoritative; a watcher notification must not undo it.
                }
            }
        }
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

    private function shouldSkipWatcherEmail(?string $email): bool
    {
        return ! $email || str_ends_with(strtolower($email), '@halalkiwi.com');
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
}
