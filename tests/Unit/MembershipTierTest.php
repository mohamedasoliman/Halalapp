<?php

namespace Tests\Unit;

use App\Support\MembershipTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MembershipTierTest extends TestCase
{
    #[DataProvider('tierAliases')]
    public function test_it_normalises_current_and_legacy_tier_values(
        mixed $value,
        string $expected
    ): void {
        $this->assertSame($expected, MembershipTier::normalise($value));
    }

    public static function tierAliases(): array
    {
        return [
            'community' => ['community', MembershipTier::FREE],
            'free' => ['free', MembershipTier::FREE],
            'empty' => ['', MembershipTier::FREE],
            'starter' => ['starter', MembershipTier::STARTER],
            'verified' => ['verified', MembershipTier::STARTER],
            'growth' => ['growth', MembershipTier::GROWTH],
            'featured' => ['featured', MembershipTier::GROWTH],
            'silver' => ['silver', MembershipTier::GROWTH],
            'premium' => ['premium', MembershipTier::PREMIUM],
            'gold' => ['gold', MembershipTier::PREMIUM],
        ];
    }

    public function test_it_uses_the_business_membership_prices_and_gallery_limits(): void
    {
        $this->assertSame(
            ['free', 'starter', 'growth', 'premium'],
            MembershipTier::VALUES
        );

        $this->assertSame(0, MembershipTier::weeklyPrice('community'));
        $this->assertSame(5, MembershipTier::weeklyPrice('starter'));
        $this->assertSame(15, MembershipTier::weeklyPrice('growth'));
        $this->assertSame(30, MembershipTier::weeklyPrice('premium'));

        $this->assertSame(0, MembershipTier::galleryLimit('community'));
        $this->assertSame(0, MembershipTier::galleryLimit('starter'));
        $this->assertSame(3, MembershipTier::galleryLimit('growth'));
        $this->assertSame(5, MembershipTier::galleryLimit('premium'));

        $this->assertSame(0, MembershipTier::dealLimit('free'));
        $this->assertSame(1, MembershipTier::dealLimit('starter'));
        $this->assertSame(3, MembershipTier::dealLimit('growth'));
        $this->assertSame(5, MembershipTier::dealLimit('premium'));
    }

    public function test_it_maps_canonical_tiers_to_values_understood_by_existing_apps(): void
    {
        $this->assertSame('', MembershipTier::legacyRestaurantValue('community'));
        $this->assertSame('verified', MembershipTier::legacyRestaurantValue('starter'));
        $this->assertSame('featured', MembershipTier::legacyRestaurantValue('growth'));
        $this->assertSame('premium', MembershipTier::legacyRestaurantValue('premium'));
    }

    public function test_it_uses_the_same_entitlements_for_restaurants_and_businesses(): void
    {
        $this->assertFalse(MembershipTier::isPartner('free'));
        $this->assertTrue(MembershipTier::isPartner('starter'));
        $this->assertTrue(MembershipTier::isPartner('growth'));
        $this->assertTrue(MembershipTier::isPartner('premium'));
        $this->assertFalse(MembershipTier::canPublishDeal('free'));
        $this->assertTrue(MembershipTier::canPublishDeal('starter'));
        $this->assertTrue(MembershipTier::canPublishDeal('growth'));
        $this->assertFalse(MembershipTier::canPublishMenu('free'));
        $this->assertFalse(MembershipTier::canPublishMenu('starter'));
        $this->assertTrue(MembershipTier::canPublishMenu('growth'));
        $this->assertTrue(MembershipTier::canPublishMenu('premium'));
        $this->assertFalse(MembershipTier::canReceiveEnquiries('free'));
        $this->assertTrue(MembershipTier::canReceiveEnquiries('premium'));
        $this->assertSame(0, MembershipTier::sortOrder('premium'));
        $this->assertSame(3, MembershipTier::sortOrder('community'));
    }
}
