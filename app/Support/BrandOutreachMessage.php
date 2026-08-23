<?php

namespace App\Support;

final class BrandOutreachMessage
{
    public static function usesCustomBody(string $kind, ?string $body): bool
    {
        return $kind === 'clarification'
            || ($kind === 'initial' && trim((string) $body) !== '');
    }
}
