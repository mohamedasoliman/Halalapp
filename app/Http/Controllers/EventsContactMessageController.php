<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Api\EventsContactRequest;
use App\Mail\EventsContactUsEmail;
use Illuminate\Support\Facades\Mail;

class EventsContactMessageController extends Controller
{

    public function send(EventsContactRequest $request)
{
    $attachmentPath = null;
    if ($request->hasFile('attachment')) {
        $attachmentPath = $request->file('attachment')->store('attachments');
    }

    Mail::to('events@halalkiwi.com')->send(new EventsContactUsEmail($request, $attachmentPath));

}
}
