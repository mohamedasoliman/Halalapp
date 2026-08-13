<?php

namespace App\Http\Controllers\Admin;

use App\Admin;
use App\Http\Controllers\Controller;
use App\Models\SupportAttachment;
use App\Models\SupportDelivery;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportReplyService;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use LogicException;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $statusValues = $this->optionValues(SupportTicket::STATUSES);
        $categoryValues = $this->optionValues(SupportTicket::CATEGORIES);
        $priorityValues = $this->optionValues(SupportTicket::PRIORITIES);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in($statusValues)],
            'category' => ['nullable', Rule::in($categoryValues)],
            'priority' => ['nullable', Rule::in($priorityValues)],
            'owner' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === 'unassigned') {
                    return;
                }

                if (filter_var($value, FILTER_VALIDATE_INT) === false
                    || ! Admin::query()
                        ->whereKey((int) $value)
                        ->where('role_id', 1)
                        ->where('status', 1)
                        ->exists()) {
                    $fail('The selected owner is invalid.');
                }
            }],
        ]);

        $tickets = SupportTicket::query()
            ->with('assignee')
            ->withCount(['messages', 'attachments'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('requester_email', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%")
                        ->orWhere('linked_barcode', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when(($filters['owner'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->when(
                isset($filters['owner']) && $filters['owner'] !== 'unassigned',
                fn ($query) => $query->where('assigned_to', (int) $filters['owner'])
            )
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'all' => SupportTicket::count(),
            'unassigned' => SupportTicket::whereNull('assigned_to')->count(),
        ];

        foreach ($statusValues as $status) {
            $counts[$status] = SupportTicket::where('status', $status)->count();
        }

        return view('admin.support.index', [
            'tickets' => $tickets,
            'counts' => $counts,
            'statuses' => $statusValues,
            'categories' => $categoryValues,
            'priorities' => $priorityValues,
            'admins' => $this->assignableAdmins(),
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load([
            'assignee',
            'messages' => fn ($query) => $query->with('attachments')->orderBy('created_at')->orderBy('id'),
            'deliveries' => fn ($query) => $query->with('reconciler')->orderByDesc('created_at')->orderByDesc('id'),
            'events' => fn ($query) => $query->with('actor')->orderByDesc('created_at')->orderByDesc('id'),
        ]);

        return view('admin.support.show', [
            'ticket' => $ticket,
            'admins' => $this->assignableAdmins(),
            'statuses' => $this->optionValues(SupportTicket::STATUSES),
            'categories' => $this->optionValues(SupportTicket::CATEGORIES),
            'priorities' => $this->optionValues(SupportTicket::PRIORITIES),
            'handoffLanes' => SupportTicket::HANDOFFS,
            'linkedEntityTypes' => SupportTicket::LINKED_ENTITY_TYPES,
            'supportMailEnabled' => (bool) config('support.mail_enabled'),
        ]);
    }

    public function triage(
        Request $request,
        SupportTicket $ticket,
        SupportTicketService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in($this->optionValues(SupportTicket::STATUSES))],
            'category' => ['required', Rule::in($this->optionValues(SupportTicket::CATEGORIES))],
            'priority' => ['required', Rule::in($this->optionValues(SupportTicket::PRIORITIES))],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('admins', 'id')->where(
                    fn ($query) => $query->where('role_id', 1)->where('status', 1)
                ),
            ],
            'proposed_handoff' => ['nullable', Rule::in(SupportTicket::HANDOFFS)],
            'linked_entity_type' => ['nullable', Rule::in(SupportTicket::LINKED_ENTITY_TYPES)],
            'linked_entity_id' => ['nullable', 'string', 'max:100'],
            'linked_barcode' => ['nullable', 'regex:/^\d{8,14}$/'],
            'resolution_note' => ['nullable', 'required_if:status,resolved,no_action', 'string', 'max:5000'],
        ]);

        $service->triage($ticket, $validated, (int) Auth::guard('admin')->id());

        return redirect()
            ->route('support.show', $ticket)
            ->with('success', 'Support ticket triage was updated. No linked product workflow was changed.');
    }

    public function saveDraft(
        Request $request,
        SupportTicket $ticket,
        SupportReplyService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        try {
            $service->saveDraft($ticket, $validated, (int) Auth::guard('admin')->id());
        } catch (InvalidArgumentException|LogicException $exception) {
            return redirect()
                ->route('support.show', $ticket)
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('support.show', $ticket)
            ->with('success', 'Reply saved as a draft. Nothing has been sent.');
    }

    public function sendDraft(
        Request $request,
        SupportTicket $ticket,
        SupportMessage $message,
        SupportReplyService $service
    ): RedirectResponse {
        abort_unless((int) $message->support_ticket_id === (int) $ticket->id, 404);

        $validated = $request->validate([
            'confirm_send' => ['accepted'],
            'approval_reference' => ['required', 'string', 'max:500'],
        ], [
            'confirm_send.accepted' => 'Confirm that you reviewed this exact recipient, subject and message.',
            'approval_reference.required' => 'Enter the approval reference before sending.',
        ]);

        try {
            $delivery = $service->sendApprovedDraft($message, $validated['approval_reference']);
        } catch (InvalidArgumentException|LogicException $exception) {
            return redirect()
                ->route('support.show', $ticket)
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        if ($delivery->status !== 'sent') {
            $mayHaveBeenAccepted = in_array($delivery->status, ['pending', 'sending', 'uncertain'], true);

            return redirect()
                ->route('support.show', $ticket)
                ->with(
                    'error',
                    'Delivery is '.$delivery->status.'. '.($mayHaveBeenAccepted
                        ? 'The message may have been accepted; review the delivery audit before taking any further action.'
                        : 'No successful delivery was recorded; review the delivery audit before taking any further action.')
                );
        }

        return redirect()
            ->route('support.show', $ticket)
            ->with('success', 'The reviewed support reply was sent and recorded in the delivery audit.');
    }

    public function discardDraft(
        Request $request,
        SupportTicket $ticket,
        SupportMessage $message,
        SupportReplyService $service
    ): RedirectResponse {
        abort_unless((int) $message->support_ticket_id === (int) $ticket->id, 404);

        $validated = $request->validate([
            'confirm_discard' => ['accepted'],
            'discard_reason' => ['required', 'string', 'max:5000'],
        ], [
            'confirm_discard.accepted' => 'Confirm that this exact unsent draft should be discarded.',
            'discard_reason.required' => 'Enter an audit reason before discarding the draft.',
        ]);

        try {
            $service->discardDraft(
                $message,
                $validated['discard_reason'],
                (int) Auth::guard('admin')->id()
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return redirect()
                ->route('support.show', $ticket)
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('support.show', $ticket)
            ->with('success', 'The unsent reply draft was discarded and retained in the ticket audit history.');
    }

    public function reconcileDelivery(
        Request $request,
        SupportTicket $ticket,
        SupportDelivery $delivery,
        SupportReplyService $service
    ): RedirectResponse {
        abort_unless((int) $delivery->support_ticket_id === (int) $ticket->id, 404);
        abort_unless($delivery->kind === 'customer_reply', 404);

        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['confirmed_sent', 'confirmed_not_sent'])],
            'reconciliation_reason' => ['required', 'string', 'max:5000'],
            'confirm_reconciliation' => ['accepted'],
        ], [
            'reconciliation_reason.required' => 'Enter the evidence and reason for this delivery outcome.',
            'confirm_reconciliation.accepted' => 'Confirm that this delivery outcome was manually verified.',
        ]);

        try {
            $service->reconcileDelivery(
                $delivery,
                $validated['outcome'],
                $validated['reconciliation_reason'],
                (int) Auth::guard('admin')->id()
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return redirect()
                ->route('support.show', $ticket)
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('support.show', $ticket)
            ->with('success', 'The delivery was reconciled without sending or retrying any email.');
    }

    public function attachment(SupportTicket $ticket, SupportAttachment $attachment): never
    {
        abort_unless($ticket->attachments()->whereKey($attachment->id)->exists(), 404);
        abort(403, 'Support attachment downloads remain disabled until a secure review process is implemented.');
    }

    /**
     * Accept list constants and label maps without coupling the admin UI to presentation labels.
     *
     * @param  array<int|string, string>  $options
     * @return array<int, string>
     */
    private function optionValues(array $options): array
    {
        return array_is_list($options) ? array_values($options) : array_keys($options);
    }

    private function assignableAdmins()
    {
        return Admin::query()
            ->where('role_id', 1)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
