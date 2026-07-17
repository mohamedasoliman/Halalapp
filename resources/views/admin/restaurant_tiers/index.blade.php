@extends('admin.layouts.app')

@php
    $tierButtonClasses = [
        'free' => 'btn-secondary',
        'starter' => 'btn-success',
        'growth' => 'btn-info',
        'premium' => 'btn-warning',
    ];
    $tierBadgeClasses = [
        'free' => 'badge-free',
        'starter' => 'badge-starter',
        'growth' => 'badge-growth',
        'premium' => 'badge-premium',
    ];
@endphp

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
                                    <h4>Restaurant Memberships</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Restaurant Memberships</a></li>
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
                                            @foreach(['premium', 'growth', 'starter', 'free'] as $filterTier)
                                                @php
                                                    $buttonClass = $tierButtonClasses[$filterTier];
                                                    $option = $tierOptions[$filterTier];
                                                    $price = $option['weekly_price'] > 0
                                                        ? ' ($'.$option['weekly_price'].'/wk)'
                                                        : '';
                                                @endphp
                                                <a href="{{ route('restaurant.tiers', ['tier' => $filterTier]) }}"
                                                   class="btn btn-sm {{ $tierFilter === $filterTier ? $buttonClass : str_replace('btn-', 'btn-outline-', $buttonClass) }}">
                                                    {{ $option['label'] }}{{ $price }}
                                                    <span class="badge bg-light text-dark">{{ $counts[$filterTier] }}</span>
                                                </a>
                                            @endforeach
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
                                                        <th>Partner Tier</th>
                                                        <th>Trading Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($restaurants as $r)
                                                        @php
                                                            $tier = $r['_tier'] ?? 'free';
                                                            $tierBadge = $tierBadgeClasses[$tier];
                                                            $address = $r['ADDRESS'] ?? '';
                                                            $area = '';
                                                            $businessStatus = strtoupper($r['BUSINESS_STATUS'] ?? 'OPERATIONAL');
                                                            $statusClass = match($businessStatus) {
                                                                'OPERATIONAL' => 'bg-success',
                                                                'CLOSED_TEMPORARILY' => 'bg-warning text-dark',
                                                                'REVIEW_REQUIRED' => 'bg-danger',
                                                                default => 'bg-secondary',
                                                            };
                                                            $statusLabel = match($businessStatus) {
                                                                'OPERATIONAL' => 'Operational',
                                                                'CLOSED_TEMPORARILY' => 'Temporarily closed',
                                                                'REVIEW_REQUIRED' => 'Review required',
                                                                default => 'Not confirmed',
                                                            };
                                                            if (str_contains($address, ',')) {
                                                                $parts = explode(',', $address);
                                                                $area = trim(end($parts));
                                                            }
                                                            $dealRows = is_array($r['Deals'] ?? null) ? $r['Deals'] : [];
                                                            if ($dealRows === [] && !empty($r['DealTitle'])) {
                                                                $dealRows[] = [
                                                                    'Title' => $r['DealTitle'],
                                                                    'Description' => $r['DealDescription'] ?? '',
                                                                    'Code' => $r['DealCode'] ?? '',
                                                                    'Expiry' => $r['DealExpiry'] ?? '',
                                                                ];
                                                            }
                                                            $dealRows = array_values($dealRows);
                                                            while (count($dealRows) < 5) $dealRows[] = [];
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
                                                            <td>
                                                                <span class="badge {{ $tierBadge }}">
                                                                    {{ $tierOptions[$tier]['label'] }}{{ $tier !== 'free' ? ' Partner' : '' }}
                                                                </span>
                                                            </td>
                                                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
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
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Trading status</label>
                                                                                        <select name="business_status" class="form-control" required>
                                                                                            @foreach([
                                                                                                'OPERATIONAL' => 'Operational',
                                                                                                'CLOSED_TEMPORARILY' => 'Temporarily closed',
                                                                                                'UNKNOWN' => 'Not confirmed',
                                                                                                'REVIEW_REQUIRED' => 'Conflicting evidence - review required',
                                                                                            ] as $value => $label)
                                                                                                <option value="{{ $value }}" @selected($businessStatus === $value)>{{ $label }}</option>
                                                                                            @endforeach
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Last reviewed</label>
                                                                                        <input type="date" name="last_reviewed_at" class="form-control" value="{{ $r['LAST_REVIEWED_AT'] ?? '' }}">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Public status note</label>
                                                                                        <textarea name="status_note" class="form-control" rows="2" maxlength="500">{{ $r['STATUS_NOTE'] ?? '' }}</textarea>
                                                                                    </div>
                                                                                </div>
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
                                                                            <h6>Membership</h6>
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Membership Tier</label>
                                                                                        <select name="tier" class="form-control membership-tier-select">
                                                                                            @foreach($tierOptions as $value => $option)
                                                                                                <option value="{{ $value }}" {{ $tier === $value ? 'selected' : '' }}>
                                                                                                    {{ $option['label'] }}{{ $option['weekly_price'] > 0 ? ' ($'.$option['weekly_price'].'/wk)' : '' }}
                                                                                                </option>
                                                                                            @endforeach
                                                                                        </select>
                                                                                        <small class="text-muted">Starter, Growth, and Premium receive their matching partner badge. Basic has no partner badge.</small>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group mb-3">
                                                                                        <label>Menu URL</label>
                                                                                        <input type="url" name="menu_url" class="form-control" data-membership-menu value="{{ $r['menu_url'] ?? $r['MenuUrl'] ?? '' }}" placeholder="https://...">
                                                                                        <small class="text-muted membership-menu-help">Menus are available on Growth and Premium.</small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="membership-promotion-fields">
                                                                                <div class="row">
                                                                                    <div class="col-md-6">
                                                                                        <div class="form-group mb-3">
                                                                                            <label>Direct enquiry email</label>
                                                                                            <input type="email" name="enquiry_email" class="form-control" data-membership-enquiry value="{{ $r['EnquiryEmail'] ?? '' }}" placeholder="hello@example.com">
                                                                                            <small class="text-muted membership-enquiry-help">Direct enquiries are available on Growth and Premium.</small>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <h6>Halal Kiwi Deals</h6>
                                                                            <p class="text-muted membership-deal-help">Starter allows 1 active deal, Growth 3, and Premium 5.</p>
                                                                            @foreach($dealRows as $dealIndex => $deal)
                                                                                <div class="border rounded p-3 mb-3" data-deal-slot="{{ $dealIndex }}">
                                                                                    <strong>Deal {{ $dealIndex + 1 }}</strong>
                                                                                    <div class="row mt-2">
                                                                                        <div class="col-md-6">
                                                                                            <div class="form-group mb-3">
                                                                                                <label>Deal title</label>
                                                                                                <input type="text" name="deals[{{ $dealIndex }}][title]" class="form-control" data-membership-deal maxlength="120" value="{{ $deal['Title'] ?? $deal['title'] ?? '' }}">
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-md-3">
                                                                                            <div class="form-group mb-3">
                                                                                                <label>Deal code</label>
                                                                                                <input type="text" name="deals[{{ $dealIndex }}][code]" class="form-control" data-membership-deal maxlength="50" value="{{ $deal['Code'] ?? $deal['code'] ?? '' }}">
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-md-3">
                                                                                            <div class="form-group mb-3">
                                                                                                <label>Deal end date</label>
                                                                                                <input type="date" name="deals[{{ $dealIndex }}][expiry]" class="form-control" data-membership-deal value="{{ $deal['Expiry'] ?? $deal['expiry'] ?? '' }}">
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12">
                                                                                            <div class="form-group mb-3">
                                                                                                <label>Deal details</label>
                                                                                                <textarea name="deals[{{ $dealIndex }}][description]" class="form-control" data-membership-deal rows="2" maxlength="500">{{ $deal['Description'] ?? $deal['description'] ?? '' }}</textarea>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach

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
                                                                                for ($i = 1; $i <= 5; $i++) {
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
                                                                                <label>Upload Gallery Images</label>
                                                                                <input type="file" name="images[]" class="form-control" data-membership-gallery accept="image/*" multiple>
                                                                                <small class="text-muted">Growth allows 3 images. Premium allows 5. Other tiers do not retain gallery images.</small>
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Trading status</label>
                                <select name="business_status" class="form-control" required>
                                    <option value="OPERATIONAL">Operational</option>
                                    <option value="CLOSED_TEMPORARILY">Temporarily closed</option>
                                    <option value="UNKNOWN">Not confirmed</option>
                                    <option value="REVIEW_REQUIRED">Conflicting evidence - review required</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Last reviewed</label>
                                <input type="date" name="last_reviewed_at" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-3">
                                <label>Public status note</label>
                                <textarea name="status_note" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
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
                    <h6>Membership</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Membership Tier</label>
                                <select name="tier" class="form-control membership-tier-select">
                                    @foreach($tierOptions as $value => $option)
                                        <option value="{{ $value }}">
                                            {{ $option['label'] }}{{ $option['weekly_price'] > 0 ? ' ($'.$option['weekly_price'].'/wk)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Starter, Growth, and Premium receive their matching partner badge. Basic has no partner badge.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Menu URL</label>
                                <input type="url" name="menu_url" class="form-control" data-membership-menu placeholder="https://...">
                                <small class="text-muted membership-menu-help">Menus are available on Growth and Premium.</small>
                            </div>
                        </div>
                    </div>

                    <div class="membership-promotion-fields">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Direct enquiry email</label>
                                    <input type="email" name="enquiry_email" class="form-control" data-membership-enquiry placeholder="hello@example.com">
                                    <small class="text-muted membership-enquiry-help">Direct enquiries are available on Growth and Premium.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6>Halal Kiwi Deals</h6>
                    <p class="text-muted membership-deal-help">Starter allows 1 active deal, Growth 3, and Premium 5.</p>
                    @for($dealIndex = 0; $dealIndex < 5; $dealIndex++)
                        <div class="border rounded p-3 mb-3" data-deal-slot="{{ $dealIndex }}">
                            <strong>Deal {{ $dealIndex + 1 }}</strong>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label>Deal title</label>
                                        <input type="text" name="deals[{{ $dealIndex }}][title]" class="form-control" data-membership-deal maxlength="120">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-3">
                                        <label>Deal code</label>
                                        <input type="text" name="deals[{{ $dealIndex }}][code]" class="form-control" data-membership-deal maxlength="50">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-3">
                                        <label>Deal end date</label>
                                        <input type="date" name="deals[{{ $dealIndex }}][expiry]" class="form-control" data-membership-deal>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-3">
                                        <label>Deal details</label>
                                        <textarea name="deals[{{ $dealIndex }}][description]" class="form-control" data-membership-deal rows="2" maxlength="500"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endfor

                    <hr>
                    <h6>Logo & Gallery</h6>
                    <div class="form-group mb-3">
                        <label>Upload Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group mb-3">
                        <label>Upload Gallery Images</label>
                        <input type="file" name="images[]" class="form-control" data-membership-gallery accept="image/*" multiple>
                        <small class="text-muted">Growth allows 3 images. Premium allows 5.</small>
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
    .badge-growth { background: #3498db; color: #fff; }
    .badge-starter { background: #27ae60; color: #fff; }
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

        document.querySelectorAll('.membership-tier-select').forEach(function(select) {
            var form = select.closest('form');
            var updateEntitlements = function() {
                var tier = select.value;
                var hasEnquiries = tier === 'growth' || tier === 'premium';
                var hasGallery = hasEnquiries;
                var hasMenu = hasEnquiries;
                var dealLimits = { free: 0, starter: 1, growth: 3, premium: 5 };
                var dealLimit = dealLimits[tier];

                form.querySelectorAll('[data-membership-menu]').forEach(function(input) {
                    input.disabled = !hasMenu;
                });
                form.querySelectorAll('[data-membership-enquiry]').forEach(function(input) {
                    input.disabled = !hasEnquiries;
                });
                form.querySelectorAll('[data-membership-gallery]').forEach(function(input) {
                    input.disabled = !hasGallery;
                });
                form.querySelectorAll('[data-deal-slot]').forEach(function(slot) {
                    var enabled = Number(slot.dataset.dealSlot) < dealLimit;
                    slot.hidden = !enabled;
                    slot.querySelectorAll('[data-membership-deal]').forEach(function(input) {
                        input.disabled = !enabled;
                    });
                });
                form.querySelectorAll('.membership-enquiry-help').forEach(function(help) {
                    help.textContent = hasEnquiries
                        ? 'This tier can receive direct enquiries.'
                        : 'Direct enquiries are available on Growth and Premium.';
                });
                form.querySelectorAll('.membership-deal-help').forEach(function(help) {
                    help.textContent = dealLimit === 0
                        ? 'Deals are available on Starter, Growth, and Premium.'
                        : 'This tier can publish up to ' + dealLimit + ' active deal' + (dealLimit === 1 ? '' : 's') + '. Leave a title empty to remove that slot.';
                });
                form.querySelectorAll('.membership-menu-help').forEach(function(help) {
                    help.textContent = hasMenu
                        ? 'This tier can publish a menu link.'
                        : 'Menus are available on Growth and Premium.';
                });
            };

            select.addEventListener('change', updateEntitlements);
            updateEntitlements();
        });
    });
</script>
@endpush
