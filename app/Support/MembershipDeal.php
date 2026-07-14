<?php

namespace App\Support;

final class MembershipDeal
{
    public static function fromRequest(array $data, mixed $tier): array
    {
        $deals = is_array($data['deals'] ?? null) ? $data['deals'] : [];

        if ($deals === [] && self::value($data, ['deal_title']) !== '') {
            $deals[] = [
                'title' => $data['deal_title'] ?? '',
                'description' => $data['deal_description'] ?? '',
                'code' => $data['deal_code'] ?? '',
                'expiry' => $data['deal_expiry'] ?? '',
            ];
        }

        return self::normalise($deals, $tier);
    }

    public static function fromRecord(array $record, mixed $tier): array
    {
        $deals = is_array($record['Deals'] ?? null) ? $record['Deals'] : [];

        if ($deals === [] && self::value($record, ['DealTitle', 'deal_title']) !== '') {
            $deals[] = [
                'Title' => $record['DealTitle'] ?? $record['deal_title'] ?? '',
                'Description' => $record['DealDescription'] ?? $record['deal_description'] ?? '',
                'Code' => $record['DealCode'] ?? $record['deal_code'] ?? '',
                'Expiry' => $record['DealExpiry'] ?? $record['deal_expiry'] ?? '',
            ];
        }

        return self::normalise($deals, $tier);
    }

    public static function applyToRecord(array &$record, array $deals, mixed $tier): void
    {
        $deals = self::normalise($deals, $tier);

        unset(
            $record['Deals'],
            $record['DealTitle'],
            $record['DealDescription'],
            $record['DealCode'],
            $record['DealExpiry'],
            $record['deal_title'],
            $record['deal_description'],
            $record['deal_code'],
            $record['deal_expiry']
        );

        if ($deals === []) {
            return;
        }

        $record['Deals'] = $deals;
        $first = $deals[0];
        $record['DealTitle'] = $first['Title'];

        foreach ([
            'Description' => 'DealDescription',
            'Code' => 'DealCode',
            'Expiry' => 'DealExpiry',
        ] as $dealKey => $recordKey) {
            if (isset($first[$dealKey])) {
                $record[$recordKey] = $first[$dealKey];
            }
        }
    }

    public static function normalise(array $deals, mixed $tier): array
    {
        $limit = MembershipTier::dealLimit($tier);
        if ($limit === 0) {
            return [];
        }

        $normalised = [];
        foreach ($deals as $deal) {
            if (! is_array($deal)) {
                continue;
            }

            $title = self::value($deal, ['Title', 'title', 'deal_title']);
            if ($title === '') {
                continue;
            }

            $item = ['Title' => $title];
            foreach ([
                'Description' => ['Description', 'description', 'deal_description'],
                'Code' => ['Code', 'code', 'deal_code'],
                'Expiry' => ['Expiry', 'expiry', 'deal_expiry'],
            ] as $canonicalKey => $keys) {
                $value = self::value($deal, $keys);
                if ($value !== '') {
                    $item[$canonicalKey] = $value;
                }
            }

            $normalised[] = $item;
            if (count($normalised) === $limit) {
                break;
            }
        }

        return $normalised;
    }

    private static function value(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}
