<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AdminLoginController extends Controller
{
	public function __construct()
	{
		$this->middleware('guest:admin', ['except' => ['logout']]);
	}

	public function showLoginForm()
	{
		return view('admin.auth.login');
	}

	public function login(Request $request)
	{
		$this->validate($request,[
			'email' => 'required|email',
			'password' => 'required|min:6'
		]);

		if(Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password], $request->remember))
		{
		    Session::put('admin_session_timeout', now()->addMinutes(5));
			return redirect()->intended(route('admin.dashboard'));
		}

		return redirect()->back()->withInput($request->only('email','remember'))->with('error','Email and Password Not Match');;
	}

	public function logout()
	{
		Auth::guard('admin')->logout();
		return redirect('/admin/login');
	}
}
