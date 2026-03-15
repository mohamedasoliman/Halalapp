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
                                    <h4>Restaurant Tiers</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Restaurant Tiers</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                {{-- Tier filter tabs --}}
                                <div class="card mb-3">
                                    <div class="card-block" style="padding: 10px 20px;">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('restaurant.tiers') }}"
                                               class="btn btn-sm {{ $tierFilter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                                                All <span class="badge bg-light text-dark">{{ $counts['all'] }}</span>
                                            </a>
                                            <a href="{{ route('restaurant.tiers', ['tier' => 'premium']) }}"
                                               class="btn btn-sm {{ $tierFilter === 'premium' ? 'btn-warning' : 'btn-outline-warning' }}">
                                                Premium ($15/wk) <span class="badge bg-light text-dark">{{ $counts['premium'] }}</span>
                                            </a>
                                            <a href="{{ route('restaurant.tiers', ['tier' => 'featured']) }}"
                                               class="btn btn-sm {{ $tierFilter === 'featured' ? 'btn-info' : 'btn-outline-info' }}">
                                                Featured ($10/wk) <span class="badge bg-light text-dark">{{ $counts['featured'] }}</span>
                                            </a>
                                            <a href="{{ route('restaurant.tiers', ['tier' => 'verified']) }}"
                                               class="btn btn-sm {{ $tierFilter === 'verified' ? 'btn-success' : 'btn-outline-success' }}">
                                                Verified ($5/wk) <span class="badge bg-light text-dark">{{ $counts['verified'] }}</span>
                                            </a>
                                            <a href="{{ route('restaurant.tiers', ['tier' => 'free']) }}"
                                               class="btn btn-sm {{ $tierFilter === 'free' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                                                Free <span class="badge bg-light text-dark">{{ $counts['free'] }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Search --}}
                                <div class="card mb-3">
                                    <div class="card-block" style="padding: 10px 20px;">
                                        <form action="{{ route('restaurant.tiers') }}" method="GET" class="d-flex gap-2">
                                            @if($tierFilter !== 'all')
                                                <input type="hidden" name="tier" value="{{ $tierFilter }}">
                                            @endif
                                            <input type="text" name="search" class="form-control" placeholder="Search by name, category, or area..." value="{{ request('search') }}" style="max-width: 400px;">
                                            <button type="submit" class="btn btn-primary btn-sm">Search</button>
                                            @if(request('search'))
                                                <a href="{{ route('restaurant.tiers', ['tier' => $tierFilter]) }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                                            @endif
                                        </form>
                                    </div>
                                </div>

                                {{-- Restaurants table --}}
                                <div class="card">
                                    <div class="card-block">
                                        <div class="dt-responsive table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Restaurant</th>
                                                        <th>Category</th>
                                                        <th>Area</th>
                                                        <th>Tier</th>
                                                        <th>Verified</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($restaurants as $r)
                                                        @php
                                                            $tier = $r['membership_tier'] ?? '';
                                                            if (empty($tier) || $tier === 'none') $tier = 'free';
                                                            $tierBadge = match($tier) {
                                                                'premium' => 'badge-premium',
                                                                'featured' => 'badge-featured',
                                                                'verified' => 'badge-verified',
                                                                default => 'badge-free',
                                                            };
                                                            $address = $r['ADDRESS'] ?? '';
                                                            $area = '';
                                                            if (str_contains($address, ',')) {
                                                                $parts = explode(',', $address);
                                                                $area = trim(end($parts));
                                                            }
                                                        @endphp
                                                        <tr>
                                                            <td>
                                                                <strong>{{ $r['NAME'] ?? '-' }}</strong>
                                                                @if(!empty($r['PHONENUMBER']))
                                                                    <br><small class="text-muted">{{ $r['PHONENUMBER'] }}</small>
                                                                @endif
                                                            </td>
                                                            <td>{{ $r['CATEGORY'] ?? '-' }}</td>
                                                            <td>{{ $area }}</td>
                                                            <td><span class="badge {{ $tierBadge }}">{{ ucfirst($tier) }}</span></td>
                                                            <td>
                                                                @if(!empty($r['is_verified']))
                                                                    <span class="badge badge-verified">Yes</span>
                                                                @else
                                                                    <span class="text-muted">No</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-primary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editModal{{ $r['_index'] }}">
                                                                    Edit
                                                                </button>
                                                            </td>
                                                        </tr>

                                                        {{-- Edit Modal --}}
                                                        <div class="modal fade" id="editModal{{ $r['_index'] }}" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form action="{{ route('restaurant.tiers.update', $r['_index']) }}" method="POST" enctype="multipart/form-data">
                                                                        @csrf
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">{{ $r['NAME'] ?? 'Restaurant' }}</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="form-group mb-3">
                                                                                <label>Membership Tier</label>
                                                                                <select name="membership_tier" class="form-control">
                                                                                    <option value="free" {{ $tier === 'free' ? 'selected' : '' }}>Free</option>
                                                                                    <option value="verified" {{ $tier === 'verified' ? 'selected' : '' }}>Verified ($5/wk)</option>
                                                                                    <option value="featured" {{ $tier === 'featured' ? 'selected' : '' }}>Featured ($10/wk)</option>
                                                                                    <option value="premium" {{ $tier === 'premium' ? 'selected' : '' }}>Premium ($15/wk)</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group mb-3">
                                                                                <label>Verified</label>
                                                                                <select name="is_verified" class="form-control">
                                                                                    <option value="0" {{ empty($r['is_verified']) ? 'selected' : '' }}>No</option>
                                                                                    <option value="1" {{ !empty($r['is_verified']) ? 'selected' : '' }}>Yes</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group mb-3">
                                                                                <label>Menu URL</label>
                                                                                <input type="text" name="menu_url" class="form-control" value="{{ $r['menu_url'] ?? '' }}" placeholder="https://...">
                                                                            </div>

                                                                            <hr>
                                                                            <h6>Images</h6>

                                                                            @if(!empty($r['LOGOURL']))
                                                                                <div class="mb-2">
                                                                                    <small class="text-muted">Current logo:</small><br>
                                                                                    <img src="{{ $r['LOGOURL'] }}" alt="Logo" style="max-height:60px; border-radius:4px;">
                                                                                </div>
                                                                            @endif
                                                                            <div class="form-group mb-3">
                                                                                <label>Upload New Logo</label>
                                                                                <input type="file" name="logo" class="form-control" accept="image/*">
                                                                            </div>

                                                                            @php
                                                                                $existingImages = [];
                                                                                for ($i = 1; $i <= 6; $i++) {
                                                                                    $img = $r["Image_{$i}"] ?? '';
                                                                                    if (!empty($img) && $img !== 'null') $existingImages[] = $img;
                                                                                }
                                                                            @endphp

                                                                            @if(count($existingImages) > 0)
                                                                                <div class="mb-2">
                                                                                    <small class="text-muted">Current images ({{ count($existingImages) }}):</small><br>
                                                                                    @foreach($existingImages as $img)
                                                                                        @php $imgUrl = str_starts_with($img, 'http') ? $img : "https://halalapp.info/upload/resturant/{$img}"; @endphp
                                                                                        <img src="{{ $imgUrl }}" alt="Restaurant" style="max-height:50px; border-radius:4px; margin:2px;">
                                                                                    @endforeach
                                                                                </div>
                                                                            @endif

                                                                            <div class="form-group mb-3">
                                                                                <label>Upload Images (up to 6)</label>
                                                                                <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                                                                                <small class="text-muted">New uploads will replace existing images starting from Image 1.</small>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-primary">Save</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
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
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; }
    .badge-premium { background: #f39c12; color: #fff; }
    .badge-featured { background: #3498db; color: #fff; }
    .badge-verified { background: #27ae60; color: #fff; }
    .badge-free { background: #bdc3c7; color: #333; }
</style>
@endpush
