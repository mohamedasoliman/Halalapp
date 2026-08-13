<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\ContactRequest;
use App\Mail\ContactUsEmail;
use App\Models\SupportDelivery;
use App\Services\SupportAttachmentService;
use App\Services\SupportTicketService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactMessageController extends Controller
{
    public function send(
        ContactRequest $request,
        SupportTicketService $tickets,
        SupportAttachmentService $attachments,
    ) {
        $data = $request->validated();
        if ($request->hasFile('attachment')) {
            $attachmentSha256 = hash_file('sha256', $request->file('attachment')->getRealPath());
            if (! is_string($attachmentSha256)) {
                throw ValidationException::withMessages([
                    'attachment' => ['The support attachment could not be read.'],
                ]);
            }
            $data['attachment_sha256'] = $attachmentSha256;
        }

        try {
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $capture = $attachments->captureUploadedFileAtomically(
                    strtolower(trim((string) $data['email'])),
                    $data['submission_uuid'] ?? $data['client_submission_uuid'] ?? null,
                    $data['attachment_sha256'],
                    $file,
                    fn () => $tickets->captureAppSubmission($data),
                );
            } else {
                $capture = $tickets->captureAppSubmission($data);
            }
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Contact submission could not be durably captured.', ['exception' => $exception]);

            throw $exception;
        }

        $ticket = $capture['ticket'];
        $message = $capture['message'];
        try {
            $eventReference = "support-internal-notification:{$ticket->reference}";
            $delivery = SupportDelivery::firstOrCreate(
                ['event_key' => hash('sha256', strtolower($eventReference))],
                [
                    'support_ticket_id' => $ticket->id,
                    'support_message_id' => $message->id,
                    'kind' => 'internal_notification',
                    'event_reference' => $eventReference,
                    'mailer' => (string) config('mail.default'),
                    'recipient_address' => $tickets->mailboxAddress(),
                    'normalized_recipient_address' => $tickets->mailboxAddress(),
                    'status' => 'pending',
                ],
            );
            $claimed = SupportDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'sending',
                    'attempts' => $delivery->attempts + 1,
                    'last_attempted_at' => now(),
                ]);
            if ($claimed === 1) {
                try {
                    Mail::to($tickets->mailboxAddress())->send(new ContactUsEmail($ticket, $message));
                    $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
                } catch (Throwable $exception) {
                    $delivery->update([
                        'status' => 'uncertain',
                        'uncertain_at' => now(),
                        'error' => mb_substr($exception->getMessage(), 0, 5000),
                    ]);
                    Log::error('Contact ticket was saved but its internal support notification is uncertain.', [
                        'ticket_reference' => $ticket->reference,
                        'delivery_id' => $delivery->id,
                        'exception' => $exception,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            // The durable ticket and private attachments are authoritative. An
            // internal-notification audit failure must not invite client retries.
            Log::error('Contact ticket was saved but its internal notification could not be prepared.', [
                'ticket_reference' => $ticket->reference,
                'exception' => $exception,
            ]);
        }

        return response()->json([
            'message' => 'Message received',
            'reference' => $ticket->reference,
            'duplicate' => ! $capture['created'],
        ]);
    }
}
