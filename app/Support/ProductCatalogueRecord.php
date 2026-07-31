<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ProductCatalogueRecord
{
    private const PLACEHOLDER_IMAGE_HASHES = [
        '32f3fadb207279b98ebb201e5c71a64fee2236e33ebe96924f9fb0fa05202a67',
        'c7e8046208a5938b85790fda802f7b190e4c5daefa0db771f9921d89a0c7c660',
    ];

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
        if (! self::isSuitableProduct($productName, $category)
            || self::looksLikeBrandOnlyName($productName)
            || ($brand !== null && mb_strtolower($productName) === mb_strtolower($brand))) {
            return null;
        }

        return [
            'barcode' => $barcode,
            'product_name' => Str::limit($productName, 250, ''),
            'brand' => self::limited($brand, 250),
            'country' => self::limited(self::countryValue($product['origin'] ?? null), 250),
            'category' => self::limited($category, 250),
            'ingredient' => self::ingredientValue($product['ingredients'] ?? null),
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
        $category = self::limited(self::meaningfulValue($row['category'] ?? null), 250);
        if (! ProductBarcode::isValidGtin($barcode)
            || ! self::hasUsableName($name)
            || ! self::isSuitableProduct($name, $category)
            || self::looksLikeBrandOnlyName($name)
            || ($brand !== null && mb_strtolower($name) === mb_strtolower($brand))) {
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
            'category' => $category,
            'ingredient' => self::ingredientValue($row['ingredient'] ?? null),
            'product_image' => self::limited($image, 250),
        ];
    }

    public static function hasUsableName(?string $name): bool
    {
        $name = self::text($name);
        if (mb_strlen($name) < 3 || preg_match('/[\p{L}]{2}/u', $name) !== 1) {
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
            'no name',
            'product name',
            'sample product',
            'test product',
            'dummy product',
            'barcode',
            'easter',
            'christmas',
            'miscellaneous',
            'stabilité',
            'muas',
            '#name?',
            '#ref!',
            '#value!',
        ], true)) {
            return false;
        }

        if (preg_match('/^health star rating(?:\s+\d+(?:\.\d+)?)?$/iu', $normalized) === 1
            || preg_match('/^(?:test|dummy|sample|tes\d*)(?:\s+\d+)*$/iu', $normalized) === 1
            || preg_match('/https?:\/\/|www\.|\bbarcode\b/iu', $normalized) === 1) {
            return false;
        }

        return true;
    }

    private static function looksLikeBrandOnlyName(string $name): bool
    {
        return preg_match(
            '/^[\p{L}\p{M}.-]+(?:\s+[\p{L}\p{M}.-]+){0,2}[’\']s$/iu',
            trim($name)
        ) === 1;
    }

    public static function isUsableImageFile(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $details = @getimagesize($path);
        $hash = @hash_file('sha256', $path);

        return $details !== false
            && ($details[0] ?? 0) >= 20
            && ($details[1] ?? 0) >= 20
            && is_string($hash)
            && ! in_array($hash, self::PLACEHOLDER_IMAGE_HASHES, true);
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
            '/(?:\s*[-–—:(]\s*)?(?:
                (?:product\s+)?not\s+available
                |product\s+not\s+verified
                |ingredients?\s+(?:are\s+)?(?:missing|not\s+available|not\s+verified)
                |ingredients?\s+need(?:s)?\s+(?:verification|to\s+be\s+verified)
                |need\s+more\s+information
            )\)?\s*$/iux',
            '',
            $name
        ));

        return trim((string) preg_replace(
            '/(?:\s*[-–—:]\s*)?ingredients?\s+(?:are\s+)?(?:missing|not\s+available)$/iu',
            '',
            $name
        ));
    }

    private static function isSuitableProduct(string $name, ?string $category): bool
    {
        $normalizedCategory = mb_strtolower(trim((string) $category));
        if (in_array($normalizedCategory, [
            'household',
            'health & beauty',
            'cosmetic',
            'cosmetics',
            'dental care',
            'medicine',
            'skin care',
            'perfume fragrance cologne',
            'tobacco',
            'pharmacy',
            'pet food',
        ], true)) {
            return false;
        }

        return preg_match(
            '/\b(?:
                dishwashing(?:\s+liquid)?|dishwasher|laundry|detergent|fabric\s+softener|
                bleach|disinfectant|toilet\s+cleaner|floor\s+cleaner|surface\s+cleaner|
                glass\s+cleaner|(?:anti[- ]?bacterial\s+)?cleaner|air\s+freshener|
                insect(?:icide)?\s+spray|
                garbage\s+bags?|trash\s+bags?|paper\s+towels?|household\s+towels?|
                toilet\s+paper|facial\s+tissues?|
                shampoo|conditioner|body\s+wash|hand\s+wash|shower\s+gel|soap|
                toothpaste|toothbrush|mouthwash|dental\s+floss|deodorant|antiperspirant|
                perfume|cologne|eau\s+de\s+(?:parfum|toilette)|fragrance|
                lipstick|mascara|eyeliner|nail\s+(?:polish|base|coat|nurse)|hair\s+dye|
                hair\s+colou?r|
                sunscreen|sunblock|moisturi[sz]er|face\s+wash|facial\s+cleanser|
                hydrating\s+cleanser|moisture\s+bomb|sheet\s+mask|beauty\s+bar|
                lip\s+(?:balm|butter)|butter\s+lip|lotion|hand\s+cream|
                skin\s+(?:cream|therapy)|baby\s+wipes|
                napp(?:y|ies)|diapers?|sanitary\s+pads?|tampons?|condoms?|pregnancy\s+tests?|
                cat\s+food|dog\s+food|pet\s+food|cigarettes?|tobacco|vape|e-liquid|
                batter(?:y|ies)|light\s+bulbs?|kites?|palmer[’\']?s|old\s+spice|
                citrullus\s+lanatus|cera\s*ve|garnier|rimmel(?:\s+london)?|
                l[’\']?occitane|l[’\']?or[eé]al|listerine|
                dove\s+(?:glowing\s+beauty|sensitive)\s+bar
            )\b/iux',
            $name
        ) !== 1;
    }

    private static function ingredientValue(mixed $value): ?string
    {
        $ingredient = self::meaningfulValue($value);

        return $ingredient !== null && ! str_contains($ingredient, "\u{FFFD}")
            ? $ingredient
            : null;
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
                $basename = mb_strtolower(rawurldecode(basename((string) parse_url($url, PHP_URL_PATH))));
                if (in_array($scheme, ['http', 'https'], true)
                    && self::isAllowedImageHost($host)
                    && ! in_array($basename, [
                        'product.jpg',
                        'product.jpeg',
                        'product.png',
                        'placeholder.jpg',
                        'placeholder.jpeg',
                        'placeholder.png',
                        'no-image.jpg',
                        'no-image.png',
                    ], true)) {
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
