<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\KiwiSaverContactUsEmail;

class KiwiSaverContactMessageController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'heard_about' => 'required_without:how_did_you_hear|string|max:255',
            'how_did_you_hear' => 'required_without:heard_about|string|max:255',
        ]);

        // Authorization is handled by api_key middleware group (X-API-Key vs APP_KEY)

        // Normalize field name so the email view has a single key
        if (!$request->filled('heard_about') && $request->filled('how_did_you_hear')) {
            $request->merge(['heard_about' => $request->input('how_did_you_hear')]);
        }

        Mail::to('kiwisaver@halalkiwi.com')->send(new KiwiSaverContactUsEmail($request));

        return response()->json(['message' => 'Mail Sent']);
    }
}


