<?php

namespace App\Services;

use Illuminate\Support\Collection;

class RequestRecipientService
{
    public function collect(Collection $requests): Collection
    {
        $requests->each(fn ($request) => $request->loadMissing('watchers'));

        return $requests
            ->flatMap(fn ($request) => collect([$request->user_email])->merge($request->watchers->pluck('user_email')))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $this->isEligible($email))
            ->unique()
            ->values();
    }

    public function isEligible(?string $email): bool
    {
        $normalized = strtolower(trim((string) $email));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            && ! str_ends_with($normalized, '@halalkiwi.com');
    }
}
