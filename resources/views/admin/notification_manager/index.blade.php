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
                                    <h4>Notification Manager</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Notifications</a></li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Manage</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                <div class="row">
                                    {{-- Notification Form --}}
                                    <div class="col-md-7">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Edit Notification</h5>
                                                <span class="badge badge-info float-right">Version: {{ $notification['notificationVersion'] }}</span>
                                            </div>
                                            <div class="card-block">
                                                <form action="{{ route('notification.manager.update') }}" method="POST" enctype="multipart/form-data">
                                                    @csrf

                                                    {{-- Active Toggle --}}
                                                    <div class="form-group row">
                                                        <label class="col-sm-3 col-form-label">Active</label>
                                                        <div class="col-sm-9">
                                                            <div class="form-check form-switch">
                                                                <input type="checkbox" class="form-check-input" id="activeToggle" name="active" value="1" role="switch"
                                                                    {{ $notification['active'] ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="activeToggle">Show notification dialog to users</label>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Message Text --}}
                                                    <div class="form-group row">
                                                        <label class="col-sm-3 col-form-label">Notification Text</label>
                                                        <div class="col-sm-9">
                                                            <textarea class="form-control" name="notification_text" rows="3"
                                                                placeholder="e.g. Much Moore Products are now HALAL suitable!">{{ $notification['notificationText'] }}</textarea>
                                                            <small class="form-text text-muted">The main message shown in the notification dialog.</small>
                                                        </div>
                                                    </div>

                                                    {{-- Button Text --}}
                                                    <div class="form-group row">
                                                        <label class="col-sm-3 col-form-label">Button Text</label>
                                                        <div class="col-sm-9">
                                                            <input type="text" class="form-control" name="button_text"
                                                                value="{{ $notification['notificationButtonText'] }}"
                                                                placeholder="e.g. View, Learn More, Open">
                                                            <small class="form-text text-muted">Text on the action button. Leave empty for no button.</small>
                                                        </div>
                                                    </div>

                                                    {{-- Link Type --}}
                                                    <div class="form-group row">
                                                        <label class="col-sm-3 col-form-label">Link Type</label>
                                                        <div class="col-sm-9">
                                                            <select class="form-control" name="link_type" id="linkType">
                                                                <option value="product" {{ $notification['linkType'] === 'product' ? 'selected' : '' }}>Product (Barcode)</option>
                                                                <option value="restaurant" {{ $notification['linkType'] === 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                                                                <option value="masjid" {{ $notification['linkType'] === 'masjid' ? 'selected' : '' }}>Masjid</option>
                                                                <option value="screen" {{ $notification['linkType'] === 'screen' ? 'selected' : '' }}>Screen (Route Path)</option>
                                                                <option value="url" {{ $notification['linkType'] === 'url' ? 'selected' : '' }}>External URL</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    {{-- Link Target (text input) --}}
                                                    <div class="form-group row" id="linkTargetGroup">
                                                        <label class="col-sm-3 col-form-label" id="linkTargetLabel">Link Target</label>
                                                        <div class="col-sm-9">
                                                            <input type="text" class="form-control" name="link_target" id="linkTarget"
                                                                value="{{ $notification['linkTarget'] }}"
                                                                placeholder="Enter barcode number">
                                                            <small class="form-text text-muted" id="linkTargetHelp">Enter the product barcode number.</small>
                                                        </div>
                                                    </div>

                                                    {{-- Restaurant Dropdown (shown only when link type is restaurant) --}}
                                                    <div class="form-group row" id="restaurantDropdownGroup" style="display:none;">
                                                        <label class="col-sm-3 col-form-label">Restaurant</label>
                                                        <div class="col-sm-9">
                                                            <select class="form-control" id="restaurantDropdown">
                                                                <option value="">-- Select a restaurant --</option>
                                                                @foreach($restaurantNames as $name)
                                                                    <option value="{{ $name }}" {{ ($notification['linkTarget'] ?? '') === $name ? 'selected' : '' }}>{{ $name }}</option>
                                                                @endforeach
                                                            </select>
                                                            <small class="form-text text-muted">Select the restaurant to link to.</small>
                                                        </div>
                                                    </div>

                                                    {{-- Image Upload --}}
                                                    <div class="form-group row">
                                                        <label class="col-sm-3 col-form-label">Image (Optional)</label>
                                                        <div class="col-sm-9">
                                                            @if(!empty($notification['notificationImage']))
                                                                <div class="mb-2">
                                                                    <img src="{{ $notification['notificationImage'] }}" alt="Current notification image"
                                                                        style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid #ddd;">
                                                                    <div class="mt-1">
                                                                        <label class="text-danger" style="cursor: pointer;">
                                                                            <input type="checkbox" name="remove_image" value="1"> Remove image
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <input type="file" class="form-control" name="notification_image" accept="image/*">
                                                            <small class="form-text text-muted">Optional image to display in the notification dialog. Max 5MB.</small>
                                                        </div>
                                                    </div>

                                                    <div class="form-group row">
                                                        <div class="col-sm-9 offset-sm-3">
                                                            <button type="submit" class="btn btn-primary btn-round">
                                                                <i class="ti-save"></i> Save & Increment Version
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Preview --}}
                                    <div class="col-md-5">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Preview</h5>
                                                <span class="text-muted float-right">How it looks in the app</span>
                                            </div>
                                            <div class="card-block">
                                                <div id="previewContainer" style="background: #f5f5f5; border-radius: 12px; padding: 24px; text-align: center; min-height: 200px;">
                                                    <div id="previewInactive" style="display: {{ $notification['active'] ? 'none' : 'block' }};">
                                                        <i class="ti-bell" style="font-size: 48px; color: #ccc;"></i>
                                                        <p class="text-muted mt-3">Notification is inactive.<br>No dialog will be shown to users.</p>
                                                    </div>
                                                    <div id="previewActive" style="display: {{ $notification['active'] ? 'block' : 'none' }};">
                                                        <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); max-width: 300px; margin: 0 auto;">
                                                            <div id="previewImageContainer" style="margin-bottom: 12px; {{ empty($notification['notificationImage']) ? 'display:none;' : '' }}">
                                                                <img id="previewImage" src="{{ $notification['notificationImage'] ?? '' }}"
                                                                    style="max-width: 100%; max-height: 120px; border-radius: 8px;">
                                                            </div>
                                                            <p id="previewText" style="font-size: 14px; color: #333; margin-bottom: 16px;">
                                                                {{ $notification['notificationText'] ?: 'Your notification text here...' }}
                                                            </p>
                                                            <div id="previewButtonContainer" style="{{ empty($notification['notificationButtonText']) ? 'display:none;' : '' }}">
                                                                <span id="previewButton" style="display: inline-block; background: #4CAF50; color: white; padding: 8px 24px; border-radius: 20px; font-size: 13px;">
                                                                    {{ $notification['notificationButtonText'] ?: 'View' }}
                                                                </span>
                                                            </div>
                                                            <div style="margin-top: 8px;">
                                                                <small id="previewPath" class="text-muted">{{ $notification['notificationButton'] }}</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <h6>Current Values in JSON</h6>
                                                    <table class="table table-sm table-bordered">
                                                        <tr>
                                                            <td class="text-muted" style="width: 40%;">notificationVersion</td>
                                                            <td><strong>{{ $notification['notificationVersion'] }}</strong></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted">notificationText</td>
                                                            <td>{{ $notification['notificationText'] ?: '(empty)' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted">notificationButton</td>
                                                            <td><code>{{ $notification['notificationButton'] ?: '(empty)' }}</code></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted">notificationButtonText</td>
                                                            <td>{{ $notification['notificationButtonText'] ?: '(empty)' }}</td>
                                                        </tr>
                                                        @if(!empty($notification['notificationImage']))
                                                        <tr>
                                                            <td class="text-muted">notificationImage</td>
                                                            <td style="word-break: break-all;"><code>{{ $notification['notificationImage'] }}</code></td>
                                                        </tr>
                                                        @endif
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- Users Count --}}
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Users Count</h5>
                                                <span class="text-muted float-right">Displayed in the app</span>
                                            </div>
                                            <div class="card-block">
                                                <form action="{{ route('users.count.update') }}" method="POST">
                                                    @csrf
                                                    <div class="form-group row">
                                                        <label class="col-sm-3 col-form-label">Users</label>
                                                        <div class="col-sm-6">
                                                            <input type="text" class="form-control" name="users_count" value="{{ $usersCount }}" placeholder="e.g. 20,000">
                                                            <small class="form-text text-muted">The user count displayed in the app (e.g. "20,000").</small>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <button type="submit" class="btn btn-primary btn-round btn-sm">
                                                                <i class="ti-save"></i> Save
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Sticky Sponsored Banner --}}
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Sticky Sponsored Banner</h5>
                                                <span class="badge {{ $stickyAd['active'] ? 'badge-success' : 'badge-secondary' }} float-right">
                                                    {{ $stickyAd['active'] ? 'Active' : 'Inactive' }} · Version {{ $stickyAd['version'] }}
                                                </span>
                                            </div>
                                            <div class="card-block">
                                                <div class="row">
                                                    <div class="col-lg-8">
                                                        <form action="{{ route('sticky-ad.update') }}" method="POST" enctype="multipart/form-data">
                                                            @csrf

                                                            <div class="form-group">
                                                                <div class="form-check form-switch">
                                                                    <input type="checkbox" class="form-check-input" id="stickyAdActive" name="active" value="1" role="switch"
                                                                        {{ old('active', $stickyAd['active']) ? 'checked' : '' }}>
                                                                    <label class="form-check-label" for="stickyAdActive">Show this banner on the app homepage</label>
                                                                </div>
                                                            </div>

                                                            <div class="form-row">
                                                                <div class="form-group col-md-5">
                                                                    <label for="stickySponsorName">Sponsor name</label>
                                                                    <input id="stickySponsorName" type="text" class="form-control" name="sponsor_name"
                                                                        maxlength="60" value="{{ old('sponsor_name', $stickyAd['sponsorName']) }}"
                                                                        placeholder="e.g. MEZBAAN">
                                                                </div>
                                                                <div class="form-group col-md-5">
                                                                    <label for="stickyMessage">Short offer or message</label>
                                                                    <input id="stickyMessage" type="text" class="form-control" name="message"
                                                                        maxlength="90" value="{{ old('message', $stickyAd['message']) }}"
                                                                        placeholder="e.g. Authentic Indian cuisine in Auckland">
                                                                </div>
                                                                <div class="form-group col-md-2">
                                                                    <label for="stickyButtonText">Button</label>
                                                                    <input id="stickyButtonText" type="text" class="form-control" name="button_text"
                                                                        maxlength="16" value="{{ old('button_text', $stickyAd['buttonText']) }}"
                                                                        placeholder="View">
                                                                </div>
                                                            </div>

                                                            <div class="form-row">
                                                                <div class="form-group col-md-4">
                                                                    <label for="stickyDestinationType">Destination</label>
                                                                    <select id="stickyDestinationType" class="form-control" name="destination_type">
                                                                        @foreach([
                                                                            'business' => 'Business profile',
                                                                            'restaurant' => 'Restaurant profile',
                                                                            'screen' => 'App screen',
                                                                            'url' => 'External website',
                                                                        ] as $value => $label)
                                                                            <option value="{{ $value }}" {{ old('destination_type', $stickyAd['destinationType']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="form-group col-md-8">
                                                                    <label id="stickyTargetLabel" for="stickyDestinationTarget">Business</label>
                                                                    <input id="stickyDestinationTarget" type="text" class="form-control" name="destination_target"
                                                                        maxlength="500" value="{{ old('destination_target', $stickyAd['destinationTarget']) }}"
                                                                        list="stickyBusinessTargets" placeholder="Select or type the exact business name">
                                                                    <small id="stickyTargetHelp" class="form-text text-muted">Choose the exact business name used in the app.</small>
                                                                </div>
                                                            </div>

                                                            <datalist id="stickyBusinessTargets">
                                                                @foreach($businessNames as $name)
                                                                    <option value="{{ $name }}"></option>
                                                                @endforeach
                                                            </datalist>
                                                            <datalist id="stickyRestaurantTargets">
                                                                @foreach($restaurantNames as $name)
                                                                    <option value="{{ $name }}"></option>
                                                                @endforeach
                                                            </datalist>
                                                            <datalist id="stickyScreenTargets">
                                                                <option value="/business"></option>
                                                                <option value="/restaurants"></option>
                                                                <option value="/masjid"></option>
                                                                <option value="/barcode"></option>
                                                                <option value="/halalList"></option>
                                                                <option value="/contact"></option>
                                                            </datalist>

                                                            <div class="form-row">
                                                                <div class="form-group col-md-8">
                                                                    <label for="stickyLogoUrl">Logo image URL (optional)</label>
                                                                    <input id="stickyLogoUrl" type="url" class="form-control" name="logo_url"
                                                                        maxlength="500" value="{{ old('logo_url', $stickyAd['logoUrl']) }}"
                                                                        placeholder="https://example.com/business-logo.png">
                                                                    <small class="form-text text-muted">Use a direct public HTTP or HTTPS image link.</small>
                                                                </div>
                                                                <div class="form-group col-md-4">
                                                                    <label for="stickyLogo">Square logo (optional)</label>
                                                                    <input id="stickyLogo" type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/webp">
                                                                    <small class="form-text text-muted">A square logo works best. Maximum 2MB. Upload takes priority over URL.</small>
                                                                </div>
                                                            </div>

                                                            <div class="form-row">
                                                                <div class="form-group col-md-3">
                                                                    <label for="stickyStartDate">Start date</label>
                                                                    <input id="stickyStartDate" type="date" class="form-control" name="start_date"
                                                                        value="{{ old('start_date', $stickyAd['startDate']) }}">
                                                                </div>
                                                                <div class="form-group col-md-3">
                                                                    <label for="stickyEndDate">End date</label>
                                                                    <input id="stickyEndDate" type="date" class="form-control" name="end_date"
                                                                        value="{{ old('end_date', $stickyAd['endDate']) }}">
                                                                </div>
                                                                <div class="form-group col-md-2 d-flex align-items-end">
                                                                    <button type="submit" class="btn btn-primary btn-block">
                                                                        <i class="ti-save"></i> Save
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            @if(!empty($stickyAd['logoUrl']))
                                                                <div class="custom-control custom-checkbox mb-3">
                                                                    <input type="checkbox" class="custom-control-input" id="stickyRemoveLogo" name="remove_logo" value="1">
                                                                    <label class="custom-control-label" for="stickyRemoveLogo">Remove the current logo</label>
                                                                </div>
                                                            @endif
                                                        </form>
                                                    </div>

                                                    <div class="col-lg-4">
                                                        <label class="d-block">App preview</label>
                                                        <div class="sticky-ad-preview">
                                                            <div class="sticky-ad-preview-logo">
                                                                @if(!empty($stickyAd['logoUrl']))
                                                                    <img src="{{ $stickyAd['logoUrl'] }}" alt="Sponsor logo">
                                                                @else
                                                                    <i class="ti-shopping-cart"></i>
                                                                @endif
                                                            </div>
                                                            <div class="sticky-ad-preview-copy">
                                                                <small>SPONSORED</small>
                                                                <strong id="stickyPreviewSponsor">{{ $stickyAd['sponsorName'] ?: 'Sponsor name' }}</strong>
                                                                <span id="stickyPreviewMessage">{{ $stickyAd['message'] ?: 'Your short offer appears here' }}</span>
                                                            </div>
                                                            <span id="stickyPreviewButton" class="sticky-ad-preview-button">{{ $stickyAd['buttonText'] ?: 'View' }}</span>
                                                            <i class="ti-close sticky-ad-preview-close"></i>
                                                        </div>
                                                        <small class="form-text text-muted mt-2">Users can dismiss the banner for the rest of the day.</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Ads Manager --}}
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Ads Manager</h5>
                                                <span class="text-muted float-right">{{ count($ads) }} ad(s)</span>
                                            </div>
                                            <div class="card-block">
                                                {{-- Existing Ads --}}
                                                @if(count($ads) > 0)
                                                    <form action="{{ route('ads.update') }}" method="POST" enctype="multipart/form-data">
                                                        @csrf
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-bordered table-striped">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width:80px;">#</th>
                                                                        <th style="width:120px;">Preview</th>
                                                                        <th>Image URL</th>
                                                                        <th>Link URL</th>
                                                                        <th style="width:100px;">Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($ads as $i => $ad)
                                                                        <tr>
                                                                            <td>{{ $i + 1 }}</td>
                                                                            <td>
                                                                                @if(!empty($ad['adImageUrl']))
                                                                                    <img src="{{ $ad['adImageUrl'] }}" alt="Ad {{ $i + 1 }}" style="max-width: 100px; max-height: 60px; border-radius: 4px;">
                                                                                @else
                                                                                    <span class="text-muted">No image</span>
                                                                                @endif
                                                                            </td>
                                                                            <td>
                                                                                <input type="text" class="form-control form-control-sm" name="ad_image_urls[]" value="{{ $ad['adImageUrl'] ?? '' }}">
                                                                            </td>
                                                                            <td>
                                                                                <input type="text" class="form-control form-control-sm" name="ad_link_urls[]" value="{{ $ad['adLinkUrl'] ?? '' }}">
                                                                            </td>
                                                                            <td>
                                                                                <button type="button" class="btn btn-sm btn-danger delete-ad-btn" data-index="{{ $i }}">
                                                                                    <i class="ti-trash"></i>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary btn-round btn-sm mb-3">
                                                            <i class="ti-save"></i> Save All Ads
                                                        </button>
                                                    </form>
                                                @else
                                                    <p class="text-muted mb-3">No ads configured yet.</p>
                                                @endif

                                                <hr>

                                                {{-- Add New Ad --}}
                                                <h6>Add New Ad</h6>
                                                <form action="{{ route('ads.update') }}" method="POST" enctype="multipart/form-data">
                                                    @csrf
                                                    {{-- Preserve existing ads --}}
                                                    @foreach($ads as $ad)
                                                        <input type="hidden" name="ad_image_urls[]" value="{{ $ad['adImageUrl'] ?? '' }}">
                                                        <input type="hidden" name="ad_link_urls[]" value="{{ $ad['adLinkUrl'] ?? '' }}">
                                                    @endforeach

                                                    <div class="form-group row">
                                                        <label class="col-sm-2 col-form-label">Ad Image</label>
                                                        <div class="col-sm-4">
                                                            <input type="file" class="form-control" name="new_ad_image" accept="image/*">
                                                            <small class="form-text text-muted">Upload an image (max 5MB). Will be stored in data/images/.</small>
                                                        </div>
                                                        <label class="col-sm-1 col-form-label">Link</label>
                                                        <div class="col-sm-3">
                                                            <input type="text" class="form-control" name="new_ad_link" placeholder="https://example.com">
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <button type="submit" class="btn btn-success btn-round btn-sm">
                                                                <i class="ti-plus"></i> Add Ad
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                {{-- Scan Ads (shown after barcode scan) --}}
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Scan Result Ads</h5>
                                                <span class="text-muted float-right">Shown after barcode scan results — random ad each time</span>
                                            </div>
                                            <div class="card-block">
                                                <form action="{{ route('scan.ads.update') }}" method="POST" enctype="multipart/form-data">
                                                    @csrf

                                                    <div class="form-group mb-3">
                                                        <div class="form-check form-switch">
                                                            <input type="checkbox" class="form-check-input" id="scanAdsToggle" name="scan_ads_active" value="1" role="switch"
                                                                {{ $scanAdsActive ? 'checked' : '' }}>
                                                            <label class="form-check-label" for="scanAdsToggle">Show ads after barcode scan results</label>
                                                        </div>
                                                    </div>

                                                    @if($scanAdsActive && count($scanAds) === 0)
                                                        <div class="alert alert-warning" role="alert">
                                                            Scan ads are active, but no ad is configured. Add an image below before an ad can appear in the app.
                                                        </div>
                                                    @endif

                                                    @if(count($scanAds) > 0)
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-bordered table-striped">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width:80px;">#</th>
                                                                        <th style="width:120px;">Preview</th>
                                                                        <th>Image URL</th>
                                                                        <th>Link URL</th>
                                                                        <th style="width:100px;">Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($scanAds as $i => $ad)
                                                                        <tr>
                                                                            <td>{{ $i + 1 }}</td>
                                                                            <td>
                                                                                @if(!empty($ad['adImageUrl']))
                                                                                    <img src="{{ $ad['adImageUrl'] }}" alt="Scan Ad" style="max-width: 100px; max-height: 60px; border-radius: 4px;">
                                                                                @endif
                                                                            </td>
                                                                            <td><input type="text" class="form-control form-control-sm" name="ad_image_urls[]" value="{{ $ad['adImageUrl'] ?? '' }}"></td>
                                                                            <td><input type="text" class="form-control form-control-sm" name="ad_link_urls[]" value="{{ $ad['adLinkUrl'] ?? '' }}"></td>
                                                                            <td>
                                                                                <button type="button" class="btn btn-sm btn-danger delete-scan-ad-btn" data-index="{{ $i }}">
                                                                                    <i class="ti-trash"></i>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @else
                                                        <p class="text-muted mb-3">No scan ads yet.</p>
                                                    @endif

                                                    <button type="submit" class="btn btn-primary btn-round btn-sm mb-3">
                                                        <i class="ti-save"></i> Save Scan Ads
                                                    </button>
                                                </form>

                                                <hr>
                                                <h6>Add New Scan Ad</h6>
                                                <form action="{{ route('scan.ads.update') }}" method="POST" enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="hidden" name="scan_ads_active" value="{{ $scanAdsActive ? '1' : '' }}">
                                                    @foreach($scanAds as $ad)
                                                        <input type="hidden" name="ad_image_urls[]" value="{{ $ad['adImageUrl'] ?? '' }}">
                                                        <input type="hidden" name="ad_link_urls[]" value="{{ $ad['adLinkUrl'] ?? '' }}">
                                                    @endforeach
                                                    <div class="form-group row">
                                                        <label class="col-sm-2 col-form-label">Image</label>
                                                        <div class="col-sm-4">
                                                            <input type="file" class="form-control" name="new_ad_image" accept="image/*">
                                                        </div>
                                                        <label class="col-sm-1 col-form-label">Link</label>
                                                        <div class="col-sm-3">
                                                            <input type="text" class="form-control" name="new_ad_link" placeholder="https://...">
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <button type="submit" class="btn btn-success btn-round btn-sm"><i class="ti-plus"></i> Add</button>
                                                        </div>
                                                    </div>
                                                </form>
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
        </div>
    </div>
@endsection

@push('styles')
<style>
    .sticky-ad-preview {
        align-items: center;
        background: #fff;
        border: 1px solid rgba(0, 84, 80, .42);
        border-radius: 8px;
        box-shadow: 0 7px 18px rgba(20, 35, 35, .14);
        display: flex;
        gap: 9px;
        min-height: 68px;
        padding: 8px 6px 8px 9px;
        width: 100%;
    }
    .sticky-ad-preview-logo {
        align-items: center;
        background: #e8f3f1;
        border-radius: 6px;
        color: #005450;
        display: flex;
        flex: 0 0 42px;
        height: 42px;
        justify-content: center;
        overflow: hidden;
    }
    .sticky-ad-preview-logo img { height: 100%; object-fit: cover; width: 100%; }
    .sticky-ad-preview-copy { display: flex; flex: 1; flex-direction: column; min-width: 0; }
    .sticky-ad-preview-copy small { color: #b08c00; font-size: 8px; font-weight: 800; }
    .sticky-ad-preview-copy strong,
    .sticky-ad-preview-copy span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sticky-ad-preview-copy strong { color: #17212b; font-size: 12px; }
    .sticky-ad-preview-copy span { color: #718096; font-size: 10px; }
    .sticky-ad-preview-button { color: #005450; font-size: 10px; font-weight: 800; padding: 10px 3px; }
    .sticky-ad-preview-close { color: #5f6c72; font-size: 13px; padding: 10px 4px; }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const linkType = document.getElementById('linkType');
        const linkTarget = document.getElementById('linkTarget');
        const linkTargetLabel = document.getElementById('linkTargetLabel');
        const linkTargetHelp = document.getElementById('linkTargetHelp');
        const linkTargetGroup = document.getElementById('linkTargetGroup');
        const activeToggle = document.getElementById('activeToggle');

        // Preview elements
        const previewActive = document.getElementById('previewActive');
        const previewInactive = document.getElementById('previewInactive');
        const previewText = document.getElementById('previewText');
        const previewButton = document.getElementById('previewButton');
        const previewButtonContainer = document.getElementById('previewButtonContainer');
        const previewPath = document.getElementById('previewPath');

        const linkTypeConfig = {
            product: {
                label: 'Barcode',
                placeholder: 'e.g. 9416312206053',
                help: 'Enter the product barcode number.',
                show: true
            },
            restaurant: {
                label: 'Restaurant Name',
                placeholder: 'e.g. Pizza Haven',
                help: 'Enter the restaurant name exactly as it appears in the app.',
                show: true
            },
            masjid: {
                label: 'Link Target',
                placeholder: '',
                help: 'Opens the Masjid screen directly. No target needed.',
                show: false
            },
            screen: {
                label: 'Route Path',
                placeholder: 'e.g. /halalList, /restaurants, /quran',
                help: 'Enter the GoRouter path. Common routes: /halalList, /restaurants, /quran, /masjid',
                show: true
            },
            url: {
                label: 'URL',
                placeholder: 'e.g. https://example.com',
                help: 'Enter the full URL including https://',
                show: true
            }
        };

        const restaurantDropdownGroup = document.getElementById('restaurantDropdownGroup');
        const restaurantDropdown = document.getElementById('restaurantDropdown');

        // Sync restaurant dropdown to link_target input
        restaurantDropdown.addEventListener('change', function() {
            linkTarget.value = this.value;
            updatePreviewPath();
        });

        function updateLinkTypeUI() {
            const config = linkTypeConfig[linkType.value];
            const isRestaurant = linkType.value === 'restaurant';

            linkTargetLabel.textContent = config.label;
            linkTarget.placeholder = config.placeholder;
            linkTargetHelp.textContent = config.help;

            // Show restaurant dropdown instead of text input
            if (isRestaurant) {
                linkTargetGroup.style.display = 'none';
                restaurantDropdownGroup.style.display = '';
                linkTarget.value = restaurantDropdown.value;
            } else if (config.show) {
                linkTargetGroup.style.display = '';
                restaurantDropdownGroup.style.display = 'none';
            } else {
                linkTargetGroup.style.display = 'none';
                restaurantDropdownGroup.style.display = 'none';
                linkTarget.value = '';
            }

            updatePreviewPath();
        }

        function buildPath() {
            const type = linkType.value;
            const target = linkTarget.value.trim();

            switch (type) {
                case 'product': return target ? '/barcode/product/' + target : '';
                case 'restaurant': return target ? '/restaurant/' + target : '';
                case 'masjid': return '/masjid';
                case 'screen': return target;
                case 'url': return target;
                default: return '';
            }
        }

        function updatePreviewPath() {
            previewPath.textContent = buildPath() || '(no link)';
        }

        function updatePreviewActive() {
            if (activeToggle.checked) {
                previewActive.style.display = 'block';
                previewInactive.style.display = 'none';
            } else {
                previewActive.style.display = 'none';
                previewInactive.style.display = 'block';
            }
        }

        // Event listeners
        linkType.addEventListener('change', updateLinkTypeUI);
        activeToggle.addEventListener('change', updatePreviewActive);

        linkTarget.addEventListener('input', updatePreviewPath);

        // Live preview for notification text
        const notifText = document.querySelector('textarea[name="notification_text"]');
        notifText.addEventListener('input', function() {
            previewText.textContent = this.value || 'Your notification text here...';
        });

        // Live preview for button text
        const buttonText = document.querySelector('input[name="button_text"]');
        buttonText.addEventListener('input', function() {
            previewButton.textContent = this.value || 'View';
            previewButtonContainer.style.display = this.value ? '' : 'none';
        });

        // Live preview for image
        const imageInput = document.querySelector('input[name="notification_image"]');
        imageInput.addEventListener('change', function() {
            const previewImageContainer = document.getElementById('previewImageContainer');
            const previewImage = document.getElementById('previewImage');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewImageContainer.style.display = '';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Initialize
        updateLinkTypeUI();

        const stickyActive = document.getElementById('stickyAdActive');
        const stickyDestinationType = document.getElementById('stickyDestinationType');
        const stickyDestinationTarget = document.getElementById('stickyDestinationTarget');
        const stickyTargetLabel = document.getElementById('stickyTargetLabel');
        const stickyTargetHelp = document.getElementById('stickyTargetHelp');
        const stickySponsorName = document.getElementById('stickySponsorName');
        const stickyMessage = document.getElementById('stickyMessage');
        const stickyButtonText = document.getElementById('stickyButtonText');
        const stickyLogoUrl = document.getElementById('stickyLogoUrl');
        const stickyPreviewLogo = document.querySelector('.sticky-ad-preview-logo');
        const stickyPreview = document.querySelector('.sticky-ad-preview');
        const stickyPreviewSponsor = document.getElementById('stickyPreviewSponsor');
        const stickyPreviewMessage = document.getElementById('stickyPreviewMessage');
        const stickyPreviewButton = document.getElementById('stickyPreviewButton');

        const stickyDestinationConfig = {
            business: {
                label: 'Business',
                placeholder: 'Select or type the exact business name',
                help: 'Choose the exact business name used in the app.',
                list: 'stickyBusinessTargets'
            },
            restaurant: {
                label: 'Restaurant',
                placeholder: 'Select or type the exact restaurant name',
                help: 'Choose the exact restaurant name used in the app.',
                list: 'stickyRestaurantTargets'
            },
            screen: {
                label: 'App route',
                placeholder: 'e.g. /business',
                help: 'Choose a suggested screen or enter another GoRouter path.',
                list: 'stickyScreenTargets'
            },
            url: {
                label: 'External URL',
                placeholder: 'https://example.com',
                help: 'Enter the complete address including https://',
                list: ''
            }
        };

        function updateStickyDestinationUI() {
            const config = stickyDestinationConfig[stickyDestinationType.value];
            stickyTargetLabel.textContent = config.label;
            stickyDestinationTarget.placeholder = config.placeholder;
            stickyTargetHelp.textContent = config.help;
            if (config.list) {
                stickyDestinationTarget.setAttribute('list', config.list);
            } else {
                stickyDestinationTarget.removeAttribute('list');
            }
        }

        function updateStickyPreview() {
            stickyPreviewSponsor.textContent = stickySponsorName.value || 'Sponsor name';
            stickyPreviewMessage.textContent = stickyMessage.value || 'Your short offer appears here';
            stickyPreviewButton.textContent = stickyButtonText.value || 'View';
            stickyPreview.style.opacity = stickyActive.checked ? '1' : '.55';
        }

        function updateStickyLogoPreview(source) {
            stickyPreviewLogo.replaceChildren();
            if (!source) {
                const placeholder = document.createElement('i');
                placeholder.className = 'ti-shopping-cart';
                stickyPreviewLogo.appendChild(placeholder);
                return;
            }

            const image = document.createElement('img');
            image.src = source;
            image.alt = 'Sponsor logo';
            image.addEventListener('error', function() {
                updateStickyLogoPreview('');
            }, { once: true });
            stickyPreviewLogo.appendChild(image);
        }

        stickyDestinationType.addEventListener('change', updateStickyDestinationUI);
        stickyActive.addEventListener('change', updateStickyPreview);
        stickySponsorName.addEventListener('input', updateStickyPreview);
        stickyMessage.addEventListener('input', updateStickyPreview);
        stickyButtonText.addEventListener('input', updateStickyPreview);
        stickyLogoUrl.addEventListener('input', function() {
            updateStickyLogoPreview(this.value.trim());
        });
        document.getElementById('stickyLogo').addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            const reader = new FileReader();
            reader.onload = function(event) {
                updateStickyLogoPreview(event.target.result);
            };
            reader.readAsDataURL(this.files[0]);
        });
        updateStickyDestinationUI();
        updateStickyPreview();

        // Ad delete buttons
        document.querySelectorAll('.delete-ad-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this ad?')) return;
                var index = this.getAttribute('data-index');
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("admin/ads") }}/' + index;
                form.innerHTML = '@csrf' + '<input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(form);
                form.submit();
            });
        });

        // Scan ad delete buttons
        document.querySelectorAll('.delete-scan-ad-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this scan ad?')) return;
                var index = this.getAttribute('data-index');
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("admin/scan-ads") }}/' + index;
                form.innerHTML = '@csrf' + '<input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(form);
                form.submit();
            });
        });
    });
</script>
@endpush
