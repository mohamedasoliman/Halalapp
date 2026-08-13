<?php

namespace App\Console\Commands;

use App\Services\BrandCommunicationDispositionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class RecordManufacturerBarcodeDisposition extends Command
{
    protected $signature = 'brands:record-disposition
        {communication : Approved inbound communication ID}
        {barcode : Exact covered barcode}
        {disposition : kept_unreviewed, needs_clarification, or no_action}
        {--reason= : Concise approved per-barcode reasoning}';

    protected $description = 'Record an approved non-verdict outcome for one barcode in a manufacturer reply';

    public function handle(BrandCommunicationDispositionService $dispositions): int
    {
        try {
            $row = $dispositions->recordNonVerdict(
                (int) $this->argument('communication'),
                (string) $this->argument('barcode'),
                (string) $this->argument('disposition'),
                $this->option('reason') ? (string) $this->option('reason') : null,
            );
        } catch (ModelNotFoundException) {
            $this->error('Inbound communication was not found. Nothing was recorded.');

            return self::FAILURE;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Communication #%d barcode %s recorded as %s.',
            $row->brand_communication_id,
            $row->barcode,
            $row->disposition,
        ));

        return self::SUCCESS;
    }
}
