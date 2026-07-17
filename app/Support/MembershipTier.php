<?php

namespace App\Support;

final class MembershipTier
{
    public const FREE = 'free';

    public const COMMUNITY = self::FREE;

    public const STARTER = 'starter';

    public const GROWTH = 'growth';

    public const PREMIUM = 'premium';

    public const VALUES = [
        self::FREE,
        self::STARTER,
        self::GROWTH,
        self::PREMIUM,
    ];

    public static function normalise(mixed $tier): string
    {
        return match (strtolower(trim((string) $tier))) {
            'premium', 'gold' => self::PREMIUM,
            'growth', 'silver', 'featured' => self::GROWTH,
            'starter', 'verified' => self::STARTER,
            'basic', 'community', 'free' => self::FREE,
            default => self::FREE,
        };
    }

    public static function label(mixed $tier): string
    {
        return ucfirst(self::normalise($tier));
    }

    public static function adminLabel(mixed $tier): string
    {
        $tier = self::normalise($tier);

        return $tier === self::FREE ? 'Basic' : self::label($tier);
    }

    public static function weeklyPrice(mixed $tier): int
    {
        return match (self::normalise($tier)) {
            self::FREE => 2,
            self::STARTER => 5,
            self::GROWTH => 15,
            self::PREMIUM => 30,
        };
    }

    public static function galleryLimit(mixed $tier): int
    {
        return match (self::normalise($tier)) {
            self::GROWTH => 3,
            self::PREMIUM => 5,
            default => 0,
        };
    }

    public static function dealLimit(mixed $tier): int
    {
        return match (self::normalise($tier)) {
            self::STARTER => 1,
            self::GROWTH => 3,
            self::PREMIUM => 5,
            default => 0,
        };
    }

    public static function legacyRestaurantValue(mixed $tier): string
    {
        return match (self::normalise($tier)) {
            self::STARTER => 'verified',
            self::GROWTH => 'featured',
            self::PREMIUM => 'premium',
            default => '',
        };
    }

    public static function sortOrder(mixed $tier): int
    {
        return match (self::normalise($tier)) {
            self::PREMIUM => 0,
            self::GROWTH => 1,
            self::STARTER => 2,
            default => 3,
        };
    }

    public static function isPartner(mixed $tier): bool
    {
        return self::normalise($tier) !== self::FREE;
    }

    public static function isFeatured(mixed $tier): bool
    {
        return in_array(
            self::normalise($tier),
            [self::GROWTH, self::PREMIUM],
            true
        );
    }

    public static function canPublishDeal(mixed $tier): bool
    {
        return self::dealLimit($tier) > 0;
    }

    public static function canPublishMenu(mixed $tier): bool
    {
        return self::isFeatured($tier);
    }

    public static function canReceiveEnquiries(mixed $tier): bool
    {
        return self::isFeatured($tier);
    }

    public static function canAppearInCarousel(mixed $tier): bool
    {
        return self::isFeatured($tier);
    }
}
