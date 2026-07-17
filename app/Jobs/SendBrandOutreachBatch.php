<?php

namespace App\Jobs;

use App\Mail\BrandOutreachEmail;
use App\Models\BrandOutreachBatch;
use App\Services\BrandOutreachService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

class SendBrandOutreachBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Avoid automatic retries after an ambiguous SMTP result; failed batches require review.
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $batchId) {}

    public function handle(BrandOutreachService $service): void
    {
        if (! config('outreach.enabled')) {
            throw new LogicException('Manufacturer outreach was disabled before this batch was delivered.');
        }

        $batch = BrandOutreachBatch::with('brand')->findOrFail($this->batchId);
        if ($batch->status !== 'queued') {
            return;
        }

        Mail::mailer(config('outreach.mailer'))->to($batch->recipient_email)->send(new BrandOutreachEmail(
            brandName: $batch->brand->name,
            products: $batch->products,
            reference: $batch->reference,
            kind: $batch->kind,
            followUpNumber: $batch->follow_up_number,
        ));

        $service->recordSent($batch);
        $service->notifyWatchers($batch);
    }

    public function failed(?Throwable $exception): void
    {
        BrandOutreachBatch::whereKey($this->batchId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => mb_substr($exception?->getMessage() ?? 'Unknown delivery failure', 0, 5000),
        ]);
    }
}
