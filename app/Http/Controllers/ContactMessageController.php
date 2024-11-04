<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\ContactUsEmail;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{

    public function send(Request $request)
    {
        try {
            $request->validate([
                'subject' => 'required|string|max:255',
                'email' => 'required|email',
                'name' => 'required|string|max:255',
                'body' => 'required|string',
                'attachment' => 'nullable|file',
            ]);

            $apiKey = $request->header('X-API-Key');
            $appKey = config('app.key');

            // Check if the API key matches the APP_KEY
            if ($apiKey !== $appKey) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('attachments');
            }

            Mail::to('appsupport@halalkiwi.com')->send(new ContactUsEmail($request, $attachmentPath));
            return response()->json(['message' => 'Mail Sent']);
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }
    }
}
