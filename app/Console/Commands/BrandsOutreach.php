<?php

namespace App\Console\Commands;

use App\Models\BrandOutreachBatch;
use App\Services\BrandOutreachService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;

class BrandsOutreach extends Command
{
    protected $signature = 'brands:outreach
        {--prepare : Create contact-research records and draft initial outreach}
        {--queue : Queue approved drafts for throttled delivery}
        {--approve : Record a durable approval for future automatic release}
        {--not-before= : Earliest release date/time in the outreach timezone}
        {--approval-reference= : Audit reference for the explicit user approval}
        {--batch=* : Specific draft batch IDs to queue or approve}
        {--all : Queue every draft matching --kind}
        {--kind=initial : Filter drafts by initial, follow_up, clarification, or all}
        {--limit= : Maximum number of drafts to queue}
        {--dry-run : Deprecated alias for preview-only mode}';

    protected $description = 'Prepare, preview, and queue controlled manufacturer outreach';

    public function handle(BrandOutreachService $service): int
    {
        $kind = $this->option('kind');
        if (! in_array($kind, ['initial', 'follow_up', 'clarification', 'all'], true)) {
            $this->error('--kind must be initial, follow_up, clarification, or all.');

            return self::FAILURE;
        }

        if ($this->option('prepare')) {
            $result = $service->prepareInitialOutreach();
            $this->info(sprintf(
                'Prepared outreach: %d brand record(s) created, %d request(s) made ready, %d draft(s) created, %d brand(s) still need contact research.',
                $result['createdBrands'],
                $result['readyRequests'],
                $result['draftsCreated'],
                $result['missingContacts'],
            ));
        }

        if ($this->option('approve') && $this->option('queue')) {
            $this->error('--approve and --queue are mutually exclusive.');

            return self::FAILURE;
        }

        $requestedIds = collect($this->option('batch'))->filter()->map(fn ($id) => (int) $id)->unique();
        if ($this->option('approve')) {
            return $this->approveScheduled($service, $kind, $requestedIds);
        }
        if ($this->option('not-before') !== null || $this->option('approval-reference') !== null) {
            $this->error('--not-before and --approval-reference require --approve.');

            return self::FAILURE;
        }

        $query = BrandOutreachBatch::with('brand')
            ->where('status', 'draft')
            ->orderBy('created_at');
        if ($kind !== 'all') {
            $query->where('kind', $kind);
        }

        if ($requestedIds->isNotEmpty()) {
            $query->whereIn('id', $requestedIds);
        }

        $drafts = $query->get();
        $this->displayDrafts($drafts);

        if (! $this->option('queue')) {
            $this->newLine();
            $this->info('Preview complete. No emails were queued or sent.');

            return self::SUCCESS;
        }

        if (! $this->option('all') && $requestedIds->isEmpty()) {
            $this->error('Select approved drafts with --batch=ID, or explicitly use --all.');

            return self::FAILURE;
        }

        if ($requestedIds->isEmpty() && $drafts->contains(fn (BrandOutreachBatch $batch) => $batch->kind === 'clarification')) {
            $this->error('Clarification drafts must always be selected with explicit --batch=ID options; bulk --all sending is not allowed.');

            return self::FAILURE;
        }

        if ($drafts->isEmpty()) {
            $this->warn('No matching draft batches to queue.');

            return self::SUCCESS;
        }

        $limit = $this->option('limit');
        try {
            $queued = $service->queueDrafts($drafts, $limit !== null ? (int) $limit : null);
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(count($queued).' approved batch(es) queued. Delivery remains subject to the daily limit and spacing settings.');

        return self::SUCCESS;
    }

    private function approveScheduled(BrandOutreachService $service, string $kind, $requestedIds): int
    {
        if ($this->option('all') || $requestedIds->isEmpty()) {
            $this->error('Scheduled approval requires explicit --batch=ID options; --all is not allowed.');

            return self::FAILURE;
        }
        if (! $this->option('not-before') || ! $this->option('approval-reference')) {
            $this->error('--not-before and --approval-reference are required with --approve.');

            return self::FAILURE;
        }

        try {
            $notBefore = Carbon::parse(
                (string) $this->option('not-before'),
                config('outreach.timezone', 'Pacific/Auckland'),
            );
        } catch (\Throwable) {
            $this->error('--not-before must be a valid date/time.');

            return self::FAILURE;
        }

        $query = BrandOutreachBatch::with('brand')
            ->whereIn('status', ['draft', 'approved'])
            ->whereIn('id', $requestedIds)
            ->orderBy('created_at');
        if ($kind !== 'all') {
            $query->where('kind', $kind);
        }
        $batches = $query->get();
        if ($batches->count() !== $requestedIds->count()) {
            $this->error('One or more selected batches do not exist, do not match --kind, or are no longer draft/approved.');

            return self::FAILURE;
        }

        $this->displayDrafts($batches);
        try {
            $approved = $service->approveScheduledBatches(
                $batches,
                $notBefore,
                (string) $this->option('approval-reference'),
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d batch(es) durably approved. Earliest release: %s. No email was queued or sent now.',
            count($approved),
            $notBefore->timezone(config('outreach.timezone', 'Pacific/Auckland'))->format('Y-m-d H:i T'),
        ));

        return self::SUCCESS;
    }

    private function displayDrafts($drafts): void
    {
        if ($drafts->isEmpty()) {
            $this->info('No matching draft outreach batches.');

            return;
        }

        $this->table(
            ['ID', 'Reference', 'Kind', 'Brand', 'Recipient', 'Products'],
            $drafts->map(fn (BrandOutreachBatch $batch) => [
                $batch->id,
                $batch->reference,
                $batch->kind,
                $batch->brand->name,
                $batch->recipient_email,
                count($batch->products),
            ])->all(),
        );
    }
}
