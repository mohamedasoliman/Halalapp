<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\ContactRequest;
use App\Mail\ContactUsEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ContactMessageController extends Controller
{
    public function send(ContactRequest $request)
    {
        $attachmentPath = null;

        try {
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('attachments');
            }

            Mail::to('appsupport@halalkiwi.com')->send(new ContactUsEmail($request, $attachmentPath));

            return response()->json(['message' => 'Mail Sent']);
        } catch (\Throwable $e) {
            Log::error('Contact email could not be sent.', [
                'exception' => $e,
            ]);

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
