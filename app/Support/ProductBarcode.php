<?php

namespace App\Support;

final class ProductBarcode
{
    public static function clean(?string $barcode): string
    {
        return trim((string) $barcode);
    }

    public static function key(?string $barcode): ?string
    {
        $barcode = self::clean($barcode);
        if ($barcode === '') {
            return null;
        }

        $key = ltrim($barcode, '0');

        return $key === '' ? null : $key;
    }

    public static function canonical(?string $barcode): string
    {
        $barcode = self::clean($barcode);
        $key = self::key($barcode);
        if ($key === null || ! ctype_digit($barcode) || strlen($key) < 7) {
            return $barcode;
        }

        foreach ([8, 12, 13, 14] as $length) {
            if (strlen($key) > $length) {
                continue;
            }

            $candidate = str_pad($key, $length, '0', STR_PAD_LEFT);
            if (self::hasValidCheckDigit($candidate)) {
                return $candidate;
            }
        }

        return $barcode;
    }

    public static function isValidGtin(?string $barcode): bool
    {
        $barcode = self::clean($barcode);

        return ctype_digit($barcode)
            && in_array(strlen($barcode), [8, 12, 13, 14], true)
            && self::hasValidCheckDigit($barcode);
    }

    public static function hasValidCheckDigit(string $barcode): bool
    {
        if ($barcode === '' || ! ctype_digit($barcode)) {
            return false;
        }

        $digits = array_map('intval', str_split($barcode));
        $checkDigit = array_pop($digits);
        $sum = 0;
        $weight = 3;

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            $sum += $digits[$index] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }
}
