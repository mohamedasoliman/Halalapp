@extends('admin.layouts.app')

@php
    $isEdit = $index !== null;
    $rawTier = strtolower($business['Tier'] ?? 'free');
    $tier = match($rawTier) {
        'gold', 'premium' => 'premium',
        'silver', 'featured', 'growth' => 'growth',
        'verified', 'starter' => 'starter',
        default => 'free',
    };
    $hours = $business['hours'] ?? [];
    $active = !array_key_exists('IsActive', $business) || (bool) $business['IsActive'];
    $featuredInCarousel = array_key_exists('FeatureInCarousel', $business)
        ? (bool) $business['FeatureInCarousel']
        : in_array($tier, ['growth', 'premium'], true);
    $isServiceArea = (bool) ($business['IsServiceAreaBusiness'] ?? false);
    $businessStatus = $business['BusinessStatus'] ?? 'unknown';
    $dealRows = old('deals');
    if (!is_array($dealRows)) {
        $dealRows = is_array($business['Deals'] ?? null) ? $business['Deals'] : [];
        if ($dealRows === [] && !empty($business['DealTitle'])) {
            $dealRows[] = [
                'Title' => $business['DealTitle'],
                'Description' => $business['DealDescription'] ?? '',
                'Code' => $business['DealCode'] ?? '',
                'Expiry' => $business['DealExpiry'] ?? '',
            ];
        }
    }
    $dealRows = array_values($dealRows);
    while (count($dealRows) < 5) $dealRows[] = [];
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
                                                    <label for="additional_addresses" class="form-label">Additional locations</label>
                                                    <textarea id="additional_addresses" name="additional_addresses" class="form-control" rows="3"
                                                              placeholder="One full address per line">{{ old('additional_addresses', implode("\n", $business['AdditionalAddresses'] ?? [])) }}</textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="google_maps_url" class="form-label">Audited Google Maps URL</label>
                                                    <input id="google_maps_url" name="google_maps_url" type="url" class="form-control"
                                                           placeholder="https://maps.google.com/..."
                                                           value="{{ old('google_maps_url', $business['GoogleMapsUrl'] ?? '') }}">
                                                    <div class="form-check mt-2">
                                                        <input type="hidden" name="is_service_area_business" value="0">
                                                        <input id="is_service_area_business" name="is_service_area_business" type="checkbox" value="1" class="form-check-input"
                                                               @checked((bool) old('is_service_area_business', $isServiceArea))>
                                                        <label for="is_service_area_business" class="form-check-label">Service-area business (do not show directions)</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="menu_url" class="form-label">Menu or services URL</label>
                                                    <input id="menu_url" name="menu_url" type="url" class="form-control"
                                                           data-membership-menu
                                                           placeholder="https://"
                                                           value="{{ old('menu_url', $business['MenuUrl'] ?? '') }}">
                                                    <small id="menu-help" class="text-muted">Menus are available on Growth and Premium.</small>
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
                                                    <label for="alternate_phone" class="form-label">Alternate phone</label>
                                                    <input id="alternate_phone" name="alternate_phone" class="form-control" value="{{ old('alternate_phone', $business['AlternatePhone'] ?? '') }}">
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
                                        <div class="card-header"><h5>Trading status and audit</h5></div>
                                        <div class="card-block">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label for="business_status" class="form-label">Trading status *</label>
                                                    <select id="business_status" name="business_status" class="form-control" required>
                                                        @foreach([
                                                            'operational' => 'Operational',
                                                            'temporarily_closed' => 'Temporarily closed',
                                                            'unknown' => 'Not confirmed',
                                                            'review_required' => 'Conflicting evidence - review required',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" @selected(old('business_status', $businessStatus) === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="last_reviewed_at" class="form-label">Last reviewed</label>
                                                    <input id="last_reviewed_at" name="last_reviewed_at" type="date" class="form-control"
                                                           value="{{ old('last_reviewed_at', $business['LastReviewedAt'] ?? now()->toDateString()) }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="status_note" class="form-label">Public status note</label>
                                                    <textarea id="status_note" name="status_note" class="form-control" rows="2" maxlength="500">{{ old('status_note', $business['StatusNote'] ?? '') }}</textarea>
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
                                                        <option value="free" @selected(old('tier', $tier) === 'free')>Basic ($2/week)</option>
                                                        <option value="starter" @selected(old('tier', $tier) === 'starter')>Starter ($5/week) - partner priority</option>
                                                        <option value="growth" @selected(old('tier', $tier) === 'growth')>Growth ($15/week) - up to 3 photos</option>
                                                        <option value="premium" @selected(old('tier', $tier) === 'premium')>Premium ($30/week) - up to 5 photos</option>
                                                    </select>
                                                    <small id="tier-help" class="text-muted d-block mt-2"></small>
                                                </div>
                                                <div class="col-md-7">
                                                    <div class="form-check mb-2">
                                                        <input type="hidden" name="is_active" value="0">
                                                        <input id="is_active" name="is_active" type="checkbox" value="1" class="form-check-input"
                                                               @checked((bool) old('is_active', $active))>
                                                        <label for="is_active" class="form-check-label">Visible in the Halal Kiwi app</label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input type="hidden" name="feature_in_carousel" value="0">
                                                        <input id="feature_in_carousel" name="feature_in_carousel" type="checkbox" value="1" class="form-check-input"
                                                               @checked((bool) old('feature_in_carousel', $featuredInCarousel))>
                                                        <label for="feature_in_carousel" class="form-check-label">
                                                            Show in the Businesses featured carousel
                                                        </label>
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
                                        <div class="card-header"><h5>Halal Kiwi Deals</h5></div>
                                        <div class="card-block">
                                            <p id="deal-help" class="text-muted">Starter allows 1 active deal, Growth 3, and Premium 5.</p>
                                            @foreach($dealRows as $dealIndex => $deal)
                                                <div class="border rounded p-3 mb-3" data-deal-slot="{{ $dealIndex }}">
                                                    <h6>Deal {{ $dealIndex + 1 }}</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label for="deals_{{ $dealIndex }}_title" class="form-label">Offer title</label>
                                                            <input id="deals_{{ $dealIndex }}_title" name="deals[{{ $dealIndex }}][title]" class="form-control" data-membership-deal maxlength="120"
                                                                   placeholder="20% off your first service"
                                                                   value="{{ old("deals.{$dealIndex}.title", $deal['Title'] ?? $deal['title'] ?? '') }}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="deals_{{ $dealIndex }}_code" class="form-label">Promo code</label>
                                                            <input id="deals_{{ $dealIndex }}_code" name="deals[{{ $dealIndex }}][code]" class="form-control" data-membership-deal maxlength="50"
                                                                   value="{{ old("deals.{$dealIndex}.code", $deal['Code'] ?? $deal['code'] ?? '') }}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="deals_{{ $dealIndex }}_expiry" class="form-label">End date</label>
                                                            <input id="deals_{{ $dealIndex }}_expiry" name="deals[{{ $dealIndex }}][expiry]" type="date" class="form-control" data-membership-deal
                                                                   value="{{ old("deals.{$dealIndex}.expiry", $deal['Expiry'] ?? $deal['expiry'] ?? '') }}">
                                                        </div>
                                                        <div class="col-12">
                                                            <label for="deals_{{ $dealIndex }}_description" class="form-label">Offer details</label>
                                                            <textarea id="deals_{{ $dealIndex }}_description" name="deals[{{ $dealIndex }}][description]" class="form-control" data-membership-deal rows="2" maxlength="500">{{ old("deals.{$dealIndex}.description", $deal['Description'] ?? $deal['description'] ?? '') }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
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
            var carouselInput = document.getElementById('feature_in_carousel');
            var menuInput = document.getElementById('menu_url');
            var menuHelp = document.getElementById('menu-help');
            var dealHelp = document.getElementById('deal-help');
            var dealSlots = document.querySelectorAll('[data-deal-slot]');

            function updateTierRules() {
                var tier = tierSelect.value;
                var limits = { free: 0, starter: 0, growth: 3, premium: 5 };
                var dealLimits = { free: 0, starter: 1, growth: 3, premium: 5 };
                var limit = limits[tier];
                var dealLimit = dealLimits[tier];
                var hasPromotions = tier === 'growth' || tier === 'premium';
                var hasMenu = tier === 'growth' || tier === 'premium';
                galleryInput.disabled = limit === 0;
                carouselInput.disabled = !hasPromotions;
                menuInput.disabled = !hasMenu;
                dealSlots.forEach(function (slot) {
                    var enabled = Number(slot.dataset.dealSlot) < dealLimit;
                    slot.hidden = !enabled;
                    slot.querySelectorAll('[data-membership-deal]').forEach(function (input) {
                        input.disabled = !enabled;
                    });
                });
                galleryHelp.textContent = limit === 0
                    ? 'This tier uses one logo only and has no gallery photos.'
                    : 'You can upload up to ' + limit + ' gallery photos for this tier.';
                tierHelp.textContent = tier === 'free'
                    ? 'Basic $2/week listing with complete public business details and no partner badge.'
                    : tier.charAt(0).toUpperCase() + tier.slice(1) + ' listings automatically receive their matching Halal Kiwi Partner badge.';
                dealHelp.textContent = dealLimit === 0
                    ? 'Deals are available on Starter, Growth, and Premium tiers.'
                    : 'This tier can publish up to ' + dealLimit + ' active deal' + (dealLimit === 1 ? '' : 's') + '. Leave a title empty to remove that slot.';
                menuHelp.textContent = hasMenu
                    ? 'This tier can publish a menu or services link.'
                    : 'Menus are available on Growth and Premium tiers.';
            }

            tierSelect.addEventListener('change', updateTierRules);
            updateTierRules();
        });
    </script>
@endpush
