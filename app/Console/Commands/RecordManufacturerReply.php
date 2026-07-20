<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\InboundBrandCommunicationService;
use Illuminate\Console\Command;

class RecordManufacturerReply extends Command
{
    protected $signature = 'brands:record-reply
        {brand : Brand ID or exact brand name}
        {message_id : Manufacturer email Message-ID}
        {--subject= : Email subject}
        {--summary= : Concise evidence summary}
        {--barcode=* : Exact covered barcode; repeat for multiple products}
        {--proof= : Saved evidence file or folder path}';

    protected $description = 'Idempotently record an approved manufacturer reply before applying verdicts';

    public function handle(InboundBrandCommunicationService $communications): int
    {
        $brandArgument = trim((string) $this->argument('brand'));
        $brand = ctype_digit($brandArgument)
            ? Brand::find((int) $brandArgument)
            : Brand::where('name', $brandArgument)->first();

        if (! $brand) {
            $this->error('Brand was not found by exact ID/name. No communication was recorded.');

            return self::FAILURE;
        }

        $communication = $communications->record(
            $brand,
            (string) $this->argument('message_id'),
            $this->option('subject') ? (string) $this->option('subject') : null,
            $this->option('summary') ? (string) $this->option('summary') : null,
            $this->option('barcode'),
            $this->option('proof') ? (string) $this->option('proof') : null,
        );

        $state = $communication->wasRecentlyCreated ? 'created' : 'already recorded';
        $this->info("Inbound communication #{$communication->id} {$state}; status: {$communication->processing_status}.");

        return self::SUCCESS;
    }
}
