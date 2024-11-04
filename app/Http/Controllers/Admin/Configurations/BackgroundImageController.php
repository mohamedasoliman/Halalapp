<?php

namespace App\Http\Controllers\Admin\InterfaceControl;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\GeneralSettings as GS;
use Session;

class BackgroundImageController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!userRoleCheck([1])) {
                return redirect()->route('admin.dashboard');
            }
            return $next($request);
        });
    }

    public function background()
    {
        return view('backend.interfaceControl.background.index');
    }

    public function backgroundUpdate(Request $request)
    {

        $validatedRequest = $request->validate([
            'banner' => 'image|mimes:jpeg,png,jpg,gif,svg|max:8048',
            'footer' => 'image|mimes:jpeg,png,jpg,gif,svg|max:8048',
            'subscribe' => 'image|mimes:jpeg,png,jpg,gif,svg|max:8048',
        ]);

        if ($request->hasFile('banner')) {
            $bannerImagePath = './assets/frontend/interfaceControl/backgroundImage/banner.jpg';
            @unlink($bannerImagePath);
            $request->file('banner')->move('assets/frontend/interfaceControl/backgroundImage/', 'banner.jpg');
        }
        if ($request->hasFile('footer')) {
            $footerImagePath = './assets/frontend/interfaceControl/backgroundImage/footer.jpg';
            @unlink($footerImagePath);
            $request->file('footer')->move('assets/frontend/interfaceControl/backgroundImage/', 'footer.jpg');
        }
        if ($request->hasFile('subscribe')) {
            $footerImagePath = './assets/frontend/interfaceControl/backgroundImage/subscribe.jpg';
            @unlink($footerImagePath);
            $request->file('subscribe')->move('assets/frontend/interfaceControl/backgroundImage/', 'subscribe.jpg');
        }

        return back()->with('success', 'Background Image  Updated');
    }
}
