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
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="activeToggle" name="active" value="1"
                                                                    {{ $notification['active'] ? 'checked' : '' }}>
                                                                <label class="custom-control-label" for="activeToggle">Show notification dialog to users</label>
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

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
    });
</script>
@endpush
