<?php

namespace App\Console\Commands;

use App\Models\BrandOutreachBatch;
use App\Services\BrandOutreachService;
use Illuminate\Console\Command;

class BrandsFollowUp extends Command
{
    protected $signature = 'brands:follow-up {--prepare : Create drafts for follow-ups that are due}';

    protected $description = 'Preview or prepare due manufacturer follow-up drafts without sending them';

    public function handle(BrandOutreachService $service): int
    {
        if ($this->option('prepare')) {
            $created = $service->createFollowUpDrafts();
            $this->info("Created {$created} follow-up draft(s).");
        }

        $drafts = BrandOutreachBatch::with('brand')
            ->where('kind', 'follow_up')
            ->where('status', 'draft')
            ->orderBy('created_at')
            ->get();

        if ($drafts->isEmpty()) {
            $this->info('No follow-up drafts are awaiting approval.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Reference', 'Follow-up', 'Brand', 'Recipient', 'Products'],
            $drafts->map(fn (BrandOutreachBatch $batch) => [
                $batch->id,
                $batch->reference,
                $batch->follow_up_number,
                $batch->brand->name,
                $batch->recipient_email,
                count($batch->products),
            ])->all(),
        );
        $this->info('No emails were queued or sent. Use brands:outreach --kind=follow_up --queue with approved batch IDs.');

        return self::SUCCESS;
    }
}
