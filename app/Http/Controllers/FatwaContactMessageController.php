<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\FatwaContactRequest;
use App\Mail\FatwaContactUsEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class FatwaContactMessageController extends Controller
{
    public function send(FatwaContactRequest $request)
    {
        $attachmentPath = null;

        try {
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('attachments');
            }

            Mail::to('fatwa@halalkiwi.com')->send(new FatwaContactUsEmail($request, $attachmentPath));

            return response()->json(['message' => 'Mail Sent']);
        } catch (\Throwable $e) {
            Log::error('Fatwa email could not be sent.', ['exception' => $e]);

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
