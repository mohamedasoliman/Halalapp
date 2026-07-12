@extends('admin.layouts.app')

@section('content')
    <div class="pcoded-main-container partner-report-page">
        @include('admin.include.sidebar')
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="page-header no-print">
                                <div class="page-header-title"><h4>Partner Analytics</h4></div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item"><a href="{{ route('analytics.index') }}">Analytics</a></li>
                                        <li class="breadcrumb-item"><a href="javascript:;">{{ $label }}</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                <header class="report-header">
                                    <div>
                                        <span class="report-brand">HALAL KIWI PARTNER REPORT</span>
                                        <h1>{{ $label }}</h1>
                                        <p>{{ ucfirst($type) }} performance from {{ $start->format('d M Y') }} to {{ $end->format('d M Y') }}</p>
                                    </div>
                                    <div class="report-actions no-print">
                                        <a href="{{ route('analytics.index', ['start' => $start->toDateString(), 'end' => $end->toDateString()]) }}" class="btn btn-outline-secondary">Back</a>
                                        <a href="{{ route('analytics.partner.export', ['type' => $type, 'key' => $key, 'start' => $start->toDateString(), 'end' => $end->toDateString()]) }}" class="btn btn-outline-primary"><i class="ti-download"></i> CSV</a>
                                        <button type="button" onclick="window.print()" class="btn btn-primary"><i class="ti-printer"></i> Print / Save PDF</button>
                                    </div>
                                </header>

                                <div class="partner-metrics">
                                    @foreach([
                                        'impressions' => 'Impressions',
                                        'profile_views' => 'Profile views',
                                        'sponsored_clicks' => 'Sponsored clicks',
                                        'engagements' => 'Customer actions',
                                        'calls' => 'Calls',
                                        'directions' => 'Directions',
                                        'website_visits' => 'Website visits',
                                        'menu_opens' => 'Menu / services',
                                        'enquiries' => 'Enquiries',
                                    ] as $metric => $labelText)
                                        <div class="partner-metric">
                                            <span>{{ $labelText }}</span>
                                            <strong>{{ number_format($metrics[$metric]) }}</strong>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="conversion-band">
                                    <div>
                                        <span>Impression to profile</span>
                                        <strong>{{ $metrics['profile_conversion'] }}%</strong>
                                    </div>
                                    <div>
                                        <span>Profile to customer action</span>
                                        <strong>{{ $metrics['action_conversion'] }}%</strong>
                                    </div>
                                    <p>These rates show how effectively visibility becomes genuine customer interest.</p>
                                </div>

                                <div class="report-grid">
                                    <section class="report-panel">
                                        <h5>Engagement over time</h5>
                                        @if($trend->isEmpty())
                                            <div class="report-empty">No activity recorded in this period.</div>
                                        @else
                                            <div id="partner-trend" class="partner-chart"></div>
                                        @endif
                                    </section>
                                    <section class="report-panel">
                                        <h5>Customer actions</h5>
                                        <table class="table mb-0">
                                            <tbody>
                                                @forelse($actions as $action)
                                                    <tr>
                                                        <td>{{ ucwords(str_replace('_', ' ', $action->dimension_value)) }}</td>
                                                        <td class="text-end"><strong>{{ number_format($action->total) }}</strong></td>
                                                    </tr>
                                                @empty
                                                    <tr><td class="text-muted">No customer actions recorded yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </section>
                                </div>

                                <footer class="report-footer">
                                    <strong>Halal Kiwi Muslim Business Network</strong>
                                    <span>Anonymous, aggregated customer engagement. No individual customers are identified.</span>
                                </footer>
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
    .report-header { align-items: center; background: #024543; border-radius: 8px; color: #fff; display: flex; justify-content: space-between; padding: 24px 28px; }
    .report-header h1 { color: #fff; font-size: 28px; margin: 6px 0; }
    .report-header p { color: rgba(255,255,255,.78); margin: 0; }
    .report-brand { color: #e6ca3c; font-size: 11px; font-weight: 800; }
    .report-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .partner-metrics { display: grid; gap: 12px; grid-template-columns: repeat(4, 1fr); margin-top: 14px; }
    .partner-metric { background: #fff; border: 1px solid #e5e9ed; border-radius: 8px; padding: 16px; }
    .partner-metric span { color: #718096; display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; }
    .partner-metric strong { color: #17212b; display: block; font-size: 25px; margin-top: 7px; }
    .conversion-band { align-items: center; background: #edf6f4; border: 1px solid #cfe4df; border-radius: 8px; display: grid; gap: 20px; grid-template-columns: 180px 220px 1fr; margin-top: 14px; padding: 17px 20px; }
    .conversion-band span { color: #4b6460; display: block; font-size: 12px; }
    .conversion-band strong { color: #024543; font-size: 23px; }
    .conversion-band p { color: #58706c; margin: 0; }
    .report-grid { display: grid; gap: 14px; grid-template-columns: minmax(0, 1.65fr) minmax(280px, 1fr); margin-top: 14px; }
    .report-panel { background: #fff; border: 1px solid #e5e9ed; border-radius: 8px; padding: 18px; }
    .report-panel h5 { margin-bottom: 16px; }
    .partner-chart { height: 280px; }
    .report-empty { align-items: center; color: #718096; display: flex; height: 250px; justify-content: center; }
    .report-footer { border-top: 1px solid #dfe5e7; color: #718096; display: flex; justify-content: space-between; margin-top: 24px; padding: 16px 4px; }
    .report-footer strong { color: #024543; }
    @media (max-width: 900px) { .report-header { align-items: flex-start; flex-direction: column; gap: 18px; } .partner-metrics { grid-template-columns: repeat(2, 1fr); } .conversion-band, .report-grid { grid-template-columns: 1fr; } }
    @media print {
        .no-print, .pcoded-navbar, .header-navbar { display: none !important; }
        .pcoded-main-container, .pcoded-wrapper, .pcoded-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .page-wrapper { padding: 0 !important; }
        body { background: #fff !important; }
        .report-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .partner-metric, .report-panel { break-inside: avoid; box-shadow: none; }
    }
</style>
@endpush

@if(!$trend->isEmpty())
@push('scripts')
<script src="{{ asset('assets/bower_components/raphael/raphael.min.js') }}"></script>
<script src="{{ asset('assets/bower_components/morris.js/morris.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Morris.Line({
            element: 'partner-trend',
            data: @json($trend),
            xkey: 'date',
            ykeys: ['events'],
            labels: ['Engagements'],
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
