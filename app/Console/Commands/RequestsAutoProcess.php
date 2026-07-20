<?php

namespace App\Console\Commands;

use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Services\ProductResolutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RequestsAutoProcess extends Command
{
    protected $signature = 'requests:auto-process
        {--apply : Apply resolutions and send retry-safe notifications}
        {--dry-run : Deprecated alias for preview-only mode}';

    protected $description = 'Preview active requests whose products already have a reviewed halal status';

    public function handle(ProductResolutionService $resolutions): int
    {
        $requests = PrioritisationRequest::with('watchers')
            ->active()
            ->orderBy('id')
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No active prioritisation requests to auto-process.');

            return self::SUCCESS;
        }

        $products = Product::query()
            ->whereIn('Barcode', $requests->pluck('barcode')->filter()->unique())
            ->whereIn('halal_status', ['0', '1'])
            ->get()
            ->keyBy(fn (Product $product) => trim((string) $product->Barcode));

        $groups = $requests
            ->filter(fn (PrioritisationRequest $request) => $products->has(trim((string) $request->barcode)))
            ->groupBy(fn (PrioritisationRequest $request) => trim((string) $request->barcode));

        $requestCount = $groups->sum(fn (Collection $group) => $group->count());
        $this->info("Products ready to auto-resolve: {$groups->count()}; requests: {$requestCount}.");

        if (! $this->option('apply') || $this->option('dry-run')) {
            $this->displayPreview($groups, $products);
            $this->info('Preview complete. No changes made and no notifications sent. Re-run with --apply only after approval.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($groups as $barcode => $barcodeRequests) {
            $product = $products->get($barcode);
            $status = (string) $product->halal_status;
            $statusLabel = $status === '0' ? 'Halal' : 'Not Halal';
            $result = $resolutions->resolve(
                (string) $barcode,
                $status,
                "Auto-resolved: product already had the reviewed {$statusLabel} verdict.",
                eventReference: "auto-resolution:product:{$product->id}:status:{$status}",
            );
            $delivery = $result['delivery'];
            $failures += $delivery['failed'] + $delivery['uncertain'] + $delivery['sending'];
            $this->line(sprintf(
                'Resolved %d request(s) for %s (%s). Notifications sent: %d; failed: %d; uncertain: %d; sending: %d.',
                $result['requests_resolved'],
                $product->product_name,
                $barcode,
                $delivery['sent'],
                $delivery['failed'],
                $delivery['uncertain'],
                $delivery['sending'],
            ));
        }

        $this->info('Auto-processing complete.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function displayPreview(Collection $groups, Collection $products): void
    {
        foreach ($groups as $barcode => $requests) {
            $product = $products->get($barcode);
            $status = (string) $product->halal_status === '0' ? 'Halal' : 'Not Halal';
            $this->line("[{$status}] {$product->product_name} ({$barcode}) - {$requests->count()} request(s)");
        }
    }
}
