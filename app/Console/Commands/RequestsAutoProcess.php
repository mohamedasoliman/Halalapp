<?php

namespace App\Console\Commands;

use App\Mail\UserNotificationEmail;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RequestsAutoProcess extends Command
{
    protected $signature = 'requests:auto-process {--dry-run : Show what would be resolved without changing data}';

    protected $description = 'Resolve active requests whose products already have a reviewed halal status';

    public function handle(): int
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

        if ($this->option('dry-run')) {
            $this->displayPreview($groups, $products);
            $this->info('Dry run complete. No changes made and no notifications sent.');

            return self::SUCCESS;
        }

        foreach ($groups as $barcode => $barcodeRequests) {
            $product = $products->get($barcode);
            $status = (string) $product->halal_status;
            $statusLabel = $status === '0' ? 'Halal' : 'Not Halal';

            DB::transaction(function () use ($barcodeRequests, $status, $statusLabel) {
                foreach ($barcodeRequests as $request) {
                    $note = "Auto-resolved: product is already marked {$statusLabel} in the production database.";
                    $request->update([
                        'status' => 'resolved',
                        'resolved_status' => (int) $status,
                        'notes' => trim(implode("\n", array_filter([$request->notes, $note]))),
                    ]);
                }
            });

            $this->notifyWatchers($barcodeRequests, $product, $status);
            $this->line("Resolved {$barcodeRequests->count()} request(s) for {$product->product_name} ({$barcode}).");
        }

        $this->info('Auto-processing complete.');

        return self::SUCCESS;
    }

    private function displayPreview(Collection $groups, Collection $products): void
    {
        foreach ($groups as $barcode => $requests) {
            $product = $products->get($barcode);
            $status = (string) $product->halal_status === '0' ? 'Halal' : 'Not Halal';
            $this->line("[{$status}] {$product->product_name} ({$barcode}) - {$requests->count()} request(s)");
        }
    }

    private function notifyWatchers(Collection $requests, Product $product, string $status): void
    {
        $emails = $requests->flatMap(function (PrioritisationRequest $request) {
            return collect([$request->user_email])->merge($request->watchers->pluck('user_email'));
        })->filter(fn (?string $email) => $this->shouldNotify($email))->unique();

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new UserNotificationEmail(
                    'resolved',
                    $product->product_name ?? 'your requested product',
                    (string) $product->Barcode,
                    $status,
                ));
            } catch (Throwable $exception) {
                $this->warn("Request resolved, but notification to {$email} failed: {$exception->getMessage()}");
            }
        }
    }

    private function shouldNotify(?string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && ! str_ends_with(strtolower($email), '@halalkiwi.com');
    }
}
