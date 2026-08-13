@extends('admin.layouts.app')

@section('content')
    <div class="pcoded-main-container">
        @include('admin.include.sidebar')
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper support-page">
                            <header class="page-header">
                                <div class="support-ticket-header">
                                    <div>
                                        <a href="{{ route('support.index') }}" class="support-ticket-card__reference"><i class="ti-arrow-left" aria-hidden="true"></i> Back to App Support</a>
                                        <h1>{{ $ticket->subject ?: 'No subject' }}</h1>
                                        <div class="support-label-row" aria-label="Ticket classification">
                                            <span class="support-badge">Reference: {{ $ticket->reference }}</span>
                                            <span class="support-badge support-badge--priority-{{ \Illuminate\Support\Str::slug($ticket->priority ?: 'normal') }}">Priority: {{ \Illuminate\Support\Str::headline($ticket->priority ?: 'normal') }}</span>
                                            <span class="support-badge support-badge--status-{{ \Illuminate\Support\Str::slug($ticket->status ?: 'new') }}">Status: {{ \Illuminate\Support\Str::headline($ticket->status ?: 'new') }}</span>
                                            <span class="support-badge">Category: {{ \Illuminate\Support\Str::headline($ticket->category ?: 'general') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </header>

                            <main class="page-body" id="support-main">
                                @include('admin.messages')

                                @if($errors->any())
                                    <div class="alert alert-danger" role="alert" tabindex="-1">
                                        <strong>The requested action was not completed.</strong>
                                        <ul class="mb-0 mt-2">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="support-notice" role="note">
                                    <i class="ti-lock" aria-hidden="true"></i>
                                    <div>
                                        <strong>Handoffs on this page are proposals, not workflow actions.</strong>
                                        <p>Saving a product, manufacturer, restaurant or masjid link only classifies this support ticket. It does not update product status, prioritisation, brands, directory records, or send outreach.</p>
                                    </div>
                                </div>

                                <div class="support-detail-grid">
                                    <div>
                                        <section class="support-panel" aria-labelledby="ticket-details-heading">
                                            <div class="support-panel__header">
                                                <div>
                                                    <h2 id="ticket-details-heading">Ticket details</h2>
                                                    <p>Captured from {{ $ticket->source ?: 'appsupport mailbox' }}.</p>
                                                </div>
                                            </div>
                                            <div class="support-panel__body">
                                                <dl class="support-description-list">
                                                    <div>
                                                        <dt>Requester</dt>
                                                        <dd>{{ $ticket->requester_name ?: 'Unknown sender' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Email</dt>
                                                        <dd>{{ $ticket->requester_email ?: 'Not available' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Received</dt>
                                                        <dd>{{ optional($ticket->received_at ?: $ticket->created_at)->format('d M Y, H:i') ?: 'Unknown' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Owner</dt>
                                                        <dd>{{ $ticket->assignee?->name ?: 'Unassigned' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Proposed handoff</dt>
                                                        <dd>{{ $ticket->proposed_handoff ? \Illuminate\Support\Str::headline($ticket->proposed_handoff) : 'None' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Linked record</dt>
                                                        <dd>
                                                            @if($ticket->linked_entity_type || $ticket->linked_entity_id || $ticket->linked_barcode)
                                                                {{ \Illuminate\Support\Str::headline($ticket->linked_entity_type ?: 'record') }}
                                                                {{ $ticket->linked_entity_id ? '#'.$ticket->linked_entity_id : '' }}
                                                                {{ $ticket->linked_barcode ? ' · Barcode '.$ticket->linked_barcode : '' }}
                                                            @else
                                                                None
                                                            @endif
                                                        </dd>
                                                    </div>
                                                </dl>
                                                @if($ticket->submission_context_type || $ticket->submission_context_id || $ticket->submission_context_label || $ticket->submitted_barcode)
                                                    <section class="support-submitted-context mt-3" aria-labelledby="submitted-context-heading">
                                                        <h3 id="submitted-context-heading">Submitted app context <span>(unverified)</span></h3>
                                                        <p>This information came from the app submission. It is not an admin-approved record link and does not trigger another workflow.</p>
                                                        <dl class="support-meta-grid">
                                                            <div>
                                                                <dt>Submitted context type</dt>
                                                                <dd>{{ $ticket->submission_context_type ? \Illuminate\Support\Str::headline($ticket->submission_context_type) : 'Not supplied' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Submitted context ID</dt>
                                                                <dd>{{ $ticket->submission_context_id ?: 'Not supplied' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Submitted barcode</dt>
                                                                <dd>{{ $ticket->submitted_barcode ?: 'Not supplied' }}</dd>
                                                            </div>
                                                            @if($ticket->submission_context_label)
                                                                <div>
                                                                    <dt>Submitted product/place label</dt>
                                                                    <dd>{{ $ticket->submission_context_label }}</dd>
                                                                </div>
                                                            @endif
                                                        </dl>
                                                    </section>
                                                @endif
                                                @if($ticket->summary)
                                                    <div class="mt-3">
                                                        <strong>Summary</strong>
                                                        <p class="mb-0 mt-1">{{ $ticket->summary }}</p>
                                                    </div>
                                                @endif
                                                @if($ticket->resolution_note)
                                                    <div class="mt-3">
                                                        <strong>Resolution / no-action reason</strong>
                                                        <p class="mb-0 mt-1">{{ $ticket->resolution_note }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </section>

                                        <section class="support-panel" aria-labelledby="conversation-heading">
                                            <div class="support-panel__header">
                                                <div>
                                                    <h2 id="conversation-heading">Conversation history</h2>
                                                    <p>{{ $ticket->messages->count() }} {{ \Illuminate\Support\Str::plural('message', $ticket->messages->count()) }} recorded for this ticket.</p>
                                                </div>
                                            </div>
                                            <div class="support-panel__body">
                                                <div class="support-message-list">
                                                    @forelse($ticket->messages as $message)
                                                        @php
                                                            $direction = $message->direction ?: 'inbound';
                                                            $directionSlug = \Illuminate\Support\Str::slug($direction);
                                                            $isInbound = $direction === 'inbound';
                                                            $isInternalNotification = $direction === 'internal_notification';
                                                            $messageFrom = $isInbound || $isInternalNotification
                                                                ? ($message->from_address ?: ($isInbound ? $ticket->requester_email : 'Unknown internal sender'))
                                                                : config('support.mailbox_address');
                                                            $messageTo = $message->to_address
                                                                ?: ($isInbound || $isInternalNotification ? $ticket->mailbox_address : $ticket->requester_email);
                                                        @endphp
                                                        <article class="support-message support-message--{{ $directionSlug }}">
                                                            <header class="support-message__header">
                                                                <div>
                                                                    <strong>{{ \Illuminate\Support\Str::headline($direction) }}</strong>
                                                                    @if($message->subject)
                                                                        <div>{{ $message->subject }}</div>
                                                                    @endif
                                                                    <small>
                                                                        @if($isInternalNotification)
                                                                            Audited internal copy: {{ $messageFrom }} to {{ $messageTo ?: 'Unknown recipient' }}
                                                                        @else
                                                                            {{ $messageFrom ?: 'Unknown sender' }}@if($messageTo) to {{ $messageTo }}@endif
                                                                        @endif
                                                                    </small>
                                                                </div>
                                                                <small>{{ optional($message->received_at ?: $message->sent_at ?: $message->created_at)->format('d M Y, H:i') }}</small>
                                                            </header>
                                                            <pre class="support-message__body">{{ $message->body }}</pre>

                                                            @if($message->attachments->isNotEmpty())
                                                                <ul class="support-attachments" aria-label="Attachments for this message">
                                                                    @foreach($message->attachments as $attachment)
                                                                        @php
                                                                            $attachmentSecurityStatus = $attachment->security_status ?: 'pending_review';
                                                                        @endphp
                                                                        <li>
                                                                            <div class="support-attachment-row">
                                                                                <i class="ti-clip" aria-hidden="true"></i>
                                                                                <span>{{ $attachment->original_name ?: 'attachment' }}</span>
                                                                                @if($attachment->size_bytes)
                                                                                    <small>({{ number_format($attachment->size_bytes / 1024, 1) }} KB)</small>
                                                                                @endif
                                                                                <span class="support-badge support-badge--attachment-{{ \Illuminate\Support\Str::slug($attachmentSecurityStatus) }}">Security: {{ \Illuminate\Support\Str::headline($attachmentSecurityStatus) }}</span>
                                                                                <strong class="support-attachment-blocked">Download unavailable pending secure review</strong>
                                                                            </div>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </article>
                                                    @empty
                                                        <div class="support-empty">
                                                            <i class="ti-email" aria-hidden="true"></i>
                                                            <h3>No messages captured</h3>
                                                            <p>This ticket has no message history yet.</p>
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </section>

                                        <section class="support-panel" aria-labelledby="delivery-heading">
                                            <div class="support-panel__header">
                                                <div>
                                                    <h2 id="delivery-heading">Delivery history</h2>
                                                    <p>Audited outbound delivery attempts. Sending and uncertain records require manual review.</p>
                                                </div>
                                            </div>
                                            <div class="support-panel__body">
                                                <div class="support-delivery-list">
                                                    @forelse($ticket->deliveries as $delivery)
                                                        @php
                                                            $deliveryStatus = $delivery->status ?: 'pending';
                                                            $reconcileDelaySeconds = max((int) config('support.delivery_reconcile_after_seconds'), 300);
                                                            $sendingSafetyElapsed = $deliveryStatus !== 'sending'
                                                                || ($delivery->last_attempted_at
                                                                    && $delivery->last_attempted_at->lte(now()->subSeconds($reconcileDelaySeconds)));
                                                            $canReconcileDelivery = $delivery->kind === 'customer_reply'
                                                                && in_array($deliveryStatus, ['sending', 'uncertain'], true)
                                                                && !$delivery->reconciliation_outcome
                                                                && $sendingSafetyElapsed;
                                                            $isWithinSendingSafetyWindow = $delivery->kind === 'customer_reply'
                                                                && $deliveryStatus === 'sending'
                                                                && !$delivery->reconciliation_outcome
                                                                && !$sendingSafetyElapsed;
                                                        @endphp
                                                        <article class="support-delivery">
                                                            <div>
                                                                <strong>{{ $delivery->recipient_address ?: $ticket->requester_email ?: 'Unknown recipient' }}</strong>
                                                                <div class="support-delivery__details">
                                                                    <strong>Kind: {{ \Illuminate\Support\Str::headline($delivery->kind ?: 'unknown') }}</strong>
                                                                    ·
                                                                    Created {{ optional($delivery->created_at)->format('d M Y, H:i') }}
                                                                    @if($delivery->sent_at)
                                                                        · Sent {{ $delivery->sent_at->format('d M Y, H:i') }}
                                                                    @endif
                                                                    @if($delivery->approval_reference)
                                                                        · Approval: {{ $delivery->approval_reference }}
                                                                    @endif
                                                                    @if($delivery->reconciliation_outcome)
                                                                        <div class="mt-1"><strong>Reconciled: {{ \Illuminate\Support\Str::headline($delivery->reconciliation_outcome) }}</strong></div>
                                                                        @if($delivery->reconciliation_reason)
                                                                            <div>{{ $delivery->reconciliation_reason }}</div>
                                                                        @endif
                                                                        @if($delivery->reconciled_at)
                                                                            <div>Reconciled {{ $delivery->reconciled_at->format('d M Y, H:i') }}</div>
                                                                        @endif
                                                                        <div>By {{ $delivery->reconciler?->name ?: 'System / unavailable' }}</div>
                                                                    @endif
                                                                    @if($delivery->error)
                                                                        <div class="text-danger mt-1">{{ $delivery->error }}</div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <span class="support-badge support-badge--delivery-{{ \Illuminate\Support\Str::slug($deliveryStatus) }}">Delivery: {{ \Illuminate\Support\Str::headline($deliveryStatus) }}</span>
                                                            @if($canReconcileDelivery)
                                                                <details class="support-reconciliation">
                                                                    <summary>Manually reconcile this delivery</summary>
                                                                    <div class="support-warning-box mt-2"><strong>This action never resends email.</strong> Verify the mail server or recipient evidence before recording an outcome.</div>
                                                                    <form action="{{ route('support.deliveries.reconcile', [$ticket, $delivery]) }}" method="POST" onsubmit="return confirm('Record this manually verified delivery outcome? No email will be sent or retried.');">
                                                                        @csrf
                                                                        <div class="support-field mb-3">
                                                                            <label for="reconciliation-outcome-{{ $delivery->id }}">Verified outcome</label>
                                                                            <select id="reconciliation-outcome-{{ $delivery->id }}" name="outcome" class="form-control" required>
                                                                                <option value="">Select verified outcome</option>
                                                                                <option value="confirmed_sent">Confirmed sent</option>
                                                                                <option value="confirmed_not_sent">Confirmed not sent</option>
                                                                            </select>
                                                                            <span class="support-help">Confirmed not sent keeps the draft available to discard before preparing a replacement.</span>
                                                                        </div>
                                                                        <div class="support-field">
                                                                            <label for="reconciliation-reason-{{ $delivery->id }}">Evidence and reason</label>
                                                                            <textarea id="reconciliation-reason-{{ $delivery->id }}" name="reconciliation_reason" class="form-control" rows="3" maxlength="5000" required></textarea>
                                                                        </div>
                                                                        <div class="support-confirmation">
                                                                            <input id="confirm-reconciliation-{{ $delivery->id }}" type="checkbox" name="confirm_reconciliation" value="1" required>
                                                                            <label for="confirm-reconciliation-{{ $delivery->id }}">I manually verified this exact delivery outcome and understand no email will be resent.</label>
                                                                        </div>
                                                                        <button type="submit" class="btn btn-outline-danger">Record verified outcome</button>
                                                                    </form>
                                                                </details>
                                                            @elseif($isWithinSendingSafetyWindow)
                                                                <div class="support-warning-box support-delivery-safety-window">
                                                                    Manual reconciliation is temporarily unavailable during the SMTP safety window.
                                                                    @if($delivery->last_attempted_at)
                                                                        Review again after {{ $delivery->last_attempted_at->copy()->addSeconds($reconcileDelaySeconds)->format('d M Y, H:i:s') }}.
                                                                    @else
                                                                        A recorded attempt time is required before reconciliation.
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </article>
                                                    @empty
                                                        <div class="support-empty">
                                                            <i class="ti-check-box" aria-hidden="true"></i>
                                                            <h3>No delivery attempts</h3>
                                                            <p>No reply has been submitted for delivery from this ticket.</p>
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </section>

                                        <section class="support-panel" aria-labelledby="audit-heading">
                                            <div class="support-panel__header">
                                                <div>
                                                    <h2 id="audit-heading">Audit history</h2>
                                                    <p>Recorded ticket captures, classifications and state changes.</p>
                                                </div>
                                            </div>
                                            <div class="support-panel__body">
                                                <div class="support-event-list">
                                                    @forelse($ticket->events as $event)
                                                        <article class="support-event">
                                                            <div class="support-event__marker" aria-hidden="true"></div>
                                                            <div>
                                                                <div class="support-event__header">
                                                                    <strong>{{ \Illuminate\Support\Str::headline($event->event_type) }}</strong>
                                                                    <small>{{ optional($event->created_at)->format('d M Y, H:i') }}</small>
                                                                </div>
                                                                <div class="support-event__meta">By {{ $event->actor?->name ?: 'System' }}</div>
                                                                @if(is_array($event->after_values))
                                                                    <dl class="support-event__changes">
                                                                        @foreach($event->after_values as $field => $value)
                                                                            @if(in_array($field, ['status', 'category', 'priority', 'assigned_to', 'linked_entity_type', 'linked_entity_id', 'linked_barcode', 'proposed_handoff', 'resolution_note'], true))
                                                                                <div>
                                                                                    <dt>{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                                                                    <dd>{{ is_array($value) ? json_encode($value) : ($value === null || $value === '' ? 'Cleared' : $value) }}</dd>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    </dl>
                                                                @endif
                                                                @if($event->details)
                                                                    <p class="mb-0 mt-2">{{ $event->details }}</p>
                                                                @endif
                                                            </div>
                                                        </article>
                                                    @empty
                                                        <div class="support-empty">
                                                            <i class="ti-time" aria-hidden="true"></i>
                                                            <h3>No audit events</h3>
                                                            <p>No ticket actions have been recorded yet.</p>
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <aside aria-label="Ticket actions">
                                        <section class="support-panel" aria-labelledby="triage-heading">
                                            <div class="support-panel__header">
                                                <div>
                                                    <h2 id="triage-heading">Triage and ownership</h2>
                                                    <p>Classify the support case without changing linked records.</p>
                                                </div>
                                            </div>
                                            <div class="support-panel__body">
                                                @php
                                                    $hasLiveDraft = $ticket->messages->contains('direction', 'outbound_draft');
                                                    $hasInflightDelivery = $ticket->deliveries->contains(fn ($delivery) => $delivery->kind === 'customer_reply'
                                                        && in_array($delivery->status, ['pending', 'sending', 'uncertain'], true));
                                                    $hasBlockingReply = $hasLiveDraft || $hasInflightDelivery;
                                                @endphp
                                                @if($hasBlockingReply)
                                                    <div class="support-warning-box">Resolved and No Action are unavailable while a customer reply is still a draft, pending, sending, or uncertain. Send or discard a draft, then review the delivery state before closing the ticket.</div>
                                                @endif
                                                <form action="{{ route('support.triage', $ticket) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="support-form-grid">
                                                        <div class="support-field">
                                                            <label for="ticket-status">Status</label>
                                                            <select id="ticket-status" name="status" class="form-control" required>
                                                                @foreach($statuses as $status)
                                                                    <option value="{{ $status }}" @selected(old('status', $ticket->status) === $status) @disabled(in_array($status, ['resolved', 'no_action'], true) && $hasBlockingReply)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('status')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field">
                                                            <label for="ticket-priority">Priority</label>
                                                            <select id="ticket-priority" name="priority" class="form-control" required>
                                                                @foreach($priorities as $priority)
                                                                    <option value="{{ $priority }}" @selected(old('priority', $ticket->priority) === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('priority')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field support-field--wide">
                                                            <label for="ticket-category">Category</label>
                                                            <select id="ticket-category" name="category" class="form-control" required>
                                                                @foreach($categories as $category)
                                                                    <option value="{{ $category }}" @selected(old('category', $ticket->category) === $category)>{{ \Illuminate\Support\Str::headline($category) }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('category')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field support-field--wide">
                                                            <label for="ticket-owner">Owner</label>
                                                            <select id="ticket-owner" name="assigned_to" class="form-control">
                                                                <option value="">Unassigned</option>
                                                                @foreach($admins as $admin)
                                                                    <option value="{{ $admin->id }}" @selected((string) old('assigned_to', $ticket->assigned_to) === (string) $admin->id)>{{ $admin->name }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('assigned_to')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field support-field--wide">
                                                            <label for="proposed-handoff">Proposed handoff lane</label>
                                                            <select id="proposed-handoff" name="proposed_handoff" class="form-control" aria-describedby="handoff-help">
                                                                <option value="">No handoff proposed</option>
                                                                @foreach($handoffLanes as $lane)
                                                                    <option value="{{ $lane }}" @selected(old('proposed_handoff', $ticket->proposed_handoff) === $lane)>{{ \Illuminate\Support\Str::headline($lane) }}</option>
                                                                @endforeach
                                                            </select>
                                                            <span id="handoff-help" class="support-help">This is an audited label only. It does not create or update work in another flow.</span>
                                                            @error('proposed_handoff')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field support-field--wide">
                                                            <label for="linked-entity-type">Linked record type</label>
                                                            <select id="linked-entity-type" name="linked_entity_type" class="form-control">
                                                                <option value="">No linked record</option>
                                                                @foreach($linkedEntityTypes as $type)
                                                                    <option value="{{ $type }}" @selected(old('linked_entity_type', $ticket->linked_entity_type) === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('linked_entity_type')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field">
                                                            <label for="linked-entity-id">Linked record ID</label>
                                                            <input id="linked-entity-id" type="text" name="linked_entity_id" value="{{ old('linked_entity_id', $ticket->linked_entity_id) }}" class="form-control" maxlength="100">
                                                            @error('linked_entity_id')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field">
                                                            <label for="linked-barcode">Linked barcode</label>
                                                            <input id="linked-barcode" type="text" inputmode="numeric" name="linked_barcode" value="{{ old('linked_barcode', $ticket->linked_barcode) }}" class="form-control" minlength="8" maxlength="14" pattern="[0-9]{8,14}" aria-describedby="barcode-help">
                                                            <span id="barcode-help" class="support-help">Exact 8–14 digit barcode; linking does not change the product.</span>
                                                            @error('linked_barcode')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field support-field--wide">
                                                            <label for="resolution-note">Resolution / no-action reason</label>
                                                            <textarea id="resolution-note" name="resolution_note" class="form-control" rows="4" maxlength="5000" aria-describedby="resolution-note-help">{{ old('resolution_note', $ticket->resolution_note) }}</textarea>
                                                            <span id="resolution-note-help" class="support-help">Required when selecting Resolved or No Action. Explain what completed the case or why no reply/action is appropriate.</span>
                                                            @error('resolution_note')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary mt-3">Save triage</button>
                                                </form>
                                            </div>
                                        </section>

                                        <section class="support-panel" aria-labelledby="draft-heading">
                                            <div class="support-panel__header">
                                                <div>
                                                    <h2 id="draft-heading">Prepare a reply</h2>
                                                    <p>Saving creates a draft only. It does not send email.</p>
                                                </div>
                                            </div>
                                            <div class="support-panel__body">
                                                @if($hasBlockingReply)
                                                    <div class="support-empty">
                                                        <i class="ti-lock" aria-hidden="true"></i>
                                                        <h3>Reply composition unavailable</h3>
                                                        <p>
                                                            @if($hasLiveDraft)
                                                                Review, send, or discard the existing unsent draft before preparing another reply.
                                                            @else
                                                                A customer delivery is pending, sending, or uncertain. Review its delivery audit before preparing another reply.
                                                            @endif
                                                        </p>
                                                    </div>
                                                @elseif($ticket->requester_email)
                                                    <form action="{{ route('support.reply-drafts.store', $ticket) }}" method="POST">
                                                        @csrf
                                                        <div class="support-field mb-3">
                                                            <label>Recipient</label>
                                                        <div class="form-control" role="textbox" aria-readonly="true">{{ $ticket->requester_email }}</div>
                                                            <span class="support-help">The captured requester address is fixed for this ticket.</span>
                                                        </div>
                                                        <div class="support-field mb-3">
                                                            <label for="reply-subject">Subject</label>
                                                            <input id="reply-subject" type="text" name="subject" class="form-control" maxlength="255" required value="{{ old('subject', 'Re: ['.$ticket->reference.'] '.$ticket->subject) }}">
                                                            @error('subject')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <div class="support-field mb-3">
                                                            <label for="reply-body">Message</label>
                                                            <textarea id="reply-body" name="body" class="form-control" rows="8" maxlength="20000" required>{{ old('body') }}</textarea>
                                                            <span class="support-help">Review personal information and attachment requests carefully before saving.</span>
                                                            @error('body')<span class="support-error">{{ $message }}</span>@enderror
                                                        </div>
                                                        <button type="submit" class="btn btn-outline-primary">Save reply draft</button>
                                                    </form>
                                                @else
                                                    <div class="support-empty">
                                                        <i class="ti-alert" aria-hidden="true"></i>
                                                        <h3>Recipient email missing</h3>
                                                        <p>A reply cannot be drafted until a valid requester address is captured.</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </section>

                                        @php
                                            $replyDrafts = $ticket->messages->where('direction', 'outbound_draft');
                                        @endphp
                                        <section class="support-panel" aria-labelledby="approval-heading">
                                            <div class="support-panel__header">
                                                <div>
                                                    <h2 id="approval-heading">Reviewed drafts</h2>
                                                    <p>Each exact draft requires an approval reference and explicit confirmation.</p>
                                                </div>
                                            </div>
                                            <div class="support-panel__body">
                                                @unless($supportMailEnabled)
                                                    <div class="support-warning-box"><strong>Sending is disabled.</strong> Drafts remain available for review, but no email can be sent until the dedicated appsupport@halalkiwi.com SMTP path is enabled and configured.</div>
                                                @endunless
                                                <div class="support-draft-list">
                                                    @forelse($replyDrafts as $draft)
                                                        <article class="support-draft">
                                                            <div class="support-draft__preview">
                                                                <h4>{{ $draft->subject ?: 'No subject' }}</h4>
                                                                <div class="support-help mb-2">To {{ $draft->to_address ?: $ticket->requester_email }}</div>
                                                                <pre>{{ $draft->body }}</pre>
                                                            </div>
                                                            <div class="support-draft__approval">
                                                                <div class="support-warning-box">
                                                                    Sending is an external action. Verify the recipient, subject, body and ticket reference before continuing.
                                                                </div>
                                                                <form action="{{ route('support.reply-drafts.send', [$ticket, $draft]) }}" method="POST" onsubmit="return confirm('Send this exact reviewed support reply now?');">
                                                                    @csrf
                                                                    <div class="support-field">
                                                                        <label for="approval-reference-{{ $draft->id }}">Approval reference</label>
                                                                        <input id="approval-reference-{{ $draft->id }}" type="text" name="approval_reference" class="form-control" maxlength="500" required aria-describedby="approval-help-{{ $draft->id }}">
                                                                        <span id="approval-help-{{ $draft->id }}" class="support-help">For example: “Approved in support review 13 Aug 2026”.</span>
                                                                    </div>
                                                                    <div class="support-confirmation">
                                                                        <input id="confirm-send-{{ $draft->id }}" type="checkbox" name="confirm_send" value="1" required>
                                                                        <label for="confirm-send-{{ $draft->id }}">I reviewed this exact recipient, subject and message and approve sending it now.</label>
                                                                    </div>
                                                                    <button type="submit" class="btn btn-danger" @disabled(!$supportMailEnabled)>Send this reviewed reply now</button>
                                                                </form>
                                                                <details class="support-discard-draft">
                                                                    <summary>Discard this unsent draft</summary>
                                                                    <form action="{{ route('support.reply-drafts.discard', [$ticket, $draft]) }}" method="POST" class="mt-3" onsubmit="return confirm('Discard this exact unsent reply draft? It will remain in the audit history.');">
                                                                        @csrf
                                                                        <div class="support-field">
                                                                            <label for="discard-reason-{{ $draft->id }}">Discard reason</label>
                                                                            <textarea id="discard-reason-{{ $draft->id }}" name="discard_reason" class="form-control" rows="3" maxlength="5000" required aria-describedby="discard-help-{{ $draft->id }}"></textarea>
                                                                            <span id="discard-help-{{ $draft->id }}" class="support-help">Explain why this draft must not be sent. The reason is retained in the audit history.</span>
                                                                        </div>
                                                                        <div class="support-confirmation">
                                                                            <input id="confirm-discard-{{ $draft->id }}" type="checkbox" name="confirm_discard" value="1" required>
                                                                            <label for="confirm-discard-{{ $draft->id }}">I confirm this exact draft is unsent and should be discarded.</label>
                                                                        </div>
                                                                        <button type="submit" class="btn btn-outline-danger">Discard unsent draft</button>
                                                                    </form>
                                                                </details>
                                                            </div>
                                                        </article>
                                                    @empty
                                                        <div class="support-empty">
                                                            <i class="ti-write" aria-hidden="true"></i>
                                                            <h3>No drafts awaiting approval</h3>
                                                            <p>Prepare and save a reply draft first.</p>
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </section>
                                    </aside>
                                </div>
                            </main>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/support-admin.css') }}">
@endpush
