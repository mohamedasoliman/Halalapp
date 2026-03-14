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
                                    <h4>Prioritisation Requests</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Prioritisation</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                {{-- Status filter tabs --}}
                                <div class="card mb-3">
                                    <div class="card-block" style="padding: 10px 20px;">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('prioritisation.index') }}"
                                               class="btn btn-sm {{ $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                                                All <span class="badge bg-light text-dark">{{ $counts['all'] }}</span>
                                            </a>
                                            <a href="{{ route('prioritisation.index', ['status' => 'pending']) }}"
                                               class="btn btn-sm {{ $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning' }}">
                                                Pending <span class="badge bg-light text-dark">{{ $counts['pending'] }}</span>
                                            </a>
                                            <a href="{{ route('prioritisation.index', ['status' => 'ready_for_outreach']) }}"
                                               class="btn btn-sm {{ $statusFilter === 'ready_for_outreach' ? 'btn-info' : 'btn-outline-info' }}">
                                                Ready for Outreach <span class="badge bg-light text-dark">{{ $counts['ready_for_outreach'] }}</span>
                                            </a>
                                            <a href="{{ route('prioritisation.index', ['status' => 'contacted']) }}"
                                               class="btn btn-sm {{ $statusFilter === 'contacted' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                                                Contacted <span class="badge bg-light text-dark">{{ $counts['contacted'] }}</span>
                                            </a>
                                            <a href="{{ route('prioritisation.index', ['status' => 'ready_for_review']) }}"
                                               class="btn btn-sm {{ $statusFilter === 'ready_for_review' ? 'btn-danger' : 'btn-outline-danger' }}">
                                                Ready for Review <span class="badge bg-light text-dark">{{ $counts['ready_for_review'] }}</span>
                                            </a>
                                            <a href="{{ route('prioritisation.index', ['status' => 'resolved']) }}"
                                               class="btn btn-sm {{ $statusFilter === 'resolved' ? 'btn-success' : 'btn-outline-success' }}">
                                                Resolved <span class="badge bg-light text-dark">{{ $counts['resolved'] }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Requests table --}}
                                <div class="card">
                                    <div class="card-block">
                                        <div class="dt-responsive table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Barcode</th>
                                                        <th>Product</th>
                                                        <th>Brand</th>
                                                        <th>Status</th>
                                                        <th>Type</th>
                                                        <th>Date</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($requests as $req)
                                                        <tr>
                                                            <td><code>{{ $req->barcode }}</code></td>
                                                            <td>{{ $req->product_name ?? '-' }}</td>
                                                            <td>{{ $req->brand_name ?? '-' }}</td>
                                                            <td>
                                                                @php
                                                                    $badgeClass = match($req->status) {
                                                                        'pending' => 'badge-warning',
                                                                        'ready_for_outreach' => 'badge-info',
                                                                        'contacted' => 'badge-secondary',
                                                                        'ready_for_review' => 'badge-danger',
                                                                        'resolved' => 'badge-success',
                                                                        default => 'badge-light',
                                                                    };
                                                                @endphp
                                                                <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', ucfirst($req->status)) }}</span>
                                                            </td>
                                                            <td>{{ $req->type }}</td>
                                                            <td>{{ $req->created_at?->format('Y-m-d') }}</td>
                                                            <td>
                                                                <a href="{{ route('prioritisation.show', $req->id) }}" class="btn btn-sm btn-primary">View</a>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="7" class="text-center">No requests found.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        {{ $requests->appends(['status' => $statusFilter])->links() }}
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
    .gap-2 { gap: 8px; }
    .badge-warning { background: #f39c12; color: #fff; }
    .badge-info { background: #3498db; color: #fff; }
    .badge-secondary { background: #95a5a6; color: #fff; }
    .badge-danger { background: #e74c3c; color: #fff; }
    .badge-success { background: #27ae60; color: #fff; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
</style>
@endpush
