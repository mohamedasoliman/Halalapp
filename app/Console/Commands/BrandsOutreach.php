<?php

namespace App\Console\Commands;

use App\Mail\BrandOutreachEmail;
use App\Mail\UserNotificationEmail;
use App\Models\Brand;
use App\Models\BrandCommunication;
use App\Models\PrioritisationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class BrandsOutreach extends Command
{
    protected $signature = 'brands:outreach {--dry-run : Show what would be sent without sending}';
    protected $description = 'Send halal inquiry emails to brands with pending requests';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $requests = PrioritisationRequest::where('status', 'ready_for_outreach')
            ->whereNotNull('brand_name')
            ->get()
            ->groupBy('brand_name');

        if ($requests->isEmpty()) {
            $this->info('No requests ready for outreach.');
            return 0;
        }

        $this->info("Found {$requests->count()} brand(s) to contact.");

        foreach ($requests as $brandName => $brandRequests) {
            $brand = Brand::where('name', $brandName)->first();

            if (!$brand || !$brand->email || $brand->contact_type !== 'email') {
                $this->warn("Skipping {$brandName} — no email or contact form only.");
                continue;
            }

            if (str_starts_with($brand->email, 'http')) {
                $this->warn("Skipping {$brandName} — contact form URL, not email.");
                continue;
            }

            $products = $brandRequests->map(fn ($r) => [
                'name' => $r->product_name ?? 'Unknown Product',
                'barcode' => $r->barcode,
            ])->toArray();

            $displayBrand = explode('(', $brandName)[0];

            $this->info("  {$displayBrand} ({$brand->email}) — {$brandRequests->count()} product(s)");
            foreach ($products as $p) {
                $this->line("    - {$p['name']} ({$p['barcode']})");
            }

            if ($dryRun) {
                continue;
            }

            try {
                Mail::to($brand->email)->send(new BrandOutreachEmail(trim($displayBrand), $products));

                // Log communication
                BrandCommunication::create([
                    'brand_id' => $brand->id,
                    'direction' => 'outbound',
                    'subject' => "Halal Suitability Inquiry - {$displayBrand}",
                    'body_preview' => "Initial inquiry for " . count($products) . " product(s).",
                    'barcodes_mentioned' => array_column($products, 'barcode'),
                ]);

                // Update brand
                $brand->update(['last_contacted_at' => now()]);

                // Update request statuses
                foreach ($brandRequests as $request) {
                    $request->update([
                        'status' => 'contacted',
                        'notes' => 'Auto-outreach sent ' . now()->toDateString() . '. WAITING.',
                    ]);

                    // Notify watchers
                    foreach ($request->watchers as $watcher) {
                        if ($this->shouldSkipWatcherEmail($watcher->user_email)) {
                            $this->line("  Skipped placeholder watcher email: {$watcher->user_email}");
                            continue;
                        }

                        try {
                            Mail::to($watcher->user_email)->send(
                                new UserNotificationEmail('contacted', $request->product_name ?? 'your requested product', $request->barcode)
                            );
                        } catch (\Exception $e) {
                            $this->warn("  Failed to notify {$watcher->user_email}: {$e->getMessage()}");
                        }
                    }
                }

                $this->info("  Sent successfully.");
            } catch (\Exception $e) {
                $this->error("  Failed to send to {$brandName}: {$e->getMessage()}");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete. No emails were sent.');
        }

        return 0;
    }

    private function shouldSkipWatcherEmail(?string $email): bool
    {
        if (!$email) {
            return true;
        }

        return str_ends_with(strtolower($email), '@halalkiwi.com');
    }
}
