<?php

namespace App\Http\Controllers\Admin\Configurations;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\GeneralSetting as GS;
use App\Models\Social;
use Session;

class SocialController extends Controller
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

    public function index()
    {
        $data['socials'] = Social::all();
        $data['mode'] = 'Add';
        return view('admin.configurations.socialsetting.index', $data);
    }

    public function store(Request $request)
    {
        $messages = [
            'fontawesome_code.required' => 'Social Icon is required!',
            'icon.required' => 'Social Name is required!',
            'title.required' => 'URL field is required',
        ];
        $validatedData = $request->validate([
            'fontawesome_code' => 'required',
            'icon' => 'required',
            'title' => 'required',
        ], $messages);
        $social = new Social;
        $social->fontawesome_code = $request->fontawesome_code;
        $social->title = $request->icon;
        $social->url = $request->title;
        $social->save();
        Session::flash('success', 'New social link added successfully!');
        return redirect()->back();
    }

    public function delete(Request $request)
    {
        $social = Social::find($request->socialID);
        $social->delete();
        Session::flash('error', 'Social Deleted Successfully');
        return redirect()->back();
    }

    public function edit($id)
    {
        $editSocial = Social::findOrFail($id);
        $mode = 'Edit';
        return view('admin.configurations.socialsetting.index', compact('editSocial', 'mode'));
    }

    public function update(Request $request)
    {
        $messages = [
            'fontawesome_code.required' => 'Social Icon is required!',
            'icon.required' => 'Social Name is required!',
            'title.required' => 'URL field is required',
        ];
        $validatedData = $request->validate([
            'fontawesome_code' => 'required',
            'icon' => 'required',
            'title' => 'required',
        ], $messages);

        $id = $request->id;
        $social = Social::findOrFail($id);
        $social->fontawesome_code = $request->fontawesome_code;
        $social->title = $request->icon;
        $social->url = $request->title;
        if ($social->save()) {
            Session::flash('success', 'Social link update successfully!');
            return redirect()->route('admin.social.index');
        }
    }
}
