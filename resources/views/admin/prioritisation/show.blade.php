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
                                                                    'dead_end' => 'badge-dark',
                                                                    default => 'badge-light',
                                                                };
                                                            @endphp
                                                            <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', ucfirst($request->status)) }}</span>
                                                        </td>
                                                    </tr>
                                                    <tr><th>Notes</th><td>{{ $request->notes ?? '-' }}</td></tr>
                                                    <tr><th>Submitted</th><td>{{ $request->created_at?->format('Y-m-d H:i') }}</td></tr>
                                                    <tr><th>Information Replies</th><td>{{ $request->information_reply_count ?? 0 }}</td></tr>
                                                    <tr><th>Information Received</th><td>{{ $request->information_received_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                                                </table>

                                                @php
                                                    $requestPhotos = $request->photos;
                                                    if ($requestPhotos->isEmpty() && $request->photo_path) {
                                                        $requestPhotos = collect([(object) [
                                                            'path' => $request->photo_path,
                                                            'original_name' => null,
                                                            'created_at' => $request->created_at,
                                                        ]]);
                                                    }
                                                @endphp
                                                @if($requestPhotos->isNotEmpty())
                                                    <h6 class="mt-3">User Photos ({{ $requestPhotos->count() }})</h6>
                                                    <div class="row">
                                                        @foreach($requestPhotos as $photo)
                                                            @php
                                                                $photoUrl = isset($photo->id)
                                                                    ? route('prioritisation.photo', [$request->id, $photo->id])
                                                                    : Storage::url($photo->path);
                                                            @endphp
                                                            <div class="col-sm-6 col-lg-4 mb-3">
                                                                <a href="{{ $photoUrl }}" target="_blank" rel="noopener">
                                                                    <img
                                                                        src="{{ $photoUrl }}"
                                                                        alt="{{ $photo->original_name ?: 'Submitted product photo' }}"
                                                                        style="width:100%; max-height:240px; object-fit:contain; border-radius:8px; border:1px solid #ddd;"
                                                                    >
                                                                </a>
                                                                <small class="d-block text-muted mt-1">
                                                                    {{ $photo->original_name ?: basename($photo->path) }}
                                                                    @if($photo->created_at)
                                                                        · {{ $photo->created_at->format('Y-m-d H:i') }}
                                                                    @endif
                                                                </small>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @if($request->informationReplies->isNotEmpty())
                                                    <h6 class="mt-4">User Information Reply Review ({{ $request->informationReplies->count() }})</h6>
                                                    @foreach($request->informationReplies->sortByDesc('received_at') as $informationReply)
                                                        @php
                                                            $replyBadgeClass = match($informationReply->processing_status) {
                                                                'processed' => 'badge-success',
                                                                'needs_clarification' => 'badge-warning',
                                                                'no_action' => 'badge-secondary',
                                                                'manual_review' => 'badge-danger',
                                                                default => 'badge-info',
                                                            };
                                                        @endphp
                                                        <div class="card mb-3 information-reply-card">
                                                            <div class="card-block">
                                                                <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                                                                    <div>
                                                                        <strong>{{ $informationReply->from_name ?: $informationReply->from_address }}</strong>
                                                                        @if($informationReply->from_name)
                                                                            <small class="text-muted">&lt;{{ $informationReply->from_address }}&gt;</small>
                                                                        @endif
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            {{ $informationReply->received_at?->format('Y-m-d H:i') ?? 'Unknown received time' }}
                                                                            @if($informationReply->delivery?->recipient_email)
                                                                                · matched recipient {{ $informationReply->delivery->recipient_email }}
                                                                                · delivery status {{ $informationReply->delivery->status }}
                                                                            @endif
                                                                        </small>
                                                                    </div>
                                                                    <span class="badge {{ $replyBadgeClass }}">
                                                                        {{ str_replace('_', ' ', ucfirst($informationReply->processing_status)) }}
                                                                    </span>
                                                                </div>

                                                                <table class="table table-sm table-bordered mb-3">
                                                                    <tr>
                                                                        <th style="width:24%">Subject</th>
                                                                        <td>{{ $informationReply->subject }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Message-ID</th>
                                                                        <td><code class="information-reply-identifier">{{ $informationReply->message_id }}</code></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Match</th>
                                                                        <td>
                                                                            {{ $informationReply->match_method ? str_replace('_', ' ', $informationReply->match_method) : 'Unmatched' }}
                                                                            @if($informationReply->match_confidence)
                                                                                <span class="text-muted">({{ $informationReply->match_confidence }})</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                    @if($informationReply->delivery?->reply_reference)
                                                                        <tr>
                                                                            <th>Reference</th>
                                                                            <td><code>{{ $informationReply->delivery->reply_reference }}</code></td>
                                                                        </tr>
                                                                    @endif
                                                                    <tr>
                                                                        <th>Review Notes</th>
                                                                        <td>{{ $informationReply->review_notes ?: 'Pending review' }}</td>
                                                                    </tr>
                                                                </table>

                                                                <details class="mb-3">
                                                                    <summary>View reply body</summary>
                                                                    <div class="information-reply-body mt-2">{{ $informationReply->body }}</div>
                                                                </details>

                                                                @if($informationReply->attachments->isNotEmpty())
                                                                    <h6>Attachments ({{ $informationReply->attachments->count() }})</h6>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-bordered mb-0">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>Name</th>
                                                                                    <th>Detected Type</th>
                                                                                    <th>Validation</th>
                                                                                    <th>Photo</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                @foreach($informationReply->attachments as $attachment)
                                                                                    <tr>
                                                                                        <td>{{ $attachment->original_name }}</td>
                                                                                        <td>
                                                                                            {{ $attachment->detected_mime_type ?: 'Unknown' }}
                                                                                            @if($attachment->width && $attachment->height)
                                                                                                <small class="text-muted d-block">{{ $attachment->width }} × {{ $attachment->height }}</small>
                                                                                            @endif
                                                                                        </td>
                                                                                        <td>
                                                                                            {{ str_replace('_', ' ', ucfirst($attachment->security_status)) }}
                                                                                            @if($attachment->rejection_reason)
                                                                                                <small class="text-danger d-block">{{ $attachment->rejection_reason }}</small>
                                                                                            @endif
                                                                                        </td>
                                                                                        <td>
                                                                                            @if($attachment->photo)
                                                                                                <a
                                                                                                    href="{{ route('prioritisation.photo', [$request->id, $attachment->photo->id]) }}"
                                                                                                    target="_blank"
                                                                                                    rel="noopener"
                                                                                                >View promoted photo</a>
                                                                                            @else
                                                                                                <span class="text-muted">Not promoted</span>
                                                                                            @endif
                                                                                        </td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
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
                                                                @elseif($product->halal_status === '3' || $product->halal_status === 3)
                                                                    <span class="badge badge-info">Mashbooh</span>
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
                                        @if(!in_array($request->status, ['resolved', 'dead_end']))
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
                                                            <option value="dead_end" {{ $request->status === 'dead_end' ? 'selected' : '' }}>Dead End</option>
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
                                                        <label>Internal resolution notes</label>
                                                        <textarea name="notes" class="form-control" rows="3" placeholder="Evidence and audit details (not shown in the app)..."></textarea>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label>User-facing product note <small class="text-muted">(optional)</small></label>
                                                        <textarea name="public_note" class="form-control" rows="2" maxlength="255" placeholder="Short reason only. Do not include dates or proof locations."></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('Are you sure? This will mark the product and notify all watchers.')">Resolve</button>
                                                </form>
                                            </div>
                                        </div>
                                        @elseif($request->status === 'dead_end')
                                        <div class="card">
                                            <div class="card-header"><h5>Dead End</h5></div>
                                            <div class="card-block">
                                                <p><span class="badge badge-dark">Dead End</span></p>
                                                <p class="text-muted">{{ $request->notes }}</p>
                                                <form action="{{ route('prioritisation.status', $request->id) }}" method="POST" class="mt-3">
                                                    @csrf
                                                    <input type="hidden" name="status" value="pending">
                                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Reopen this request?')">Reopen as Pending</button>
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
    .badge-dark { background: #2c3e50; color: #fff; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; }
    .btn-block { width: 100%; }
    .information-reply-card { border: 1px solid #dfe5eb; box-shadow: none; }
    .information-reply-identifier { overflow-wrap: anywhere; white-space: normal; }
    .information-reply-body {
        background: #f7f9fb;
        border: 1px solid #e3e8ee;
        border-radius: 4px;
        max-height: 320px;
        overflow: auto;
        padding: 12px;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
@endpush
