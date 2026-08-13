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
                                <div class="page-header-title">
                                    <h1>App Support</h1>
                                    <p class="support-page__intro">Review and respond to messages captured from <strong>appsupport@halalkiwi.com</strong>.</p>
                                </div>
                                <nav class="page-header-breadcrumb" aria-label="Breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home" aria-hidden="true"></i><span class="sr-only">Dashboard</span></a></li>
                                        <li class="breadcrumb-item" aria-current="page">App Support</li>
                                    </ul>
                                </nav>
                            </header>

                            <main class="page-body" id="support-main">
                                @include('admin.messages')

                                @if($errors->any())
                                    <div class="alert alert-danger" role="alert" tabindex="-1">
                                        <strong>Check the filters below.</strong>
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
                                        <strong>This queue is isolated from product workflows.</strong>
                                        <p>A product or manufacturer handoff is recorded here as a proposal only. It cannot change a verdict, prioritisation request, brand record, or manufacturer outreach batch.</p>
                                    </div>
                                </div>

                                <section aria-labelledby="support-summary-heading">
                                    <h2 id="support-summary-heading" class="sr-only">Queue summary</h2>
                                    <div class="support-stat-grid">
                                        <div class="support-stat">
                                            <span class="support-stat__value">{{ number_format($counts['all']) }}</span>
                                            <span class="support-stat__label">All tickets</span>
                                        </div>
                                        <div class="support-stat">
                                            <span class="support-stat__value">{{ number_format($counts['unassigned']) }}</span>
                                            <span class="support-stat__label">Unassigned</span>
                                        </div>
                                        @foreach($statuses as $status)
                                            <div class="support-stat">
                                                <span class="support-stat__value">{{ number_format($counts[$status] ?? 0) }}</span>
                                                <span class="support-stat__label">Status: {{ \Illuminate\Support\Str::headline($status) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>

                                <section class="support-panel" aria-labelledby="support-filter-heading">
                                    <div class="support-panel__header">
                                        <div>
                                            <h2 id="support-filter-heading">Filter the queue</h2>
                                            <p>Search by reference, subject, requester or linked barcode.</p>
                                        </div>
                                    </div>
                                    <div class="support-panel__body">
                                        <form action="{{ route('support.index') }}" method="GET">
                                            <div class="support-filter-grid">
                                                <div class="support-field">
                                                    <label for="support-search">Search</label>
                                                    <input id="support-search" class="form-control" type="search" name="search" value="{{ request('search') }}" maxlength="120" autocomplete="off">
                                                </div>
                                                <div class="support-field">
                                                    <label for="support-status">Status</label>
                                                    <select id="support-status" class="form-control" name="status">
                                                        <option value="">All statuses</option>
                                                        @foreach($statuses as $status)
                                                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="support-field">
                                                    <label for="support-category">Category</label>
                                                    <select id="support-category" class="form-control" name="category">
                                                        <option value="">All categories</option>
                                                        @foreach($categories as $category)
                                                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ \Illuminate\Support\Str::headline($category) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="support-field">
                                                    <label for="support-priority">Priority</label>
                                                    <select id="support-priority" class="form-control" name="priority">
                                                        <option value="">All priorities</option>
                                                        @foreach($priorities as $priority)
                                                            <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="support-field">
                                                    <label for="support-owner">Owner</label>
                                                    <select id="support-owner" class="form-control" name="owner">
                                                        <option value="">All owners</option>
                                                        <option value="unassigned" @selected(request('owner') === 'unassigned')>Unassigned</option>
                                                        @foreach($admins as $admin)
                                                            <option value="{{ $admin->id }}" @selected((string) request('owner') === (string) $admin->id)>{{ $admin->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="support-filter-actions">
                                                <button type="submit" class="btn btn-primary">Apply filters</button>
                                                <a href="{{ route('support.index') }}" class="btn btn-outline-secondary">Reset filters</a>
                                            </div>
                                        </form>
                                    </div>
                                </section>

                                <section aria-labelledby="support-ticket-list-heading">
                                    <div class="support-panel__header support-panel">
                                        <div>
                                            <h2 id="support-ticket-list-heading">Support tickets</h2>
                                            <p>{{ number_format($tickets->total()) }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }} match the current filters.</p>
                                        </div>
                                    </div>

                                    <div class="support-ticket-list">
                                        @forelse($tickets as $ticket)
                                            @php
                                                $prioritySlug = \Illuminate\Support\Str::slug($ticket->priority ?: 'normal');
                                                $statusSlug = \Illuminate\Support\Str::slug($ticket->status ?: 'new');
                                            @endphp
                                            <article class="support-ticket-card">
                                                <div>
                                                    <span class="support-ticket-card__reference">{{ $ticket->reference }}</span>
                                                    <h2>{{ $ticket->subject ?: 'No subject' }}</h2>
                                                    <div class="support-ticket-card__requester">
                                                        From {{ $ticket->requester_name ?: 'Unknown sender' }}
                                                        @if($ticket->requester_email)
                                                            &lt;{{ $ticket->requester_email }}&gt;
                                                        @endif
                                                    </div>
                                                    <p class="support-ticket-card__summary">{{ $ticket->summary ?: 'No summary has been recorded yet.' }}</p>
                                                    <div class="support-ticket-card__meta mt-2">
                                                        Received {{ optional($ticket->received_at ?: $ticket->created_at)->format('d M Y, H:i') }}
                                                        · {{ $ticket->messages_count }} {{ \Illuminate\Support\Str::plural('message', $ticket->messages_count) }}
                                                        · {{ $ticket->attachments_count }} {{ \Illuminate\Support\Str::plural('attachment', $ticket->attachments_count) }}
                                                        · Owner: {{ $ticket->assignee?->name ?: 'Unassigned' }}
                                                    </div>
                                                </div>
                                                <div class="support-ticket-card__labels" aria-label="Ticket classification">
                                                    <span class="support-badge support-badge--priority-{{ $prioritySlug }}">Priority: {{ \Illuminate\Support\Str::headline($ticket->priority ?: 'normal') }}</span>
                                                    <span class="support-badge support-badge--status-{{ $statusSlug }}">Status: {{ \Illuminate\Support\Str::headline($ticket->status ?: 'new') }}</span>
                                                    <span class="support-badge">Category: {{ \Illuminate\Support\Str::headline($ticket->category ?: 'general') }}</span>
                                                    @if($ticket->proposed_handoff)
                                                        <span class="support-badge">Proposed handoff: {{ \Illuminate\Support\Str::headline($ticket->proposed_handoff) }}</span>
                                                    @endif
                                                </div>
                                                <div class="support-ticket-card__action">
                                                    <a href="{{ route('support.show', $ticket) }}" class="btn btn-outline-primary" aria-label="Open support ticket {{ $ticket->reference }}">Open ticket</a>
                                                </div>
                                            </article>
                                        @empty
                                            <div class="support-empty">
                                                <i class="ti-email" aria-hidden="true"></i>
                                                <h2>No tickets match these filters</h2>
                                                <p>Reset the filters to see the full app-support queue.</p>
                                                <a href="{{ route('support.index') }}" class="btn btn-outline-primary mt-3">Reset filters</a>
                                            </div>
                                        @endforelse
                                    </div>

                                    @if($tickets->hasPages())
                                        <nav class="support-pagination" aria-label="Support ticket pages">
                                            {{ $tickets->links() }}
                                        </nav>
                                    @endif
                                </section>
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
