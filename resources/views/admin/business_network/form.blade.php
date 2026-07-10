@extends('admin.layouts.app')

@php
    $isEdit = $index !== null;
    $rawTier = strtolower($business['Tier'] ?? 'community');
    $tier = match($rawTier) {
        'gold', 'premium' => 'premium',
        'silver', 'featured', 'growth' => 'growth',
        'verified', 'starter' => 'starter',
        default => 'community',
    };
    $hours = $business['hours'] ?? [];
    $active = !array_key_exists('IsActive', $business) || (bool) $business['IsActive'];
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
                                    <h4>{{ $isEdit ? 'Edit Business' : 'Add Business' }}</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="{{ route('business-network.index') }}">Business Network</a></li>
                                        <li class="breadcrumb-item"><a href="javascript:;">{{ $isEdit ? 'Edit' : 'Add' }}</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                @if($errors->any())
                                    <div class="alert alert-danger" role="alert">
                                        <strong>Please check the highlighted fields.</strong>
                                        <ul class="mb-0 mt-2">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form action="{{ $isEdit ? route('business-network.update', $index) : route('business-network.store') }}"
                                      method="POST"
                                      enctype="multipart/form-data">
                                    @csrf
                                    @if($isEdit)
                                        @method('PUT')
                                    @endif

                                    <div class="card mb-3">
                                        <div class="card-header"><h5>Business details</h5></div>
                                        <div class="card-block">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="name" class="form-label">Business name *</label>
                                                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                                           value="{{ old('name', $business['Name'] ?? '') }}" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="category" class="form-label">Category *</label>
                                                    <input id="category" name="category" list="business-categories"
                                                           class="form-control @error('category') is-invalid @enderror"
                                                           value="{{ old('category', $business['Category'] ?? '') }}" required>
                                                    <datalist id="business-categories">
                                                        @foreach($categories as $category)
                                                            <option value="{{ $category }}"></option>
                                                        @endforeach
                                                    </datalist>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="sub_category" class="form-label">Subcategory *</label>
                                                    <input id="sub_category" name="sub_category"
                                                           class="form-control @error('sub_category') is-invalid @enderror"
                                                           value="{{ old('sub_category', $business['SubCategory'] ?? '') }}" required>
                                                </div>
                                                <div class="col-12">
                                                    <label for="address" class="form-label">Address or service area</label>
                                                    <input id="address" name="address" class="form-control"
                                                           value="{{ old('address', $business['Address'] ?? '') }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="business_type" class="form-label">Business status *</label>
                                                    <select id="business_type" name="business_type" class="form-control" required>
                                                        @foreach([
                                                            'muslim_owned' => 'Muslim-owned',
                                                            'halal_certified' => 'Halal-certified',
                                                            'muslim_friendly' => 'Muslim-friendly',
                                                            'community_organisation' => 'Mosque or community organisation',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" @selected(old('business_type', $business['BusinessType'] ?? 'muslim_owned') === $value)>
                                                                {{ $label }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="menu_url" class="form-label">Menu or services URL</label>
                                                    <input id="menu_url" name="menu_url" type="url" class="form-control"
                                                           placeholder="https://"
                                                           value="{{ old('menu_url', $business['MenuUrl'] ?? '') }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="bio" class="form-label">Short summary</label>
                                                    <textarea id="bio" name="bio" class="form-control" rows="3" maxlength="300">{{ old('bio', $business['Bio'] ?? '') }}</textarea>
                                                    <small class="text-muted">Shown on the business card. Keep it short.</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="description" class="form-label">Full description</label>
                                                    <textarea id="description" name="description" class="form-control" rows="3" maxlength="3000">{{ old('description', $business['Desc'] ?? '') }}</textarea>
                                                    <small class="text-muted">Shown on the business profile.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-3">
                                        <div class="card-header"><h5>Contact and social links</h5></div>
                                        <div class="card-block">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="phone" class="form-label">Phone</label>
                                                    <input id="phone" name="phone" class="form-control" value="{{ old('phone', $business['Phone'] ?? '') }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label">Public enquiry email</label>
                                                    <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $business['Email'] ?? '') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="website" class="form-label">Website</label>
                                                    <input id="website" name="website" type="url" class="form-control" placeholder="https://" value="{{ old('website', $business['website'] ?? '') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="instagram" class="form-label">Instagram</label>
                                                    <input id="instagram" name="instagram" type="url" class="form-control" placeholder="https://" value="{{ old('instagram', $business['Instagram'] ?? '') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="facebook" class="form-label">Facebook</label>
                                                    <input id="facebook" name="facebook" type="url" class="form-control" placeholder="https://" value="{{ old('facebook', $business['Facebook'] ?? '') }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-3">
                                        <div class="card-header"><h5>Membership and visibility</h5></div>
                                        <div class="card-block">
                                            <div class="row g-3 align-items-start">
                                                <div class="col-md-5">
                                                    <label for="tier" class="form-label">MBN tier *</label>
                                                    <select id="tier" name="tier" class="form-control" required>
                                                        <option value="community" @selected(old('tier', $tier) === 'community')>Community (legacy free listing)</option>
                                                        <option value="starter" @selected(old('tier', $tier) === 'starter')>Starter ($5/week) - logo only</option>
                                                        <option value="growth" @selected(old('tier', $tier) === 'growth')>Growth ($15/week) - up to 3 photos</option>
                                                        <option value="premium" @selected(old('tier', $tier) === 'premium')>Premium ($30/week) - up to 5 photos</option>
                                                    </select>
                                                    <small id="tier-help" class="text-muted d-block mt-2"></small>
                                                </div>
                                                <div class="col-md-7">
                                                    <div class="form-check mb-2">
                                                        <input type="hidden" name="verified" value="0">
                                                        <input id="verified" name="verified" type="checkbox" value="1" class="form-check-input"
                                                               @checked((bool) old('verified', $business['Verified'] ?? false))>
                                                        <label for="verified" class="form-check-label">Show Halal Kiwi verified badge</label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input type="hidden" name="is_active" value="0">
                                                        <input id="is_active" name="is_active" type="checkbox" value="1" class="form-check-input"
                                                               @checked((bool) old('is_active', $active))>
                                                        <label for="is_active" class="form-check-label">Visible in the Halal Kiwi app</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="hidden" name="permission_granted" value="0">
                                                        <input id="permission_granted" name="permission_granted" type="checkbox" value="1" class="form-check-input"
                                                               @checked((bool) old('permission_granted', $business['PermissionGranted'] ?? false)) {{ $isEdit ? '' : 'required' }}>
                                                        <label for="permission_granted" class="form-check-label">
                                                            Business has authorised Halal Kiwi to publish its information, logo, and photos
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-3">
                                        <div class="card-header"><h5>Logo and photos</h5></div>
                                        <div class="card-block">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="logo" class="form-label">Business logo</label>
                                                    <input id="logo" name="logo" type="file" accept="image/*" class="form-control">
                                                    <small class="text-muted">One logo, maximum 5 MB.</small>
                                                    @if(!empty($business['Logo']))
                                                        <div class="mt-3"><img src="{{ $business['Logo'] }}" alt="Current logo" class="media-preview"></div>
                                                    @endif
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="images" class="form-label">Gallery photos</label>
                                                    <input id="images" name="images[]" type="file" accept="image/*" multiple class="form-control">
                                                    <small id="gallery-help" class="text-muted">Growth allows 3 photos. Premium allows 5.</small>
                                                    @if(!empty($business['images']))
                                                        <div class="media-grid mt-3">
                                                            @foreach($business['images'] as $image)
                                                                <img src="{{ $image }}" alt="Current business photo" class="media-preview">
                                                            @endforeach
                                                        </div>
                                                        <div class="form-check mt-2">
                                                            <input type="hidden" name="clear_gallery" value="0">
                                                            <input id="clear_gallery" name="clear_gallery" type="checkbox" value="1" class="form-check-input">
                                                            <label for="clear_gallery" class="form-check-label">Remove all existing gallery photos</label>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-3">
                                        <div class="card-header"><h5>Halal Kiwi Deal</h5></div>
                                        <div class="card-block">
                                            <p class="text-muted">Leave the title empty when the business has no active offer.</p>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="deal_title" class="form-label">Offer title</label>
                                                    <input id="deal_title" name="deal_title" class="form-control" maxlength="120"
                                                           placeholder="20% off your first service"
                                                           value="{{ old('deal_title', $business['DealTitle'] ?? '') }}">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="deal_code" class="form-label">Promo code</label>
                                                    <input id="deal_code" name="deal_code" class="form-control" maxlength="50"
                                                           value="{{ old('deal_code', $business['DealCode'] ?? '') }}">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="deal_expiry" class="form-label">End date</label>
                                                    <input id="deal_expiry" name="deal_expiry" type="date" class="form-control"
                                                           value="{{ old('deal_expiry', $business['DealExpiry'] ?? '') }}">
                                                </div>
                                                <div class="col-12">
                                                    <label for="deal_description" class="form-label">Offer details</label>
                                                    <textarea id="deal_description" name="deal_description" class="form-control" rows="2" maxlength="500">{{ old('deal_description', $business['DealDescription'] ?? '') }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-3">
                                        <div class="card-header"><h5>Opening hours</h5></div>
                                        <div class="card-block">
                                            <div class="row g-3">
                                                @foreach([
                                                    'monday' => 'Monday',
                                                    'tuesday' => 'Tuesday',
                                                    'wednesday' => 'Wednesday',
                                                    'thursday' => 'Thursday',
                                                    'friday' => 'Friday',
                                                    'saturday' => 'Saturday',
                                                    'sunday' => 'Sunday',
                                                ] as $field => $day)
                                                    <div class="col-md-6 col-xl-4">
                                                        <label for="{{ $field }}" class="form-label">{{ $day }}</label>
                                                        <input id="{{ $field }}" name="{{ $field }}" class="form-control"
                                                               placeholder="9:00am - 5:00pm or Closed"
                                                               value="{{ old($field, $hours[$day] ?? '') }}">
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mb-4">
                                        <a href="{{ route('business-network.index') }}" class="btn btn-outline-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti-save"></i> {{ $isEdit ? 'Save Changes' : 'Add Business' }}
                                        </button>
                                    </div>
                                </form>
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
        .media-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .media-preview { border: 1px solid #dde3e7; border-radius: 6px; height: 72px; object-fit: cover; width: 72px; }
        .form-label { font-weight: 600; }
        .form-check-input { margin-top: 0.2rem; }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tierSelect = document.getElementById('tier');
            var galleryInput = document.getElementById('images');
            var galleryHelp = document.getElementById('gallery-help');
            var tierHelp = document.getElementById('tier-help');

            function updateTierRules() {
                var tier = tierSelect.value;
                var limits = { community: 0, starter: 0, growth: 3, premium: 5 };
                var limit = limits[tier];
                galleryInput.disabled = limit === 0;
                galleryHelp.textContent = limit === 0
                    ? 'This tier uses one logo only and has no gallery photos.'
                    : 'You can upload up to ' + limit + ' gallery photos for this tier.';
                tierHelp.textContent = tier === 'community'
                    ? 'Legacy directory listing without a paid MBN badge.'
                    : tier.charAt(0).toUpperCase() + tier.slice(1) + ' listings receive an Official Halal Kiwi Partner badge.';
            }

            tierSelect.addEventListener('change', updateTierRules);
            updateTierRules();
        });
    </script>
@endpush
