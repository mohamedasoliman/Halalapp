<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Api\ContactRequest;
use App\Mail\ContactUsEmail;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{

    public function send(ContactRequest $request)
    {
        try {

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
