<?php

namespace App\Console\Commands;

use App\Support\MembershipDeal;
use App\Support\MembershipTier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class SyncBusinessMembershipTiers extends Command
{
    protected $signature = 'businesses:sync-membership-tiers
        {--dry-run : Report the migration without writing files}';

    protected $description = 'Standardize business tiers and enforce membership entitlements';

    public function handle(): int
    {
        $path = public_path('data/BusinessList.json');
        if (! File::exists($path)) {
            $this->error("Business data not found: {$path}");

            return self::FAILURE;
        }

        try {
            $businesses = json_decode(
                File::get($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            $this->error('Business data is invalid JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_array($businesses)) {
            $this->error('Business data must contain a JSON array.');

            return self::FAILURE;
        }

        $counts = array_fill_keys(MembershipTier::VALUES, 0);
        $changed = 0;

        foreach ($businesses as &$business) {
            $tier = MembershipTier::normalise($business['Tier'] ?? null);
            $counts[$tier]++;
            $before = $business;

            $business['Tier'] = MembershipTier::label($tier);
            unset($business['Verified'], $business['IsVerified']);
            $this->enforceGalleryLimit($business, $tier);
            $this->enforceMenuEntitlement($business, $tier);
            MembershipDeal::applyToRecord(
                $business,
                MembershipDeal::fromRecord($business, $tier),
                $tier
            );

            $isOperational = ($business['BusinessStatus'] ?? 'operational') === 'operational';
            $requestedCarousel = array_key_exists('FeatureInCarousel', $business)
                ? (bool) $business['FeatureInCarousel']
                : MembershipTier::canAppearInCarousel($tier);
            $business['FeatureInCarousel'] = MembershipTier::canAppearInCarousel($tier)
                && $isOperational
                && $requestedCarousel;

            if ($business !== $before) {
                $changed++;
            }
        }
        unset($business);

        $this->table(
            ['Tier', 'Businesses'],
            array_map(
                fn (string $tier): array => [MembershipTier::label($tier), $counts[$tier]],
                MembershipTier::VALUES
            )
        );
        $this->line("Records requiring migration: {$changed}");

        if ($this->option('dry-run') || $changed === 0) {
            $this->info($changed === 0
                ? 'Business tiers are already in sync.'
                : 'Dry run complete. No files changed.');

            return self::SUCCESS;
        }

        $backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($backupDirectory, 0700);
        chmod($backupDirectory, 0700);
        $backupPath = $backupDirectory.'/business_tiers_'.now()->format('Y-m-d_His').'.json';
        File::copy($path, $backupPath);
        chmod($backupPath, 0600);

        File::put(
            $path,
            json_encode(
                array_values($businesses),
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            ).PHP_EOL,
            true
        );

        $publicPath = '/home5/halalapp/public_html/data/BusinessList.json';
        if (is_dir(dirname($publicPath))) {
            File::copy($path, $publicPath);
        }

        $this->info("Backup created: {$backupPath}");
        $this->info("Migrated {$changed} business records.");

        return self::SUCCESS;
    }

    private function enforceGalleryLimit(array &$business, string $tier): void
    {
        $limit = MembershipTier::galleryLimit($tier);
        $rawImages = $business['images'] ?? [];
        $images = is_array($rawImages)
            ? array_values(array_filter($rawImages))
            : [];

        if ($limit === 0) {
            unset($business['images']);

            return;
        }

        $business['images'] = array_slice($images, 0, $limit);
    }

    private function enforceMenuEntitlement(array &$business, string $tier): void
    {
        if (! MembershipTier::canPublishMenu($tier)) {
            unset($business['MenuUrl'], $business['menu_url']);
        }
    }
}
