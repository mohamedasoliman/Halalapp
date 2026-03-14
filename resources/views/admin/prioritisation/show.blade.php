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
                                    <h4>Request #{{ $request->id }}</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="{{ route('prioritisation.index') }}">Prioritisation</a></li>
                                        <li class="breadcrumb-item"><a href="javascript:;">#{{ $request->id }}</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                <div class="row">
                                    {{-- Request details --}}
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header"><h5>Product Details</h5></div>
                                            <div class="card-block">
                                                <table class="table table-bordered">
                                                    <tr><th style="width:30%">Barcode</th><td><code>{{ $request->barcode }}</code></td></tr>
                                                    <tr><th>Product Name</th><td>{{ $request->product_name ?? '-' }}</td></tr>
                                                    <tr><th>Brand</th><td>{{ $request->brand_name ?? 'Unknown' }}</td></tr>
                                                    <tr><th>Type</th><td>{{ $request->type }}</td></tr>
                                                    <tr>
                                                        <th>Status</th>
                                                        <td>
                                                            @php
                                                                $badgeClass = match($request->status) {
                                                                    'pending' => 'badge-warning',
                                                                    'ready_for_outreach' => 'badge-info',
                                                                    'contacted' => 'badge-secondary',
                                                                    'ready_for_review' => 'badge-danger',
                                                                    'resolved' => 'badge-success',
                                                                    default => 'badge-light',
                                                                };
                                                            @endphp
                                                            <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', ucfirst($request->status)) }}</span>
                                                        </td>
                                                    </tr>
                                                    <tr><th>Notes</th><td>{{ $request->notes ?? '-' }}</td></tr>
                                                    <tr><th>Submitted</th><td>{{ $request->created_at?->format('Y-m-d H:i') }}</td></tr>
                                                </table>

                                                @if($request->photo_path)
                                                    <h6 class="mt-3">User Photo</h6>
                                                    <img src="{{ Storage::url($request->photo_path) }}" alt="Product photo" style="max-width:300px; border-radius:8px;">
                                                @endif

                                                @if($product)
                                                    <h6 class="mt-3">Current DB Status</h6>
                                                    <table class="table table-bordered">
                                                        <tr><th style="width:30%">DB Product Name</th><td>{{ $product->product_name }}</td></tr>
                                                        <tr>
                                                            <th>Halal Status</th>
                                                            <td>
                                                                @if($product->halal_status === '0' || $product->halal_status === 0)
                                                                    <span class="badge badge-success">Halal</span>
                                                                @elseif($product->halal_status === '1' || $product->halal_status === 1)
                                                                    <span class="badge badge-danger">Not Halal</span>
                                                                @else
                                                                    <span class="badge badge-warning">Unreviewed</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        <tr><th>Category</th><td>{{ $product->category ?? '-' }}</td></tr>
                                                        <tr><th>Ingredients</th><td>{{ $product->ingredient ?? '-' }}</td></tr>
                                                    </table>
                                                @endif

                                                @if($brand)
                                                    <h6 class="mt-3">Brand Info</h6>
                                                    <table class="table table-bordered">
                                                        <tr><th style="width:30%">Brand</th><td>{{ $brand->name }}</td></tr>
                                                        <tr><th>Email</th><td>{{ $brand->email ?? '-' }}</td></tr>
                                                        <tr><th>Response</th><td>{{ $brand->response ? ucfirst(str_replace('_', ' ', $brand->response)) : 'No response yet' }}</td></tr>
                                                        <tr><th>Scope</th><td>{{ $brand->response_scope ? ucfirst($brand->response_scope) : '-' }}</td></tr>
                                                        <tr><th>Last Contacted</th><td>{{ $brand->last_contacted_at?->format('Y-m-d') ?? 'Never' }}</td></tr>
                                                    </table>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Actions sidebar --}}
                                    <div class="col-md-4">
                                        {{-- Watchers --}}
                                        <div class="card">
                                            <div class="card-header"><h5>Watchers ({{ $request->watchers->count() }})</h5></div>
                                            <div class="card-block">
                                                @forelse($request->watchers as $watcher)
                                                    <div class="mb-2">
                                                        <strong>{{ $watcher->user_name ?? 'Anonymous' }}</strong><br>
                                                        <small>{{ $watcher->user_email }}</small>
                                                    </div>
                                                @empty
                                                    <p class="text-muted">No watchers (anonymous submission).</p>
                                                @endforelse
                                            </div>
                                        </div>

                                        {{-- Update status --}}
                                        @if($request->status !== 'resolved')
                                        <div class="card">
                                            <div class="card-header"><h5>Update Status</h5></div>
                                            <div class="card-block">
                                                <form action="{{ route('prioritisation.status', $request->id) }}" method="POST">
                                                    @csrf
                                                    <div class="form-group mb-2">
                                                        <select name="status" class="form-control">
                                                            <option value="pending" {{ $request->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                            <option value="ready_for_outreach" {{ $request->status === 'ready_for_outreach' ? 'selected' : '' }}>Ready for Outreach</option>
                                                            <option value="contacted" {{ $request->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                                                            <option value="ready_for_review" {{ $request->status === 'ready_for_review' ? 'selected' : '' }}>Ready for Review</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <input type="text" name="brand_name" class="form-control" placeholder="Brand name" value="{{ $request->brand_name }}">
                                                    </div>
                                                    <button type="submit" class="btn btn-primary btn-block">Update</button>
                                                </form>
                                            </div>
                                        </div>

                                        {{-- Resolve --}}
                                        <div class="card">
                                            <div class="card-header"><h5>Resolve (Final Verdict)</h5></div>
                                            <div class="card-block">
                                                <form action="{{ route('prioritisation.resolve', $request->id) }}" method="POST">
                                                    @csrf
                                                    <div class="form-group mb-2">
                                                        <label>Verdict</label>
                                                        <select name="halal_status" class="form-control" required>
                                                            <option value="">-- Select --</option>
                                                            <option value="0">Halal</option>
                                                            <option value="1">Not Halal</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label>Notes</label>
                                                        <textarea name="notes" class="form-control" rows="3" placeholder="Resolution notes..."></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('Are you sure? This will mark the product and notify all watchers.')">Resolve</button>
                                                </form>
                                            </div>
                                        </div>
                                        @else
                                        <div class="card">
                                            <div class="card-header"><h5>Resolved</h5></div>
                                            <div class="card-block">
                                                <p>
                                                    @if($request->resolved_status === 0)
                                                        <span class="badge badge-success">Halal</span>
                                                    @else
                                                        <span class="badge badge-danger">Not Halal</span>
                                                    @endif
                                                </p>
                                                <p class="text-muted">{{ $request->notes }}</p>
                                            </div>
                                        </div>
                                        @endif
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
    .badge-warning { background: #f39c12; color: #fff; }
    .badge-info { background: #3498db; color: #fff; }
    .badge-secondary { background: #95a5a6; color: #fff; }
    .badge-danger { background: #e74c3c; color: #fff; }
    .badge-success { background: #27ae60; color: #fff; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; }
    .btn-block { width: 100%; }
</style>
@endpush
