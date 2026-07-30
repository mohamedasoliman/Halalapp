<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ProductCatalogueRecord
{
    /**
     * Fields intentionally omit external verdicts, source details and timestamps.
     *
     * @return array{
     *     barcode: string,
     *     product_name: string,
     *     brand: string|null,
     *     country: string|null,
     *     category: string|null,
     *     ingredient: string|null,
     *     product_image: string|null,
     *     image_download_url?: string|null
     * }|null
     */
    public static function fromApiProduct(array $product): ?array
    {
        $barcode = ProductBarcode::canonical(self::text($product['barcode'] ?? null));
        if (! ProductBarcode::isValidGtin($barcode)) {
            return null;
        }

        $name = self::productNameValue($product['name'] ?? null);
        $brand = self::brandValue($product['brand'] ?? null);
        if (! self::hasUsableName($name)) {
            return null;
        }

        $productName = self::collapseRepeatedBrand(self::productName($name, $brand), $brand);
        if (! self::hasUsableName($productName)) {
            return null;
        }

        $category = self::meaningfulValue(
            $product['main_category']
                ?? ($product['all_categories'][0] ?? null)
                ?? ($product['categories'][0]['name'] ?? null)
        );

        return [
            'barcode' => $barcode,
            'product_name' => Str::limit($productName, 250, ''),
            'brand' => self::limited($brand, 250),
            'country' => self::limited(self::countryValue($product['origin'] ?? null), 250),
            'category' => self::limited($category, 250),
            'ingredient' => self::meaningfulValue($product['ingredients'] ?? null),
            'product_image' => null,
            'image_download_url' => self::imageUrl($product),
        ];
    }

    /**
     * @return array{
     *     barcode: string,
     *     product_name: string,
     *     brand: string|null,
     *     country: string|null,
     *     category: string|null,
     *     ingredient: string|null,
     *     product_image: string|null
     * }|null
     */
    public static function fromImportRow(array $row): ?array
    {
        $barcode = ProductBarcode::canonical(self::text($row['barcode'] ?? null));
        $name = self::productNameValue($row['product_name'] ?? null);
        $rawBrand = self::meaningfulValue($row['brand'] ?? null);
        $brand = self::brandValue($row['brand'] ?? null);
        if ($rawBrand !== null && $brand !== null && $rawBrand !== $brand) {
            $prefixLength = mb_strlen($rawBrand);
            if (mb_stripos($name, $rawBrand.' ') === 0) {
                $name = $brand.mb_substr($name, $prefixLength);
            }
        }
        $name = self::collapseRepeatedBrand($name, $brand);
        if (! ProductBarcode::isValidGtin($barcode) || ! self::hasUsableName($name)) {
            return null;
        }

        $image = self::meaningfulValue($row['product_image'] ?? null);
        if ($image !== null && (str_contains($image, '/') || str_contains($image, '\\'))) {
            $image = null;
        }

        return [
            'barcode' => $barcode,
            'product_name' => Str::limit($name, 250, ''),
            'brand' => self::limited($brand, 250),
            'country' => self::limited(self::countryValue($row['country'] ?? null), 250),
            'category' => self::limited(self::meaningfulValue($row['category'] ?? null), 250),
            'ingredient' => self::meaningfulValue($row['ingredient'] ?? null),
            'product_image' => self::limited($image, 250),
        ];
    }

    public static function hasUsableName(?string $name): bool
    {
        $name = self::text($name);
        if (mb_strlen($name) < 2 || preg_match('/[\p{L}]{2}/u', $name) !== 1) {
            return false;
        }

        $normalized = mb_strtolower(trim($name));
        if (in_array($normalized, [
            'product',
            'unknown',
            'unknown product',
            'unnamed',
            'unnamed product',
            'not found',
            'n/a',
            'na',
            'none',
        ], true)) {
            return false;
        }

        return preg_match('/^health star rating(?:\s+\d+(?:\.\d+)?)?$/iu', $normalized) !== 1;
    }

    private static function productName(string $name, ?string $brand): string
    {
        if ($brand === null || mb_strlen($brand) < 2 || ! self::hasUsableName($brand)) {
            return $name;
        }

        if (mb_stripos($name, $brand) !== false) {
            return $name;
        }

        return self::text($brand.' '.$name);
    }

    private static function collapseRepeatedBrand(string $name, ?string $brand): string
    {
        if ($brand === null || $brand === '') {
            return $name;
        }

        return trim((string) preg_replace(
            '/^('.preg_quote($brand, '/').')\s+\1\b/iu',
            '$1',
            $name
        ));
    }

    private static function productNameValue(mixed $value): string
    {
        $name = self::text($value);
        $name = trim((string) preg_replace(
            '/\s*\((?:product\s+)?not\s+available\)\s*$/iu',
            '',
            $name
        ));

        return trim((string) preg_replace(
            '/(?:\s*[-–—:]\s*)?ingredients?\s+(?:are\s+)?(?:missing|not\s+available)$/iu',
            '',
            $name
        ));
    }

    private static function brandValue(mixed $value): ?string
    {
        $brand = self::meaningfulValue($value);
        if ($brand === null) {
            return null;
        }

        $parts = preg_split('/\s*,\s*/u', $brand) ?: [];
        $first = trim((string) preg_replace('/^by\s+/iu', '', (string) ($parts[0] ?? '')));

        return $first !== '' ? $first : null;
    }

    private static function countryValue(mixed $value): ?string
    {
        $country = self::meaningfulValue($value);
        if ($country === null) {
            return null;
        }

        $countries = [];
        foreach (preg_split('/\s*,\s*/u', $country) ?: [] as $item) {
            $item = trim($item);
            $key = mb_strtolower($item);
            if ($item !== '' && ! isset($countries[$key])) {
                $countries[$key] = $item;
            }
        }

        return $countries === [] ? null : implode(', ', array_values($countries));
    }

    private static function imageUrl(array $product): ?string
    {
        foreach (['main_image', 'image'] as $field) {
            $url = self::meaningfulValue($product[$field] ?? null);
            if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
                $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
                if (in_array($scheme, ['http', 'https'], true) && self::isAllowedImageHost($host)) {
                    return $url;
                }
            }
        }

        return null;
    }

    private static function isAllowedImageHost(string $host): bool
    {
        foreach (['mustakshif.com', 'openfoodfacts.org', 'amazonaws.com'] as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }

    private static function meaningfulValue(mixed $value): ?string
    {
        $value = self::text($value);
        if ($value === '' || in_array(mb_strtolower($value), ['null', 'n/a', 'na', 'none', '-'], true)) {
            return null;
        }

        return $value;
    }

    private static function limited(?string $value, int $limit): ?string
    {
        return $value === null ? null : Str::limit($value, $limit, '');
    }

    private static function text(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
