<?php

namespace App\Console\Commands;

use App\Models\BrandOutreachBatch;
use App\Services\BrandOutreachService;
use Illuminate\Console\Command;
use LogicException;

class BrandsReleaseApproved extends Command
{
    protected $signature = 'brands:release-approved
        {--release : Queue due, explicitly approved batches after safety revalidation}
        {--limit= : Maximum number of due batches to queue}';

    protected $description = 'Preview or release scheduled manufacturer outreach approvals';

    public function handle(BrandOutreachService $service): int
    {
        $timezone = config('outreach.timezone', 'Pacific/Auckland');
        $approved = BrandOutreachBatch::with('brand')
            ->where('status', 'approved')
            ->orderBy('not_before_at')
            ->get();

        if ($approved->isEmpty()) {
            $this->info('No scheduled manufacturer outreach approvals.');
        } else {
            $this->table(
                ['ID', 'Reference', 'Brand', 'Not before', 'State', 'Approval reference'],
                $approved->map(function (BrandOutreachBatch $batch) use ($timezone) {
                    $due = $batch->not_before_at && $batch->not_before_at->lte(now());

                    return [
                        $batch->id,
                        $batch->reference,
                        $batch->brand->name,
                        $batch->not_before_at?->timezone($timezone)->format('Y-m-d H:i T') ?? 'MISSING',
                        $due ? 'DUE' : 'future',
                        $batch->approval_reference,
                    ];
                })->all(),
            );
        }

        $reviewRequired = BrandOutreachBatch::query()->where('status', 'review_required')->count();
        $due = $approved->filter(fn (BrandOutreachBatch $batch) => $batch->not_before_at?->lte(now()))->count();
        $this->info("Scheduled summary: {$approved->count()} approved, {$due} due, {$reviewRequired} review required.");

        if (! $this->option('release')) {
            $this->info('Preview complete. No emails were queued or sent.');

            return self::SUCCESS;
        }

        try {
            $limit = $this->option('limit');
            $result = $service->releaseScheduledApprovals($limit !== null ? (int) $limit : null);
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result['review_required'] as $batchId => $reason) {
            $this->warn("Batch {$batchId} requires review: {$reason}");
        }
        $this->info(sprintf(
            'Release complete: %d due, %d queued, %d moved to review_required, %d deferred by capacity.',
            $result['due'],
            count($result['queued']),
            count($result['review_required']),
            $result['deferred'],
        ));

        return self::SUCCESS;
    }
}
