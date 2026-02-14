<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Api\FatwaContactRequest;
use App\Mail\FatwaContactUsEmail;
use Illuminate\Support\Facades\Mail;

class FatwaContactMessageController extends Controller
{

    public function send(FatwaContactRequest $request)
{
    $attachmentPath = null;
    if ($request->hasFile('attachment')) {
        $attachmentPath = $request->file('attachment')->store('attachments');
    }

    Mail::to('fatwa@halalkiwi.com')->send(new FatwaContactUsEmail($request, $attachmentPath));

}
}
