<?php

namespace App\Support;

final class HalalStatus
{
    public const HALAL = '0';

    public const NOT_HALAL = '1';

    public const UNREVIEWED = '2';

    public const MASHBOOH = '3';

    public static function values(): array
    {
        return [
            self::HALAL,
            self::NOT_HALAL,
            self::UNREVIEWED,
            self::MASHBOOH,
        ];
    }

    public static function label(string|int|null $status): string
    {
        return match ((string) $status) {
            self::HALAL => 'Halal',
            self::NOT_HALAL => 'Not Halal',
            self::MASHBOOH => 'Mashbooh',
            default => 'Unreviewed',
        };
    }

    public static function badgeClass(string|int|null $status): string
    {
        return match ((string) $status) {
            self::HALAL => 'label-success',
            self::NOT_HALAL => 'label-danger',
            self::MASHBOOH => 'label-info',
            default => 'label-warning',
        };
    }
}
