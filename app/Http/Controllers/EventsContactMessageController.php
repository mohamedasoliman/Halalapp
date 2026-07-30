<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\EventsContactRequest;
use App\Mail\EventsContactUsEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EventsContactMessageController extends Controller
{
    public function send(EventsContactRequest $request)
    {
        $attachmentPath = null;

        try {
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('attachments');
            }

            Mail::to('events@halalkiwi.com')->send(new EventsContactUsEmail($request, $attachmentPath));

            return response()->json(['message' => 'Mail Sent']);
        } catch (\Throwable $e) {
            Log::error('Events email could not be sent.', ['exception' => $e]);

            return response()->json([
                'message' => 'Your message could not be sent. Please try again.',
            ], 500);
        } finally {
            if ($attachmentPath !== null) {
                Storage::delete($attachmentPath);
            }
        }
    }
}
