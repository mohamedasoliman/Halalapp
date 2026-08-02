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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

class SendBrandOutreachBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Avoid automatic retries after an ambiguous SMTP result.
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
        if (! $service->claimForSending($batch)) {
            return;
        }

        Mail::mailer(config('outreach.mailer'))->to($batch->recipient_email)->send(new BrandOutreachEmail(
            brandName: $batch->brand->name,
            products: $batch->products,
            reference: $batch->reference,
            kind: $batch->kind,
            followUpNumber: $batch->follow_up_number,
            subjectOverride: $batch->subject,
            body: $batch->message_body,
            inReplyTo: $batch->in_reply_to_message_id,
            references: $batch->reference_message_ids ?? [],
        ));

        $service->recordSent($batch);
        try {
            $result = $service->notifyWatchers($batch);
            $needsReview = $result['failed'] + $result['uncertain'] + $result['sending'];
            if ($needsReview > 0) {
                BrandOutreachBatch::whereKey($batch->id)->where('status', 'sent')->update([
                    'error' => sprintf(
                        'Manufacturer email sent. Requester notifications require review (failed: %d; uncertain: %d; sending: %d).',
                        $result['failed'],
                        $result['uncertain'],
                        $result['sending'],
                    ),
                ]);
            }
        } catch (Throwable $exception) {
            $message = 'Manufacturer email sent, but requester notification processing requires review: '
                .mb_substr($exception->getMessage(), 0, 4800);
            BrandOutreachBatch::whereKey($batch->id)->where('status', 'sent')->update(['error' => $message]);
            Log::error($message, ['batch_id' => $batch->id, 'reference' => $batch->reference]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $batch = BrandOutreachBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $error = mb_substr($exception?->getMessage() ?? 'Unknown delivery failure', 0, 4800);
        if ($batch->status === 'sent') {
            $batch->update([
                'error' => "Manufacturer email sent. Post-send processing requires review: {$error}",
            ]);

            return;
        }

        if ($batch->status === 'sending') {
            $batch->update([
                'status' => 'uncertain',
                'error' => "Manufacturer email delivery outcome is uncertain; do not retry without reconciliation: {$error}",
            ]);

            return;
        }

        $batch->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => $error,
        ]);
    }
}
