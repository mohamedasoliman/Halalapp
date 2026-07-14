<?php

namespace Tests\Unit;

use App\Support\MembershipTier;
use PHPUnit\Framework\TestCase;

class BusinessTierDataTest extends TestCase
{
    public function test_business_data_uses_canonical_tiers_and_entitlements(): void
    {
        $path = dirname(__DIR__, 2).'/public/data/BusinessList.json';
        $businesses = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertNotEmpty($businesses);

        foreach ($businesses as $business) {
            $name = $business['Name'] ?? 'Unnamed business';
            $this->assertArrayHasKey('Tier', $business, $name);

            $tier = MembershipTier::normalise($business['Tier']);
            $this->assertSame(MembershipTier::label($tier), $business['Tier'], $name);
            $this->assertArrayNotHasKey('BusinessType', $business, $name);
            $this->assertArrayNotHasKey('Verified', $business, $name);
            $this->assertArrayNotHasKey('IsVerified', $business, $name);
            $images = is_array($business['images'] ?? null)
                ? $business['images']
                : [];
            $this->assertLessThanOrEqual(
                MembershipTier::galleryLimit($tier),
                count(array_filter($images)),
                $name
            );

            $deals = $business['Deals'] ?? [];
            $this->assertIsArray($deals, $name);
            $this->assertLessThanOrEqual(
                MembershipTier::dealLimit($tier),
                count($deals),
                $name
            );

            if (! MembershipTier::canPublishDeal($tier)) {
                $this->assertArrayNotHasKey('Deals', $business, $name);
                $this->assertArrayNotHasKey('DealTitle', $business, $name);
            } elseif ($deals !== []) {
                $this->assertSame($deals[0]['Title'], $business['DealTitle'] ?? null, $name);
            }

            if (! MembershipTier::canPublishMenu($tier)) {
                $this->assertArrayNotHasKey('MenuUrl', $business, $name);
                $this->assertArrayNotHasKey('menu_url', $business, $name);
            }

            if (! MembershipTier::canAppearInCarousel($tier)
                || ($business['BusinessStatus'] ?? 'operational') !== 'operational') {
                $this->assertFalse((bool) ($business['FeatureInCarousel'] ?? false), $name);
            }
        }
    }
}
