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
                                    <h4>Muslim Business Network</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Business Network</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                <div class="card mb-3">
                                    <div class="card-block py-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach([
                                                'all' => 'All',
                                                'premium' => 'Premium ($30/wk)',
                                                'growth' => 'Growth ($15/wk)',
                                                'starter' => 'Starter ($5/wk)',
                                                'community' => 'Community',
                                            ] as $tierKey => $tierLabel)
                                                <a href="{{ route('business-network.index', ['tier' => $tierKey]) }}"
                                                   class="btn btn-sm {{ $tierFilter === $tierKey ? 'btn-primary' : 'btn-outline-primary' }}">
                                                    {{ $tierLabel }}
                                                    <span class="badge bg-light text-dark">{{ $counts[$tierKey] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-3">
                                    <div class="card-block py-3">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <form action="{{ route('business-network.index') }}" method="GET" class="d-flex flex-wrap gap-2">
                                                @if($tierFilter !== 'all')
                                                    <input type="hidden" name="tier" value="{{ $tierFilter }}">
                                                @endif
                                                <input type="search"
                                                       name="search"
                                                       class="form-control"
                                                       placeholder="Search name, category, or area"
                                                       value="{{ $search }}"
                                                       style="min-width: 280px;">
                                                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                                                @if($search !== '')
                                                    <a href="{{ route('business-network.index', ['tier' => $tierFilter]) }}"
                                                       class="btn btn-outline-secondary btn-sm">Clear</a>
                                                @endif
                                            </form>
                                            <a href="{{ route('business-network.create') }}" class="btn btn-success btn-sm">
                                                <i class="ti-plus"></i> Add Business
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-block">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="mb-1">Business listings</h5>
                                                <small class="text-muted">Premium and Growth listings are prioritised in the Halal Kiwi app.</small>
                                            </div>
                                            <span class="badge bg-secondary">{{ count($businesses) }} shown</span>
                                        </div>

                                        <div class="dt-responsive table-responsive">
                                            <table class="table table-striped table-bordered align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Business</th>
                                                        <th>Category</th>
                                                        <th>Tier</th>
                                                        <th>Status</th>
                                                        <th>Deal</th>
                                                        <th style="width: 150px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($businesses as $business)
                                                        @php
                                                            $tier = $business['_tier'];
                                                            $tierClass = match($tier) {
                                                                'premium' => 'tier-premium',
                                                                'growth' => 'tier-growth',
                                                                'starter' => 'tier-starter',
                                                                default => 'tier-community',
                                                            };
                                                            $active = !array_key_exists('IsActive', $business) || (bool) $business['IsActive'];
                                                        @endphp
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="business-logo">
                                                                        @if(!empty($business['Logo']))
                                                                            <img src="{{ $business['Logo'] }}" alt="{{ $business['Name'] ?? 'Business' }} logo">
                                                                        @else
                                                                            <i class="ti-briefcase"></i>
                                                                        @endif
                                                                    </div>
                                                                    <div>
                                                                        <strong>{{ $business['Name'] ?? '-' }}</strong>
                                                                        @if(!empty($business['Address']))
                                                                            <div class="text-muted small">{{ $business['Address'] }}</div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                {{ $business['Category'] ?? '-' }}
                                                                <div class="text-muted small">{{ $business['SubCategory'] ?? '' }}</div>
                                                            </td>
                                                            <td><span class="tier-badge {{ $tierClass }}">{{ ucfirst($tier) }}</span></td>
                                                            <td>
                                                                <span class="badge {{ $active ? 'bg-success' : 'bg-secondary' }}">
                                                                    {{ $active ? 'Active' : 'Hidden' }}
                                                                </span>
                                                                @if(!empty($business['Verified']))
                                                                    <div class="mt-1"><span class="badge bg-info">Verified</span></div>
                                                                @endif
                                                                @if(!empty($business['FeatureInCarousel']) || (!array_key_exists('FeatureInCarousel', $business) && $tier === 'premium'))
                                                                    <div class="mt-1"><span class="badge bg-warning text-dark">Featured</span></div>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if(!empty($business['DealTitle']))
                                                                    <span class="badge bg-warning text-dark">{{ $business['DealTitle'] }}</span>
                                                                @else
                                                                    <span class="text-muted">None</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <div class="d-flex gap-2">
                                                                    <a href="{{ route('business-network.edit', $business['_index']) }}"
                                                                       class="btn btn-primary btn-sm"
                                                                       title="Edit {{ $business['Name'] ?? 'business' }}">
                                                                        <i class="ti-pencil"></i> Edit
                                                                    </a>
                                                                    <form action="{{ route('business-network.destroy', $business['_index']) }}"
                                                                          method="POST"
                                                                          onsubmit="return confirm('Remove this business from the directory?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete business">
                                                                            <i class="ti-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                No businesses match these filters.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
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
        .business-logo {
            align-items: center;
            background: #f5f7f8;
            border: 1px solid #dde3e7;
            border-radius: 6px;
            display: flex;
            flex: 0 0 48px;
            height: 48px;
            justify-content: center;
            overflow: hidden;
            width: 48px;
        }
        .business-logo img { height: 100%; object-fit: cover; width: 100%; }
        .tier-badge { border-radius: 4px; display: inline-block; font-size: 12px; font-weight: 700; padding: 5px 9px; }
        .tier-premium { background: #fff3cd; color: #755800; }
        .tier-growth { background: #dbeafe; color: #174f7a; }
        .tier-starter { background: #d8f1ec; color: #024543; }
        .tier-community { background: #e9ecef; color: #495057; }
    </style>
@endpush
