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

                                {{-- Search + Add Button --}}
                                <div class="card mb-3">
                                    <div class="card-block" style="padding: 10px 20px;">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
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
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addRestaurantModal">
                                                <i class="ti-plus"></i> Add Restaurant
                                            </button>
                                        </div>
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
                                                                <button type="button" class="btn btn-sm btn-danger delete-restaurant-btn" data-index="{{ $r['_index'] }}" data-name="{{ $r['NAME'] ?? 'Restaurant' }}">
                                                                    <i class="ti-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>

                                                        {{-- Edit Modal --}}
                                                        <div class="modal fade" id="editModal{{ $r['_index'] }}" tabindex="-1">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <form action="{{ route('restaurant.tiers.update', $r['_index']) }}" method="POST" enctype="multipart/form-data">
                                                                        @csrf
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Edit: {{ $r['NAME'] ?? 'Restaurant' }}</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Name</label>
                                                                                        <input type="text" name="name" class="form-control" value="{{ $r['NAME'] ?? '' }}">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Category</label>
                                                                                        <input type="text" name="category" class="form-control" value="{{ $r['CATEGORY'] ?? '' }}" placeholder="e.g. Bakery - Sweet">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group mb-3">
                                                                                <label>Address</label>
                                                                                <input type="text" name="address" class="form-control" value="{{ $r['ADDRESS'] ?? '' }}">
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Phone</label>
                                                                                        <input type="text" name="phone" class="form-control" value="{{ $r['PHONENUMBER'] ?? '' }}">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Website</label>
                                                                                        <input type="text" name="website" class="form-control" value="{{ $r['WEBSITEURL'] ?? '' }}" placeholder="https://...">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Latitude</label>
                                                                                        <input type="number" step="any" name="latitude" class="form-control" value="{{ $r['Latitude'] ?? '' }}">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Longitude</label>
                                                                                        <input type="number" step="any" name="longitude" class="form-control" value="{{ $r['Longitude'] ?? '' }}">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group mb-3">
                                                                                <label>Certified</label>
                                                                                <input type="text" name="certified" class="form-control" value="{{ $r['Certified'] ?? '' }}" placeholder="Certification info">
                                                                            </div>

                                                                            <hr>
                                                                            <h6>Opening Hours</h6>
                                                                            <div class="row">
                                                                                @foreach(['monday' => 'MONDAY', 'tuesday' => 'TUESDAY', 'wednesday' => 'WEDNESDAY', 'thursday' => 'THURSDAY', 'friday' => 'FRIDAY', 'saturday' => 'SATURDAY', 'sunday' => 'SUNDAY'] as $field => $key)
                                                                                    <div class="col-md-6">
                                                                                        <div class="form-group mb-2">
                                                                                            <label>{{ ucfirst($field) }}</label>
                                                                                            <input type="text" name="{{ $field }}" class="form-control form-control-sm" value="{{ $r[$key] ?? '' }}" placeholder="e.g. 8 am - 7 pm">
                                                                                        </div>
                                                                                    </div>
                                                                                @endforeach
                                                                            </div>

                                                                            <hr>
                                                                            <h6>Tier & Verification</h6>
                                                                            <div class="row">
                                                                                <div class="col-md-4">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Membership Tier</label>
                                                                                        <select name="membership_tier" class="form-control">
                                                                                            <option value="free" {{ $tier === 'free' ? 'selected' : '' }}>Free</option>
                                                                                            <option value="verified" {{ $tier === 'verified' ? 'selected' : '' }}>Verified ($5/wk)</option>
                                                                                            <option value="featured" {{ $tier === 'featured' ? 'selected' : '' }}>Featured ($10/wk)</option>
                                                                                            <option value="premium" {{ $tier === 'premium' ? 'selected' : '' }}>Premium ($15/wk)</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Verified</label>
                                                                                        <select name="is_verified" class="form-control">
                                                                                            <option value="0" {{ empty($r['is_verified']) ? 'selected' : '' }}>No</option>
                                                                                            <option value="1" {{ !empty($r['is_verified']) ? 'selected' : '' }}>Yes</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Menu URL</label>
                                                                                        <input type="text" name="menu_url" class="form-control" value="{{ $r['menu_url'] ?? '' }}" placeholder="https://...">
                                                                                    </div>
                                                                                </div>
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

{{-- Add Restaurant Modal --}}
<div class="modal fade" id="addRestaurantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('restaurant.tiers.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add New Restaurant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Category</label>
                                <input type="text" name="category" class="form-control" placeholder="e.g. Bakery - Sweet">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label>Address</label>
                        <input type="text" name="address" class="form-control" placeholder="e.g. 123 Main St, Auckland">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Website</label>
                                <input type="text" name="website" class="form-control" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Latitude</label>
                                <input type="number" step="any" name="latitude" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Longitude</label>
                                <input type="number" step="any" name="longitude" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label>Certified</label>
                        <input type="text" name="certified" class="form-control" placeholder="Certification info">
                    </div>

                    <hr>
                    <h6>Opening Hours</h6>
                    <div class="row">
                        @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                            <div class="col-md-6">
                                <div class="form-group mb-2">
                                    <label>{{ ucfirst($day) }}</label>
                                    <input type="text" name="{{ $day }}" class="form-control form-control-sm" placeholder="e.g. 8 am - 7 pm">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <hr>
                    <h6>Tier & Verification</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Membership Tier</label>
                                <select name="membership_tier" class="form-control">
                                    <option value="free">Free</option>
                                    <option value="verified">Verified ($5/wk)</option>
                                    <option value="featured">Featured ($10/wk)</option>
                                    <option value="premium">Premium ($15/wk)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Verified</label>
                                <select name="is_verified" class="form-control">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Menu URL</label>
                                <input type="text" name="menu_url" class="form-control" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6>Logo</h6>
                    <div class="form-group mb-3">
                        <label>Upload Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Restaurant</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.delete-restaurant-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var name = this.getAttribute('data-name');
                if (!confirm('Are you sure you want to delete "' + name + '"?')) return;
                var index = this.getAttribute('data-index');
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("admin/restaurant-tiers") }}/' + index;
                form.innerHTML = '@csrf' + '<input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(form);
                form.submit();
            });
        });
    });
</script>
@endpush
