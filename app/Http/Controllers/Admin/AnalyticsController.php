<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        [$start, $end] = $this->dateRange($request);
        $ready = Schema::hasTable('analytics_daily_summaries') &&
            Schema::hasTable('analytics_events');

        if (! $ready) {
            return view('admin.analytics.index', compact('start', 'end', 'ready'));
        }

        $summary = $this->summaryQuery($start, $end);
        $eventCounts = (clone $summary)
            ->selectRaw('event_name, SUM(event_count) AS total')
            ->groupBy('event_name')
            ->orderByDesc('total')
            ->get();

        $totalEvents = (int) $eventCounts->sum('total');
        $appLaunches = $this->countEvents($eventCounts, ['app_launched']);
        $sessions = $this->countEvents($eventCounts, ['session_start_custom']);
        $profileViews = $this->countEvents($eventCounts, [
            'business_page_viewed',
            'restaurant_details_viewed',
            'mosque_details_viewed',
            'product_details_viewed',
        ]);
        $commercialActions = $this->countEvents($eventCounts, [
            'business_action_clicked',
            'restaurant_action_clicked',
        ]);

        $uniqueDevices = AnalyticsEvent::whereBetween('occurred_at', [
            $start->copy()->startOfDay(),
            $end->copy()->endOfDay(),
        ])->distinct('anonymous_id')->count('anonymous_id');

        $trend = (clone $summary)
            ->selectRaw('summary_date, SUM(event_count) AS total')
            ->groupBy('summary_date')
            ->orderBy('summary_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->summary_date)->format('Y-m-d'),
                'events' => (int) $row->total,
            ]);

        $featureBreakdown = $this->featureBreakdown($eventCounts);
        $topEvents = $eventCounts->take(12);
        $partners = $this->partnerRows($start, $end)->take(30);
        $latestEventAt = AnalyticsEvent::max('occurred_at');
        $rawEventCount = AnalyticsEvent::count();

        return view('admin.analytics.index', compact(
            'start',
            'end',
            'ready',
            'totalEvents',
            'appLaunches',
            'sessions',
            'profileViews',
            'commercialActions',
            'uniqueDevices',
            'trend',
            'featureBreakdown',
            'topEvents',
            'partners',
            'latestEventAt',
            'rawEventCount'
        ));
    }

    public function partner(Request $request, string $type, string $key)
    {
        abort_unless(in_array($type, ['business', 'restaurant'], true), 404);
        [$start, $end] = $this->dateRange($request);

        $query = $this->summaryQuery($start, $end)
            ->where('entity_type', $type)
            ->where('entity_key', $key);

        $label = (clone $query)->where('entity_label', '!=', '')->value('entity_label');
        abort_if($label === null, 404);

        $metrics = $this->partnerMetrics($query, $type);
        $trend = (clone $query)
            ->selectRaw('summary_date, SUM(event_count) AS total')
            ->groupBy('summary_date')
            ->orderBy('summary_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->summary_date)->format('Y-m-d'),
                'events' => (int) $row->total,
            ]);
        $actions = (clone $query)
            ->where('dimension_key', 'action')
            ->selectRaw('dimension_value, SUM(event_count) AS total')
            ->groupBy('dimension_value')
            ->orderByDesc('total')
            ->get();

        return view('admin.analytics.partner', compact(
            'start',
            'end',
            'type',
            'key',
            'label',
            'metrics',
            'trend',
            'actions'
        ));
    }

    public function exportPartner(Request $request, string $type, string $key): StreamedResponse
    {
        abort_unless(in_array($type, ['business', 'restaurant'], true), 404);
        [$start, $end] = $this->dateRange($request);
        $query = $this->summaryQuery($start, $end)
            ->where('entity_type', $type)
            ->where('entity_key', $key);
        $label = (clone $query)->where('entity_label', '!=', '')->value('entity_label');
        abort_if($label === null, 404);
        $metrics = $this->partnerMetrics($query, $type);

        return response()->streamDownload(function () use ($label, $start, $end, $metrics): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Halal Kiwi Partner Analytics']);
            fputcsv($handle, ['Partner', $label]);
            fputcsv($handle, ['Period', $start->toDateString().' to '.$end->toDateString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Metric', 'Total']);
            foreach ($metrics as $metric => $value) {
                fputcsv($handle, [ucwords(str_replace('_', ' ', $metric)), $value]);
            }
            fclose($handle);
        }, $key.'-halal-kiwi-analytics.csv', ['Content-Type' => 'text/csv']);
    }

    private function dateRange(Request $request): array
    {
        try {
            $end = Carbon::createFromFormat('Y-m-d', $request->string('end')->toString())->startOfDay();
        } catch (\Throwable) {
            $end = today();
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d', $request->string('start')->toString())->startOfDay();
        } catch (\Throwable) {
            $start = $end->copy()->subDays(29);
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }
        if ($start->diffInDays($end) > 365) {
            $start = $end->copy()->subDays(365);
        }

        return [$start, $end];
    }

    private function summaryQuery(Carbon $start, Carbon $end): Builder
    {
        return AnalyticsDailySummary::query()
            ->whereBetween('summary_date', [$start->toDateString(), $end->toDateString()]);
    }

    private function countEvents(Collection $eventCounts, array $eventNames): int
    {
        return (int) $eventCounts
            ->whereIn('event_name', $eventNames)
            ->sum('total');
    }

    private function featureBreakdown(Collection $eventCounts): Collection
    {
        $features = [
            'Products & Scanner' => 0,
            'Restaurants' => 0,
            'Businesses' => 0,
            'Mosques' => 0,
            'Quran & Qibla' => 0,
            'Notifications' => 0,
            'Forms & Community' => 0,
            'Other' => 0,
        ];

        foreach ($eventCounts as $event) {
            $name = $event->event_name;
            $group = match (true) {
                str_contains($name, 'product'), str_contains($name, 'barcode'),
                str_contains($name, 'halal_'), str_contains($name, 'e_number') => 'Products & Scanner',
                str_contains($name, 'restaurant') => 'Restaurants',
                str_contains($name, 'business') => 'Businesses',
                str_contains($name, 'mosque'), str_contains($name, 'masjid') => 'Mosques',
                str_contains($name, 'quran'), str_contains($name, 'surah'),
                str_contains($name, 'verse'), str_contains($name, 'qibla') => 'Quran & Qibla',
                str_contains($name, 'notification') => 'Notifications',
                str_contains($name, 'form'), str_contains($name, 'fatwa'),
                str_contains($name, 'event') => 'Forms & Community',
                default => 'Other',
            };
            $features[$group] += (int) $event->total;
        }

        return collect($features)->sortDesc();
    }

    private function partnerRows(Carbon $start, Carbon $end): Collection
    {
        return AnalyticsDailySummary::query()
            ->whereBetween('summary_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('entity_type', ['business', 'restaurant'])
            ->select([
                'entity_type',
                'entity_key',
                DB::raw('MAX(entity_label) AS entity_label'),
                DB::raw("SUM(CASE WHEN event_name IN ('business_page_viewed','restaurant_details_viewed') THEN event_count ELSE 0 END) AS profile_views"),
                DB::raw("SUM(CASE WHEN event_name IN ('business_featured_impression','business_directory_impression','restaurant_featured_impression','restaurant_directory_impression') THEN event_count ELSE 0 END) AS impressions"),
                DB::raw("SUM(CASE WHEN event_name IN ('business_action_clicked','restaurant_action_clicked') THEN event_count ELSE 0 END) AS actions"),
                DB::raw('SUM(event_count) AS total_events'),
            ])
            ->groupBy('entity_type', 'entity_key')
            ->orderByDesc('actions')
            ->orderByDesc('profile_views')
            ->get();
    }

    private function partnerMetrics(Builder $query, string $type): array
    {
        $viewEvent = $type === 'business' ? 'business_page_viewed' : 'restaurant_details_viewed';
        $actionEvent = $type === 'business' ? 'business_action_clicked' : 'restaurant_action_clicked';
        $impressionEvents = $type === 'business'
            ? ['business_featured_impression', 'business_directory_impression']
            : ['restaurant_featured_impression', 'restaurant_directory_impression'];

        $profileViews = (int) (clone $query)->where('event_name', $viewEvent)->sum('event_count');
        $impressions = (int) (clone $query)->whereIn('event_name', $impressionEvents)->sum('event_count');
        $actions = (int) (clone $query)->where('event_name', $actionEvent)->sum('event_count');

        $actionCount = fn (array $names): int => (int) (clone $query)
            ->where('event_name', $actionEvent)
            ->where('dimension_key', 'action')
            ->whereIn('dimension_value', $names)
            ->sum('event_count');

        return [
            'impressions' => $impressions,
            'profile_views' => $profileViews,
            'engagements' => $actions,
            'calls' => $actionCount(['call']),
            'directions' => $actionCount(['directions']),
            'website_visits' => $actionCount(['website']),
            'menu_opens' => $actionCount(['menu', 'services']),
            'enquiries' => $actionCount(['enquire', 'email', 'sms']),
            'social_clicks' => $actionCount(['instagram', 'facebook']),
            'profile_conversion' => $impressions > 0
                ? round(($profileViews / $impressions) * 100, 1)
                : 0,
            'action_conversion' => $profileViews > 0
                ? round(($actions / $profileViews) * 100, 1)
                : 0,
        ];
    }
}
