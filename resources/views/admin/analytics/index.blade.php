@extends('admin.layouts.app')

@section('content')
    <div class="pcoded-main-container">
        @include('admin.include.sidebar')
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="page-header">
                                <div class="page-header-title"><h4>Halal Kiwi Analytics</h4></div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Analytics</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @if(!$ready)
                                    <div class="alert alert-info">
                                        Analytics storage is ready in the code but its database migration has not run yet.
                                    </div>
                                @else
                                    <div class="analytics-toolbar">
                                        <div>
                                            <h5 class="mb-1">App performance and partner outcomes</h5>
                                            <small class="text-muted">
                                                Anonymous first-party analytics. Raw events are retained for 90 days; daily totals remain available long term.
                                            </small>
                                        </div>
                                        <form method="GET" action="{{ route('analytics.index') }}" class="date-filter">
                                            <div>
                                                <label for="start">From</label>
                                                <input id="start" type="date" name="start" value="{{ $start->toDateString() }}" class="form-control">
                                            </div>
                                            <div>
                                                <label for="end">To</label>
                                                <input id="end" type="date" name="end" value="{{ $end->toDateString() }}" class="form-control">
                                            </div>
                                            <button class="btn btn-primary" type="submit"><i class="ti-filter"></i> Apply</button>
                                        </form>
                                    </div>

                                    <div class="metric-grid">
                                        <div class="metric-card">
                                            <span class="metric-label">Anonymous devices</span>
                                            <strong>{{ number_format($uniqueDevices) }}</strong>
                                            <small>Within raw-data retention</small>
                                        </div>
                                        <div class="metric-card">
                                            <span class="metric-label">Sessions</span>
                                            <strong>{{ number_format($sessions) }}</strong>
                                            <small>{{ number_format($appLaunches) }} app launches</small>
                                        </div>
                                        <div class="metric-card">
                                            <span class="metric-label">Profile views</span>
                                            <strong>{{ number_format($profileViews) }}</strong>
                                            <small>Products, mosques and partners</small>
                                        </div>
                                        <div class="metric-card metric-card-accent">
                                            <span class="metric-label">Partner actions</span>
                                            <strong>{{ number_format($commercialActions) }}</strong>
                                            <small>Calls, directions, menus and more</small>
                                        </div>
                                        <div class="metric-card">
                                            <span class="metric-label">All events</span>
                                            <strong>{{ number_format($totalEvents) }}</strong>
                                            <small>{{ number_format($rawEventCount) }} raw rows retained</small>
                                        </div>
                                    </div>

                                    <div class="analytics-grid analytics-grid-main">
                                        <section class="analytics-panel">
                                            <div class="panel-heading">
                                                <div>
                                                    <h5>Engagement trend</h5>
                                                    <small>All meaningful app events by day</small>
                                                </div>
                                            </div>
                                            @if($trend->isEmpty())
                                                <div class="empty-analytics">Data will appear after users install the analytics-enabled app.</div>
                                            @else
                                                <div id="analytics-trend" class="analytics-chart" aria-label="Daily engagement chart"></div>
                                            @endif
                                        </section>

                                        <section class="analytics-panel">
                                            <div class="panel-heading"><h5>Feature usage</h5></div>
                                            @php($maxFeature = max(1, (int) $featureBreakdown->max()))
                                            <div class="feature-bars">
                                                @foreach($featureBreakdown as $feature => $total)
                                                    <div class="feature-row">
                                                        <div class="feature-meta">
                                                            <span>{{ $feature }}</span>
                                                            <strong>{{ number_format($total) }}</strong>
                                                        </div>
                                                        <div class="feature-track">
                                                            <span style="width: {{ max(2, round(($total / $maxFeature) * 100, 1)) }}%"></span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </section>
                                    </div>

                                    <section class="analytics-panel mt-3">
                                        <div class="panel-heading">
                                            <div>
                                                <h5>Restaurant and business performance</h5>
                                                <small>Select a partner to view or print their complete report.</small>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle analytics-table">
                                                <thead>
                                                    <tr>
                                                        <th>Partner</th>
                                                        <th>Type</th>
                                                        <th>Impressions</th>
                                                        <th>Profile views</th>
                                                        <th>Sponsored clicks</th>
                                                        <th>Actions</th>
                                                        <th>Profile conversion</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($partners as $partner)
                                                        @php($conversion = $partner->impressions > 0 ? round(($partner->profile_views / $partner->impressions) * 100, 1) : 0)
                                                        <tr>
                                                            <td><strong>{{ $partner->entity_label ?: $partner->entity_key }}</strong></td>
                                                            <td><span class="type-badge">{{ ucfirst($partner->entity_type) }}</span></td>
                                                            <td>{{ number_format($partner->impressions) }}</td>
                                                            <td>{{ number_format($partner->profile_views) }}</td>
                                                            <td>{{ number_format($partner->sponsored_clicks) }}</td>
                                                            <td>{{ number_format($partner->actions) }}</td>
                                                            <td>{{ $conversion }}%</td>
                                                            <td class="text-end">
                                                                <a class="btn btn-sm btn-outline-primary"
                                                                   href="{{ route('analytics.partner', ['type' => $partner->entity_type, 'key' => $partner->entity_key, 'start' => $start->toDateString(), 'end' => $end->toDateString()]) }}">
                                                                    View report
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="8" class="text-center text-muted py-4">Partner activity will appear here after the new app release begins collecting events.</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>

                                    <div class="analytics-grid mt-3">
                                        <section class="analytics-panel">
                                            <div class="panel-heading"><h5>Top events</h5></div>
                                            <div class="table-responsive">
                                                <table class="table analytics-table mb-0">
                                                    <thead><tr><th>Event</th><th class="text-end">Total</th></tr></thead>
                                                    <tbody>
                                                        @forelse($topEvents as $event)
                                                            <tr>
                                                                <td>{{ ucwords(str_replace('_', ' ', $event->event_name)) }}</td>
                                                                <td class="text-end"><strong>{{ number_format($event->total) }}</strong></td>
                                                            </tr>
                                                        @empty
                                                            <tr><td colspan="2" class="text-muted">No events in this period.</td></tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>

                                        <section class="analytics-panel health-panel">
                                            <div class="panel-heading"><h5>Collection health</h5></div>
                                            <dl>
                                                <div><dt>Latest event</dt><dd>{{ $latestEventAt ? \Carbon\Carbon::parse($latestEventAt)->diffForHumans() : 'Waiting for first event' }}</dd></div>
                                                <div><dt>Raw retention</dt><dd>90 days</dd></div>
                                                <div><dt>Long-term totals</dt><dd>Daily summaries</dd></div>
                                                <div><dt>User identification</dt><dd>Anonymous installation ID</dd></div>
                                                <div><dt>Firebase</dt><dd>Continues in parallel</dd></div>
                                            </dl>
                                        </section>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .analytics-toolbar { align-items: end; background: #fff; border: 1px solid #e5e9ed; border-radius: 8px; display: flex; gap: 24px; justify-content: space-between; padding: 18px 20px; }
    .date-filter { align-items: end; display: flex; flex-wrap: wrap; gap: 10px; }
    .date-filter label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
    .metric-grid { display: grid; gap: 12px; grid-template-columns: repeat(5, minmax(150px, 1fr)); margin-top: 14px; }
    .metric-card { background: #fff; border: 1px solid #e5e9ed; border-radius: 8px; min-height: 118px; padding: 16px; }
    .metric-card-accent { border-top: 3px solid #024543; }
    .metric-card strong { color: #17212b; display: block; font-size: 26px; line-height: 1.2; margin: 8px 0 4px; }
    .metric-card small, .metric-label { color: #718096; font-size: 12px; }
    .metric-label { font-weight: 700; text-transform: uppercase; }
    .analytics-grid { display: grid; gap: 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .analytics-grid-main { grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr); margin-top: 14px; }
    .analytics-panel { background: #fff; border: 1px solid #e5e9ed; border-radius: 8px; padding: 18px; }
    .panel-heading { align-items: center; display: flex; justify-content: space-between; margin-bottom: 16px; }
    .panel-heading h5 { margin: 0; }
    .panel-heading small { color: #718096; }
    .analytics-chart { height: 280px; }
    .empty-analytics { align-items: center; color: #718096; display: flex; height: 250px; justify-content: center; text-align: center; }
    .feature-row { margin-bottom: 15px; }
    .feature-meta { display: flex; font-size: 13px; justify-content: space-between; margin-bottom: 5px; }
    .feature-track { background: #edf2f3; border-radius: 4px; height: 8px; overflow: hidden; }
    .feature-track span { background: #024543; border-radius: 4px; display: block; height: 100%; }
    .analytics-table th { color: #718096; font-size: 12px; font-weight: 700; text-transform: uppercase; }
    .type-badge { background: #e4f1ef; border-radius: 4px; color: #024543; font-size: 11px; font-weight: 700; padding: 4px 7px; }
    .health-panel dl { margin: 0; }
    .health-panel dl div { border-bottom: 1px solid #edf0f2; display: flex; justify-content: space-between; padding: 11px 0; }
    .health-panel dl div:last-child { border-bottom: 0; }
    .health-panel dt { color: #718096; font-weight: 500; }
    .health-panel dd { color: #17212b; font-weight: 600; margin: 0; text-align: right; }
    @media (max-width: 1100px) { .metric-grid { grid-template-columns: repeat(3, 1fr); } .analytics-grid-main { grid-template-columns: 1fr; } }
    @media (max-width: 700px) { .analytics-toolbar { align-items: stretch; flex-direction: column; } .metric-grid, .analytics-grid { grid-template-columns: 1fr; } .date-filter > div { flex: 1; } }
</style>
@endpush

@if($ready && !$trend->isEmpty())
@push('scripts')
<script src="{{ asset('assets/bower_components/raphael/raphael.min.js') }}"></script>
<script src="{{ asset('assets/bower_components/morris.js/morris.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Morris.Line({
            element: 'analytics-trend',
            data: @json($trend),
            xkey: 'date',
            ykeys: ['events'],
            labels: ['Events'],
            lineColors: ['#024543'],
            pointFillColors: ['#d0ae00'],
            pointStrokeColors: ['#024543'],
            resize: true,
            smooth: false,
            hideHover: 'auto'
        });
    });
</script>
@endpush
@endif
