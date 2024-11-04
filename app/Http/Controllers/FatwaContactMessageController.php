<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\FatwaContactUsEmail;
use Illuminate\Support\Facades\Mail;

class FatwaContactMessageController extends Controller
{

    public function send(Request $request)
{
    $request->validate([
        'subject' => 'required|string|max:255',
        'email' => 'required|email',
        'name' => 'required|string|max:255',
        'category' => 'required|string|max:255',
        'date' => 'required|string|max:255',
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

    Mail::to('fatwa@halalkiwi.com')->send(new FatwaContactUsEmail($request, $attachmentPath));

}
}
