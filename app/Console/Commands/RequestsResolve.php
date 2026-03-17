<?php

namespace App\Console\Commands;

use App\Mail\UserNotificationEmail;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RequestsResolve extends Command
{
    protected $signature = 'requests:resolve
        {barcode : Product barcode}
        {status : 0=halal, 1=not_halal}
        {--notes= : Resolution notes}';

    protected $description = 'Resolve a product and notify all requesting users';

    public function handle(): int
    {
        $barcode = $this->argument('barcode');
        $status = $this->argument('status');
        $notes = $this->option('notes') ?? '';

        if (!in_array($status, ['0', '1'])) {
            $this->error('Status must be 0 (halal) or 1 (not halal).');
            return 1;
        }

        $statusLabel = $status === '0' ? 'Halal' : 'Not Halal';

        // 1. Update product in database
        $product = Product::where('Barcode', $barcode)->first();
        if ($product) {
            $product->update([
                'halal_status' => $status,
                'notes' => $notes,
            ]);
            $this->info("Updated product: {$product->product_name} → {$statusLabel}");
        } else {
            $this->warn("Product with barcode {$barcode} not found in products table.");
        }

        // 2. Invalidate product cache
        Cache::increment('products_cache_version');
        $this->info('Product cache invalidated.');

        // 3. Resolve all matching prioritisation requests
        $requests = PrioritisationRequest::where('barcode', $barcode)
            ->whereNotIn('status', ['resolved', 'dead_end'])
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No pending prioritisation requests for this barcode.');
            return 0;
        }

        $productName = $product?->product_name ?? $requests->first()->product_name ?? 'Unknown Product';
        $watcherEmails = collect();

        foreach ($requests as $request) {
            $request->update([
                'status' => 'resolved',
                'resolved_status' => (int) $status,
                'notes' => "Marked {$statusLabel}. {$notes}",
            ]);

            // Collect all watcher emails
            foreach ($request->watchers as $watcher) {
                $watcherEmails->push($watcher->user_email);
            }
        }

        // 4. Notify unique watchers
        $uniqueEmails = $watcherEmails->unique()->filter();
        $this->info("Resolved {$requests->count()} request(s). Notifying {$uniqueEmails->count()} user(s).");

        foreach ($uniqueEmails as $email) {
            try {
                Mail::to($email)->send(
                    new UserNotificationEmail('resolved', $productName, $barcode, $status)
                );
                $this->line("  Notified: {$email}");
            } catch (\Exception $e) {
                $this->warn("  Failed to notify {$email}: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return 0;
    }
}
