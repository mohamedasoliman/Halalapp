<?php

namespace App\Console\Commands;

use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Services\RequestNotificationService;
use App\Services\RequestRecipientService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RequestsRequestInformation extends Command
{
    protected $signature = 'requests:request-information
        {barcode : Exact product barcode}
        {--event= : Stable unique event reference for idempotent delivery}
        {--send : Prepare and deliver the approved information request}';

    protected $description = 'Preview or send a retry-safe request for product photos and information';

    public function handle(
        RequestNotificationService $notifications,
        RequestRecipientService $recipients,
    ): int {
        $barcode = trim((string) $this->argument('barcode'));
        if (preg_match('/^\d{8,14}$/', $barcode) !== 1) {
            $this->error('An exact 8-14 digit barcode is required.');

            return self::FAILURE;
        }

        $requests = PrioritisationRequest::with('watchers')
            ->where('barcode', $barcode)
            ->whereNotIn('status', ['resolved', 'dead_end'])
            ->orderBy('id')
            ->get();

        if ($requests->isEmpty()) {
            $this->error('No active request was found for this exact barcode.');

            return self::FAILURE;
        }

        $namedRequest = $requests->first(
            fn (PrioritisationRequest $request) => trim((string) $request->product_name) !== ''
        );
        $productName = trim((string) $namedRequest?->product_name);
        if ($productName === '') {
            $productName = trim((string) Product::where('Barcode', $barcode)->value('product_name'));
        }
        if ($productName === '') {
            $productName = 'your requested product';
        }

        $eligibleRecipients = $recipients->collect($requests);
        $this->line("Product: {$productName} ({$barcode})");
        $this->line('Active requests: '.$requests->count());
        $this->line('Eligible unique recipients: '.$eligibleRecipients->count());

        if (! $this->option('send')) {
            $this->info('Preview complete. No delivery rows were created and no emails were sent.');

            return self::SUCCESS;
        }

        $eventReference = trim((string) $this->option('event'));
        if ($eventReference === '') {
            $this->error('--event is required with --send so retries remain idempotent.');

            return self::FAILURE;
        }

        try {
            $prepared = $notifications->prepareInformationRequestEvent(
                $eventReference,
                $requests,
                $productName,
                $barcode,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $result = $notifications->deliverEvent($eventReference);

        $this->line('Prepared recipients: '.$prepared->count());
        $this->line(sprintf(
            'Emails sent: %d; failed: %d; uncertain: %d; sending: %d; skipped/already claimed: %d.',
            $result['sent'],
            $result['failed'],
            $result['uncertain'],
            $result['sending'],
            $result['skipped'],
        ));

        if ($result['failed'] > 0) {
            $this->warn("Retry safely with requests:resolve --retry-event='{$eventReference}'.");
        }
        if ($result['uncertain'] > 0 || $result['sending'] > 0) {
            $this->warn('Uncertain or sending deliveries require manual reconciliation and are excluded from automatic retries.');
        }

        return $result['failed'] === 0
            && $result['uncertain'] === 0
            && $result['sending'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
