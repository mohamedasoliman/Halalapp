<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile API version policy
    |--------------------------------------------------------------------------
    |
    | Leave enforcement disabled until the corresponding release is available
    | in both app stores. Once enabled, requests must provide X-App-Version and
    | meet the configured semantic-version floor.
    |
    */
    'require_version' => env('MOBILE_API_REQUIRE_VERSION', false),
    'minimum_version' => env('MOBILE_API_MIN_VERSION'),
    'legacy_catalogue_enabled' => env('MOBILE_API_LEGACY_CATALOGUE_ENABLED', true),
];
