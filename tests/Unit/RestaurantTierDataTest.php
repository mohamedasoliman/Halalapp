<?php

namespace Tests\Unit;

use App\Support\MembershipTier;
use PHPUnit\Framework\TestCase;

class RestaurantTierDataTest extends TestCase
{
    public function test_restaurant_data_has_canonical_and_legacy_compatible_tiers(): void
    {
        $path = dirname(__DIR__, 2).'/public/data/HalalRestaurantsList.json';
        $restaurants = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertNotEmpty($restaurants);

        foreach ($restaurants as $restaurant) {
            $name = $restaurant['NAME'] ?? 'Unnamed restaurant';
            $this->assertArrayHasKey('Tier', $restaurant, $name);
            $this->assertContains(
                $restaurant['BUSINESS_STATUS'] ?? null,
                ['OPERATIONAL', 'CLOSED_TEMPORARILY', 'UNKNOWN', 'REVIEW_REQUIRED'],
                $name
            );

            $tier = MembershipTier::normalise($restaurant['Tier']);
            $this->assertSame(MembershipTier::label($tier), $restaurant['Tier'], $name);
            $this->assertSame(
                MembershipTier::legacyRestaurantValue($tier),
                $restaurant['membership_tier'] ?? '',
                $name
            );
            $this->assertArrayNotHasKey('is_verified', $restaurant, $name);

            $imageCount = count(array_filter(
                $restaurant,
                static fn (mixed $value, string $key): bool => preg_match('/^Image_\d+$/', $key) === 1
                    && trim((string) $value) !== '',
                ARRAY_FILTER_USE_BOTH
            ));

            $this->assertLessThanOrEqual(
                MembershipTier::galleryLimit($tier),
                $imageCount,
                $name
            );

            if (! MembershipTier::canReceiveEnquiries($tier)) {
                $this->assertArrayNotHasKey('EnquiryEmail', $restaurant, $name);
            }

            $deals = $restaurant['Deals'] ?? [];
            $this->assertIsArray($deals, $name);
            $this->assertLessThanOrEqual(
                MembershipTier::dealLimit($tier),
                count($deals),
                $name
            );

            if (! MembershipTier::canPublishDeal($tier)) {
                $this->assertArrayNotHasKey('Deals', $restaurant, $name);
                $this->assertArrayNotHasKey('DealTitle', $restaurant, $name);
            } elseif ($deals !== []) {
                $this->assertSame($deals[0]['Title'], $restaurant['DealTitle'] ?? null, $name);
            }

            if (! MembershipTier::canPublishMenu($tier)) {
                $this->assertArrayNotHasKey('menu_url', $restaurant, $name);
                $this->assertArrayNotHasKey('MenuUrl', $restaurant, $name);
            }
        }
    }
}
