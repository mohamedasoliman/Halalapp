<?php

namespace App\Console\Commands;

use App\Support\MembershipDeal;
use App\Support\MembershipTier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class SyncRestaurantMembershipTiers extends Command
{
    protected $signature = 'restaurants:sync-membership-tiers
        {--dry-run : Report the migration without writing files}';

    protected $description = 'Add canonical membership tiers while preserving legacy app compatibility';

    public function handle(): int
    {
        $path = public_path('data/HalalRestaurantsList.json');
        if (! File::exists($path)) {
            $this->error("Restaurant data not found: {$path}");

            return self::FAILURE;
        }

        try {
            $restaurants = json_decode(
                File::get($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            $this->error('Restaurant data is invalid JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_array($restaurants)) {
            $this->error('Restaurant data must contain a JSON array.');

            return self::FAILURE;
        }

        $counts = array_fill_keys(MembershipTier::VALUES, 0);
        $changed = 0;

        foreach ($restaurants as &$restaurant) {
            $canonicalValue = trim((string) ($restaurant['Tier'] ?? ''));
            $tier = MembershipTier::normalise(
                $canonicalValue !== ''
                    ? $canonicalValue
                    : ($restaurant['membership_tier'] ?? null)
            );
            $counts[$tier]++;

            $before = $restaurant;
            $restaurant['Tier'] = MembershipTier::label($tier);
            $restaurant['BUSINESS_STATUS'] = $this->normaliseBusinessStatus(
                $restaurant['BUSINESS_STATUS'] ?? null
            );
            $this->applyLegacyCompatibilityValue($restaurant, $tier);
            unset($restaurant['is_verified']);
            $this->enforceGalleryLimit($restaurant, $tier);
            $this->enforceMenuEntitlement($restaurant, $tier);
            MembershipDeal::applyToRecord(
                $restaurant,
                MembershipDeal::fromRecord($restaurant, $tier),
                $tier
            );
            $this->enforcePromotionEntitlements($restaurant, $tier);

            if ($restaurant !== $before) {
                $changed++;
            }
        }
        unset($restaurant);

        $this->table(
            ['Tier', 'Restaurants'],
            array_map(
                fn (string $tier): array => [MembershipTier::label($tier), $counts[$tier]],
                MembershipTier::VALUES
            )
        );
        $this->line("Records requiring migration: {$changed}");

        if ($this->option('dry-run') || $changed === 0) {
            $this->info($changed === 0 ? 'Restaurant tiers are already in sync.' : 'Dry run complete. No files changed.');

            return self::SUCCESS;
        }

        $backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($backupDirectory, 0700);
        chmod($backupDirectory, 0700);
        $backupPath = $backupDirectory.'/restaurant_tiers_'.now()->format('Y-m-d_His').'.json';
        File::copy($path, $backupPath);
        chmod($backupPath, 0600);

        File::put(
            $path,
            json_encode(
                array_values($restaurants),
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            ).PHP_EOL,
            true
        );

        $publicPath = '/home5/halalapp/public_html/data/HalalRestaurantsList.json';
        if (is_dir(dirname($publicPath))) {
            File::copy($path, $publicPath);
        }

        $this->info("Backup created: {$backupPath}");
        $this->info("Migrated {$changed} restaurant records.");

        return self::SUCCESS;
    }

    private function applyLegacyCompatibilityValue(array &$restaurant, string $tier): void
    {
        $legacyValue = MembershipTier::legacyRestaurantValue($tier);

        if ($legacyValue !== '') {
            $restaurant['membership_tier'] = $legacyValue;

            return;
        }

        // Missing, empty, "free", and "none" remain compatible with the app's entry tier.
        $currentValue = strtolower(trim((string) ($restaurant['membership_tier'] ?? '')));
        if (! in_array($currentValue, ['', 'free', 'none'], true)) {
            $restaurant['membership_tier'] = '';
        }
    }

    private function normaliseBusinessStatus(mixed $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            '' => 'OPERATIONAL',
            'OPERATIONAL', 'OPEN' => 'OPERATIONAL',
            'CLOSED_TEMPORARILY', 'TEMPORARILY_CLOSED' => 'CLOSED_TEMPORARILY',
            'REVIEW_REQUIRED' => 'REVIEW_REQUIRED',
            default => 'UNKNOWN',
        };
    }

    private function enforceGalleryLimit(array &$restaurant, string $tier): void
    {
        $limit = MembershipTier::galleryLimit($tier);

        foreach (array_keys($restaurant) as $key) {
            if (preg_match('/^Image_(\d+)$/', $key, $matches) !== 1) {
                continue;
            }

            if ((int) $matches[1] > $limit) {
                unset($restaurant[$key]);
            }
        }
    }

    private function enforcePromotionEntitlements(array &$restaurant, string $tier): void
    {
        if (! MembershipTier::canReceiveEnquiries($tier)) {
            unset($restaurant['EnquiryEmail'], $restaurant['enquiry_email']);
        }

    }

    private function enforceMenuEntitlement(array &$restaurant, string $tier): void
    {
        if (! MembershipTier::canPublishMenu($tier)) {
            unset($restaurant['menu_url'], $restaurant['MenuUrl']);
        }
    }
}
