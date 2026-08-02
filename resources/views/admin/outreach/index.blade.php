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
                                <div class="page-header-title">
                                    <h4>Manufacturer Outreach</h4>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                @unless($outreachEnabled)
                                    <div class="alert alert-warning">
                                        <strong>Sending is disabled.</strong>
                                        Drafts can be prepared and reviewed, but no manufacturer email can be queued until SMTP, SPF, DKIM and DMARC are verified and <code>OUTREACH_ENABLED=true</code>.
                                    </div>
                                @endunless

                                <div class="row">
                                    @foreach([
                                        'Contacts needed' => $stats['contacts_needed'],
                                        'Ready requests' => $stats['ready_requests'],
                                        'Drafts' => $stats['drafts'],
                                        'Queued' => $stats['queued'],
                                        'Sending' => $stats['sending'],
                                        'Uncertain' => $stats['uncertain'],
                                        'Sent' => $stats['sent'],
                                        'Failed' => $stats['failed'],
                                    ] as $label => $value)
                                        <div class="col-md-2 col-sm-4">
                                            <div class="card"><div class="card-block text-center"><h3>{{ $value }}</h3><span>{{ $label }}</span></div></div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="card">
                                    <div class="card-block d-flex align-items-center flex-wrap outreach-actions">
                                        <form action="{{ route('outreach.prepare') }}" method="POST" onsubmit="return confirm('Prepare contact-research records and email drafts? No emails will be sent.')">
                                            @csrf
                                            <button type="submit" class="btn btn-primary">Prepare Research &amp; Drafts</button>
                                        </form>
                                        <a href="{{ route('brands.index', ['research' => 'pending']) }}" class="btn btn-info">Research Missing Contacts</a>
                                        <a href="{{ route('outreach.index') }}" class="btn btn-secondary">All</a>
                                        @foreach(['draft', 'queued', 'sending', 'uncertain', 'sent', 'failed', 'cancelled'] as $status)
                                            <a href="{{ route('outreach.index', ['status' => $status]) }}" class="btn btn-secondary">{{ ucfirst($status) }}</a>
                                        @endforeach
                                    </div>
                                </div>

                                <form id="queue-form" action="{{ route('outreach.queue') }}" method="POST" onsubmit="return confirm('Queue the selected, reviewed manufacturer emails?')">
                                    @csrf
                                </form>

                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Outreach Batches</h5>
                                        <button type="submit" form="queue-form" class="btn btn-success" {{ $outreachEnabled ? '' : 'disabled' }}>Queue Selected Drafts</button>
                                    </div>
                                    <div class="card-block">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Select</th>
                                                        <th>Reference</th>
                                                        <th>Brand / Recipient</th>
                                                        <th>Products</th>
                                                        <th>Type</th>
                                                        <th>Status</th>
                                                        <th>Timing / Error</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($batches as $batch)
                                                        <tr>
                                                            <td>
                                                                @if($batch->status === 'draft')
                                                                    <input type="checkbox" name="batch_ids[]" value="{{ $batch->id }}" form="queue-form" aria-label="Select {{ $batch->reference }}">
                                                                @endif
                                                            </td>
                                                            <td><strong>{{ $batch->reference }}</strong><br><small>{{ $batch->created_at?->format('Y-m-d H:i') }}</small></td>
                                                            <td>{{ $batch->brand->name }}<br><small>{{ $batch->recipient_email }}</small></td>
                                                            <td>
                                                                @foreach($batch->products as $product)
                                                                    <div>{{ $product['name'] }} <small>({{ $product['barcode'] }})</small></div>
                                                                @endforeach
                                                                @if($batch->kind === 'clarification')
                                                                    <details class="mt-2">
                                                                        <summary>Review approved subject and body</summary>
                                                                        <div class="mt-2"><strong>Subject:</strong> {{ $batch->subject }}</div>
                                                                        <pre class="mt-2 mb-0" style="white-space: pre-wrap">{{ $batch->message_body }}</pre>
                                                                        <small>Replying to communication #{{ $batch->source_communication_id }}</small>
                                                                    </details>
                                                                @endif
                                                            </td>
                                                            <td>{{ ucfirst(str_replace('_', ' ', $batch->kind)) }}{{ $batch->follow_up_number ? ' #'.$batch->follow_up_number : '' }}</td>
                                                            <td><span class="badge badge-{{ $batch->status }}">{{ ucfirst($batch->status) }}</span></td>
                                                            <td>
                                                                @if($batch->sent_at) Sent {{ $batch->sent_at->format('Y-m-d H:i') }}
                                                                @elseif($batch->scheduled_at) Scheduled {{ $batch->scheduled_at->format('Y-m-d H:i') }}
                                                                @else -
                                                                @endif
                                                                @if($batch->error)<div class="text-danger mt-1">{{ $batch->error }}</div>@endif
                                                            </td>
                                                            <td>
                                                                @if(in_array($batch->status, ['draft', 'queued'], true))
                                                                    <form action="{{ route('outreach.cancel', $batch) }}" method="POST" onsubmit="return confirm('Cancel this outreach batch?')">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                                                    </form>
                                                                @elseif($batch->status === 'failed')
                                                                    <form action="{{ route('outreach.retry', $batch) }}" method="POST">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-sm btn-warning">Return to Draft</button>
                                                                    </form>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="8" class="text-center">No outreach batches yet. Prepare drafts first.</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        {{ $batches->links() }}
                                    </div>
                                </div>
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
    .outreach-actions { gap: 8px; }
    .badge { padding: 4px 8px; border-radius: 4px; color: #fff; }
    .badge-draft { background: #6c757d; }
    .badge-queued { background: #17a2b8; }
    .badge-sending { background: #0069d9; }
    .badge-uncertain { background: #fd7e14; }
    .badge-sent { background: #28a745; }
    .badge-failed { background: #dc3545; }
    .badge-cancelled { background: #343a40; }
</style>
@endpush
