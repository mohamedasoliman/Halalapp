<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnalyticsIngestionController extends Controller
{
    private const ALLOWED_EVENTS = [
        'address_geocoding_attempt',
        'app_error',
        'app_launched',
        'app_opened_from_notification',
        'app_resumed',
        'barcode_scanned',
        'barcode_screen_visited',
        'boycott_list_interaction',
        'boycott_screen_visited',
        'business_action_clicked',
        'business_category_screen_visited',
        'business_directory_impression',
        'business_directory_used',
        'business_featured_impression',
        'business_listing_interest',
        'business_page_viewed',
        'business_profile_selected',
        'business_sticky_ad_clicked',
        'business_sticky_ad_dismissed',
        'business_sticky_ad_impression',
        'contact_form_submitted',
        'dark_mode_announcement_shown',
        'directory_screen_visited',
        'e_number_details_viewed',
        'e_number_screen_visited',
        'e_number_searched',
        'events_screen_visited',
        'fatwa_hub_screen_visited',
        'fatwa_interaction',
        'fatwa_question_submitted',
        'feature_tour_completed',
        'feature_tour_skipped',
        'feature_tour_started',
        'halal_filter_used',
        'halal_list_screen_visited',
        'home_screen_visited',
        'home_sticky_ad_clicked',
        'home_sticky_ad_dismissed',
        'home_sticky_ad_impression',
        'kiwisaver_form_screen_visited',
        'kiwisaver_form_submitted',
        'location_permission_changed',
        'masjids_screen_visited',
        'mosque_details_viewed',
        'mosque_directions_opened',
        'mosque_reported',
        'mosque_search_completed',
        'mosque_search_mode_changed',
        'notification_action_clicked',
        'notification_dialog_shown',
        'notification_permission_changed',
        'onboarding_completed',
        'product_assessment_guidelines_opened',
        'product_details_viewed',
        'product_reported',
        'product_scan_attempt',
        'product_shared',
        'product_status_filter_used',
        'qibla_direction_used',
        'qibla_screen_visited',
        'quran_feature_used',
        'quran_screen_visited',
        'quran_verse_bookmarked',
        'quran_verse_copied',
        'quran_verse_shared',
        'restaurant_action_clicked',
        'restaurant_details_viewed',
        'restaurant_directory_impression',
        'restaurant_featured_impression',
        'restaurant_reported',
        'restaurant_search_performed',
        'restaurant_sticky_ad_clicked',
        'restaurant_sticky_ad_dismissed',
        'restaurant_sticky_ad_impression',
        'restaurants_screen_visited',
        'search_performed',
        'session_start_custom',
        'surah_opened',
    ];

    private const BLOCKED_PROPERTIES = [
        'address',
        'barcode',
        'barcode_key',
        'email',
        'phone',
        'query',
        'search_query',
        'latitude',
        'longitude',
        'location',
        'token',
        'notification_text',
        'error_message',
        'timestamp',
        'launch_time',
        'session_id',
    ];

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('analytics_events') ||
            ! Schema::hasTable('analytics_daily_summaries')) {
            return response()->json(['message' => 'Analytics storage is not ready.'], 503);
        }

        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.event_uuid' => ['required', 'uuid'],
            'events.*.anonymous_id' => ['required', 'uuid'],
            'events.*.session_id' => ['required', 'uuid'],
            'events.*.event_name' => ['required', Rule::in(self::ALLOWED_EVENTS)],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.platform' => [
                'nullable',
                Rule::in(['android', 'ios', 'web', 'macos', 'windows', 'linux', 'fuchsia', 'unknown']),
            ],
            'events.*.app_version' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z.+_-]+$/'],
            'events.*.properties' => ['nullable', 'array', 'max:30'],
        ]);

        $accepted = 0;
        $duplicates = 0;

        DB::transaction(function () use ($validated, &$accepted, &$duplicates): void {
            foreach ($validated['events'] as $payload) {
                $properties = $this->sanitiseProperties($payload['properties'] ?? []);
                [$entityType, $entityKey, $entityLabel] = $this->entity($properties);
                [$dimensionKey, $dimensionValue] = $this->dimension($properties);
                $occurredAt = $this->safeOccurredAt($payload['occurred_at']);

                $event = AnalyticsEvent::firstOrCreate(
                    ['event_uuid' => $payload['event_uuid']],
                    [
                        'anonymous_id' => hash('sha256', $payload['anonymous_id']),
                        'session_id' => hash('sha256', $payload['session_id']),
                        'event_name' => $payload['event_name'],
                        'entity_type' => $entityType,
                        'entity_key' => $entityKey,
                        'entity_label' => $entityLabel,
                        'properties' => $properties ?: null,
                        'platform' => Str::limit($payload['platform'] ?? 'unknown', 20, ''),
                        'app_version' => Str::limit($payload['app_version'] ?? 'unknown', 30, ''),
                        'occurred_at' => $occurredAt,
                    ]
                );

                if (! $event->wasRecentlyCreated) {
                    $duplicates++;

                    continue;
                }

                $accepted++;
                $summary = AnalyticsDailySummary::firstOrCreate([
                    'summary_date' => $occurredAt->toDateString(),
                    'event_name' => $payload['event_name'],
                    'entity_type' => $entityType,
                    'entity_key' => $entityKey,
                    'dimension_key' => $dimensionKey,
                    'dimension_value' => $dimensionValue,
                ], [
                    'entity_label' => $entityLabel,
                    'event_count' => 0,
                ]);

                if ($entityLabel !== '' && $summary->entity_label !== $entityLabel) {
                    $summary->entity_label = $entityLabel;
                    $summary->save();
                }

                $summary->increment('event_count');
            }
        });

        return response()->json([
            'accepted' => $accepted,
            'duplicates' => $duplicates,
        ], 202);
    }

    private function sanitiseProperties(array $properties): array
    {
        $safe = [];

        foreach (array_slice($properties, 0, 30, true) as $key => $value) {
            $normalisedKey = Str::snake(Str::limit((string) $key, 50, ''));
            if ($normalisedKey === '' || in_array($normalisedKey, self::BLOCKED_PROPERTIES, true)) {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $safe[$normalisedKey] = $value;
            } elseif (is_string($value)) {
                $safe[$normalisedKey] = Str::limit(trim($value), 191, '');
            }
        }

        return $safe;
    }

    private function entity(array $properties): array
    {
        foreach ([
            'business_name' => 'business',
            'restaurant_name' => 'restaurant',
            'mosque_name' => 'mosque',
            'product_name' => 'product',
            'surah_name' => 'surah',
            'brand_name' => 'brand',
        ] as $property => $type) {
            $label = trim((string) ($properties[$property] ?? ''));
            if ($label !== '') {
                return [$type, Str::slug($label), Str::limit($label, 191, '')];
            }
        }

        return ['', '', ''];
    }

    private function dimension(array $properties): array
    {
        foreach ([
            'action',
            'source',
            'search_type',
            'category',
            'tier',
            'halal_status',
            'destination',
            'platform',
        ] as $key) {
            $value = trim((string) ($properties[$key] ?? ''));
            if ($value !== '') {
                return [$key, Str::limit($value, 191, '')];
            }
        }

        return ['', ''];
    }

    private function safeOccurredAt(string $value): Carbon
    {
        $occurredAt = Carbon::parse($value)->utc();
        $earliest = now()->subDays(7);
        $latest = now()->addMinutes(10);

        return $occurredAt->between($earliest, $latest) ? $occurredAt : now();
    }
}
