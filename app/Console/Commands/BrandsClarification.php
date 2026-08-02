<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Services\BrandOutreachService;
use Illuminate\Console\Command;
use Throwable;

class BrandsClarification extends Command
{
    protected $signature = 'brands:clarification
        {brand : Recipient brand ID or exact brand name}
        {--communication-id= : Approved inbound communication being answered}
        {--event= : Unique stable event reference for idempotency}
        {--subject= : Exact approved email subject}
        {--body= : Exact approved plain-text email body}
        {--body-file= : Read the approved plain-text body from this file}
        {--barcode=* : Exact covered barcode; repeat for multiple products}
        {--reference-message-id=* : Additional Message-ID for the References header}';

    protected $description = 'Create an audited manufacturer clarification draft without sending it';

    public function handle(BrandOutreachService $service): int
    {
        $brandArgument = trim((string) $this->argument('brand'));
        $brand = ctype_digit($brandArgument)
            ? Brand::find((int) $brandArgument)
            : Brand::where('name', $brandArgument)->first();
        if (! $brand) {
            $this->error('Brand was not found by exact ID/name. No draft was created.');

            return self::FAILURE;
        }

        $communicationId = filter_var($this->option('communication-id'), FILTER_VALIDATE_INT);
        $communication = $communicationId ? BrandCommunication::find($communicationId) : null;
        if (! $communication) {
            $this->error('A valid approved inbound --communication-id is required. No draft was created.');

            return self::FAILURE;
        }

        $bodyOption = $this->option('body');
        $bodyFile = $this->option('body-file');
        if ($bodyOption !== null && $bodyFile !== null) {
            $this->error('Use either --body or --body-file, not both. No draft was created.');

            return self::FAILURE;
        }

        if ($bodyFile !== null) {
            $path = (string) $bodyFile;
            if (! is_file($path) || ! is_readable($path)) {
                $this->error('The clarification body file is not readable. No draft was created.');

                return self::FAILURE;
            }
            $bodyOption = file_get_contents($path);
        }

        try {
            $batch = $service->createClarificationDraft(
                $brand,
                $communication,
                (string) $this->option('event'),
                (string) $this->option('subject'),
                (string) $bodyOption,
                $this->option('barcode'),
                $this->option('reference-message-id'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage().' No draft was created or queued.');

            return self::FAILURE;
        }

        $state = $batch->wasRecentlyCreated ? 'created' : 'already exists';
        $this->info("Clarification batch #{$batch->id} {$state}: {$batch->reference}");
        $this->table(
            ['Brand', 'Recipient', 'Subject', 'Barcodes', 'Source communication', 'Status'],
            [[
                $brand->name,
                $batch->recipient_email,
                $batch->subject,
                collect($batch->products)->pluck('barcode')->implode(', '),
                $batch->source_communication_id,
                $batch->status,
            ]],
        );
        $this->info("No email was queued or sent. After explicit approval, use: php artisan brands:outreach --kind=clarification --queue --batch={$batch->id}");

        return self::SUCCESS;
    }
}
