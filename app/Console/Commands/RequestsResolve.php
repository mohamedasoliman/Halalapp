<?php

namespace App\Console\Commands;

use App\Services\ProductResolutionService;
use App\Services\RequestNotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class RequestsResolve extends Command
{
    protected $signature = 'requests:resolve
        {barcode? : Product barcode}
        {status? : 0=halal, 1=not_halal}
        {--notes= : Internal resolution notes}
        {--public-note= : Optional concise note shown to app users}
        {--proof= : Saved proof path}
        {--communication-id= : Approved inbound brand communication ID}
        {--event= : Stable notification event reference}
        {--retry-event= : Retry only failed/pending notifications; uncertain/sending rows require review}';

    protected $description = 'Resolve an exact-barcode product and notify all eligible requesting users safely';

    public function handle(
        ProductResolutionService $resolutions,
        RequestNotificationService $notifications,
    ): int {
        if ($retryEvent = $this->option('retry-event')) {
            $result = $notifications->deliverEvent((string) $retryEvent);
            $this->displayDeliveryResult((string) $retryEvent, $result);

            return $result['failed'] === 0 && $result['uncertain'] === 0 && $result['sending'] === 0
                ? self::SUCCESS
                : self::FAILURE;
        }

        if ($this->argument('barcode') === null || $this->argument('status') === null) {
            $this->error('Barcode and status are required unless --retry-event is used.');

            return self::FAILURE;
        }

        try {
            $result = $resolutions->resolve(
                (string) $this->argument('barcode'),
                (string) $this->argument('status'),
                (string) ($this->option('notes') ?? ''),
                $this->option('proof') ? (string) $this->option('proof') : null,
                $this->option('communication-id') ? (int) $this->option('communication-id') : null,
                $this->option('event') ? (string) $this->option('event') : null,
                publicNote: $this->option('public-note') ? (string) $this->option('public-note') : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (ModelNotFoundException) {
            $this->error('Exact-barcode product was not found. No requests were resolved and no emails were sent.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Resolved %d request(s) for %s. Prepared %d unique recipient(s).',
            $result['requests_resolved'],
            $result['product_name'],
            $result['recipients_prepared'],
        ));
        $this->displayDeliveryResult($result['event_reference'], $result['delivery']);

        return $result['delivery']['failed'] === 0
            && $result['delivery']['uncertain'] === 0
            && $result['delivery']['sending'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function displayDeliveryResult(string $eventReference, array $result): void
    {
        $this->line("Notification event: {$eventReference}");
        $this->line(sprintf(
            'Notifications sent: %d; failed: %d; uncertain: %d; sending: %d; skipped/already claimed: %d.',
            $result['sent'],
            $result['failed'],
            $result['uncertain'],
            $result['sending'],
            $result['skipped'],
        ));

        if ($result['failed'] > 0) {
            $this->warn("Retry safely with --retry-event='{$eventReference}'. Successfully sent recipients will be skipped.");
        }

        if ($result['uncertain'] > 0) {
            $this->warn('Uncertain deliveries may already have been accepted by SMTP. Reconcile them manually; --retry-event will not resend them.');
        }

        if ($result['sending'] > 0) {
            $this->warn('Sending deliveries are still in progress or were left by an interrupted process. Reconcile them manually before changing their state.');
        }
    }
}
