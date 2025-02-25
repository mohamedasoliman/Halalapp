<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\EventsContactUsEmail;
use Illuminate\Support\Facades\Mail;

class EventsContactMessageController extends Controller
{

    public function send(Request $request)
{
    $request->validate([
        'subject' => 'required|string|max:255',
        'email' => 'required|email',
        'contact' => 'nullable|string',
        'eventName' => 'required|string|max:255',
        'date' => 'required|string|max:255',
        'time' => 'required|string|max:255',
        'address' => 'required|string',
        'link' => 'nullable|string',
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

    Mail::to('events@halalkiwi.com')->send(new EventsContactUsEmail($request, $attachmentPath));

}
}
