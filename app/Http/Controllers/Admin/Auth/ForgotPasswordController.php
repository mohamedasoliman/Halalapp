<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ForgotPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest:admin');
    }

    public function showLinkRequestForm()
    {
        return view('admin.auth.passwords.email');
    }

    public function sendResetLinkEmails(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $activeAdminExists = Admin::query()
            ->where('email', $request->string('email')->toString())
            ->where('status', 1)
            ->exists();

        if ($activeAdminExists) {
            Password::broker('admins')->sendResetLink($request->only('email'));
        }

        // Always return the same response so this endpoint cannot enumerate admins.
        return back()->with(
            'success',
            'If an active administrator account exists for that address, a reset link has been sent.'
        );
    }

    public function getPassword(Request $request, string $token)
    {
        return view('admin.auth.passwords.reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        if (! Admin::query()->where('email', $credentials['email'])->where('status', 1)->exists()) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ]);
        }

        $status = Password::broker('admins')->reset(
            $credentials,
            function (Admin $admin, string $password): void {
                $admin->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ]);
        }

        return redirect()->route('admin.login')->with('success', 'Your password has been changed.');
    }
}
