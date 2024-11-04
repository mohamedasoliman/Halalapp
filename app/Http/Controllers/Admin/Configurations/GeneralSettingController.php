<?php

namespace App\Http\Controllers\Admin\Configurations;

use App\MetaField;
use Nette\Utils\Image;
use Illuminate\Http\Request;
use App\Models\GeneralSetting;
use App\Http\Controllers\Controller;
use App\Models\ProductModel\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class GeneralSettingController extends Controller
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

    public function GenSetting()
    {
        return view('admin.configurations.general-setting.index');
    }

    public function UpdateGenSetting(Request $request)
    {
        $messages = [
            'websiteTitle.required' => 'Website Title is required',
        ];

        $validator = Validator::make($request->all(), [
            'websiteTitle' => 'required',
            'gst' => 'numeric|between:0,99',
            'price_commission' => 'numeric|between:0,99',

        ], $messages);

        if ($validator->fails()) {
            return redirect()->route('admin.GenSetting')->withErrors($validator);
        }

        $generalSettings = GeneralSetting::first();

        $generalSettings->website_title = $request->websiteTitle;
        $generalSettings->footer = $request->footer;
        $generalSettings->address = $request->address;
        $generalSettings->email = $request->email;
        $generalSettings->phone = $request->phone;
        $generalSettings->gst = $request->gst;

        $generalSettings->save();

        Session::flash('success', 'Successfully Updated!');


        return redirect()->route('admin.GenSetting');
    }

    public function TimerSetting()
    {
        $data['start_time'] = MetaField::where('name', 'start_time')->first()->value;
        $data['close_time'] = MetaField::where('name', 'close_time')->first()->value;

        return view('admin.configurations.footer_timer.index', $data);
    }

    public function TimerSettingSave(Request $request)
    {
        $request->validate([
            'start_time' => 'required',
            'close_time' => 'required',
        ]);

        MetaField::where('name', 'start_time')->update(['value' => $request->start_time]);
        MetaField::where('name', 'close_time')->update(['value' => $request->close_time]);
        Session::flash('success', 'Successfully Updated!');
        return redirect()->route('admin.TimerSetting');
    }

    public function bannerSetting()
    {
        $data['banner_1'] = getsSiteMetaField('banner_1');
        $data['banner_2'] = getsSiteMetaField('banner_2');
        $data['banner_3'] = getsSiteMetaField('banner_3');
        return view('admin.configurations.banner.index', $data);
    }

    public function bannerSettingSave(Request $request)
    {
        $request->validate([
            'banner2_text' => 'required',
            'banner3_text' => 'required',
            'banner1' => 'required|mimes:jpeg,png,jpg|max:2048|dimensions:min_width=640,min_height=640,max_width=640,max_height=640',
            'banner2_image' => 'required|mimes:jpeg,png,jpg|max:2048|dimensions:min_width=1270,min_height=640,max_width=1270,max_height=640',
            'banner3_image' => 'required|mimes:jpeg,png,jpg|max:2048|dimensions:min_width=260,min_height=152,max_width=260,max_height=152',
        ], [
            'banner1.dimensions' => 'For banner1 select height: 640px and width: 640px image.',
            'banner2_image.dimensions' => 'For banner2 select height: 640px and width: 1270px image.',
            'banner3_image.dimensions' => 'For banner3 select height: 152px and width: 260px image.',
        ]);

        $originalImage1 = $request->file('banner1');
        $banner_1_image = getsSiteMetaField('banner_1');
        if ($originalImage1) {
            if (!empty($banner_1_image) && file_exists(public_path() . '/upload/banner/banner1/'.$banner_1_image->value)){
                @unlink('./public/upload/banner/banner1/'.$banner_1_image->value);
//                unlink(public_path() . '/upload/banner/banner1/'.$banner_1_image);
            }

            $imageName = time() . $originalImage1->getClientOriginalName();
            $thumbnailImage = Image::make($originalImage1);
            $originalPath = public_path() . '/upload/banner/banner1/';
            $thumbnailImage->save($originalPath . $imageName);

            MetaField::updateOrCreate(['name' => 'banner_1'], ['value' => $imageName]);
        }


        $originalImage2 = $request->file('banner2_image');
        $banner2 = array(
            'banner_text' => $request->banner2_text,
        );
        $getBanner2Data = getsSiteMetaField('banner_2');
        if (!empty($getBanner2Data)){
            $banner_2_image = json_decode(getsSiteMetaField('banner_2')->value, true)['banner_image'];
            $banner2['banner_image'] = $banner_2_image;
        }

        if ($originalImage2) {
            if (!empty($getBanner2Data) && file_exists(public_path() . '/upload/banner/banner2/'.$banner_2_image)){
                @unlink('./upload/banner/banner2/'.$banner_2_image);
            }

            $imageName = time() . $originalImage2->getClientOriginalName();
            $thumbnailImage = Image::make($originalImage2);
            $originalPath = public_path() . '/upload/banner/banner2/';
            $thumbnailImage->save($originalPath . $imageName);

            $banner2['banner_image'] = $imageName;
        }

        $originalImage2 = $request->file('banner2_image');
        $banner2 = array(
            'banner_text' => $request->banner2_text,
        );
        $getBanner2Data = getsSiteMetaField('banner_2');
        if (!empty($getBanner2Data)){
            $banner_2_image = json_decode(getsSiteMetaField('banner_2')->value, true)['banner_image'];
            $banner2['banner_image'] = $banner_2_image;
        }

        if ($originalImage2) {
            if (!empty($getBanner2Data) && file_exists(public_path() . '/upload/banner/banner2/'.$banner_2_image)){
                @unlink('./upload/banner/banner2/'.$banner_2_image);
            }

            $imageName = time() . $originalImage2->getClientOriginalName();
            $thumbnailImage = Image::make($originalImage2);
            $originalPath = public_path() . '/upload/banner/banner2/';
            $thumbnailImage->save($originalPath . $imageName);

            $banner2['banner_image'] = $imageName;
        }

        //For banner 3
        $originalImage3 = $request->file('banner3_image');
        $banner3 = array(
            'banner_text' => $request->banner3_text,
        );
        $getBanner3Data = getsSiteMetaField('banner_3');
        if (!empty($getBanner3Data)){
            $banner_3_image = json_decode(getsSiteMetaField('banner_3')->value, true)['banner_image'];
            $banner3['banner_image'] = $banner_3_image;
        }

        if ($originalImage3) {
            if (!empty($getBanner3Data) && file_exists(public_path() . '/upload/banner/banner3/'.$banner_3_image)){
                @unlink('./upload/banner/banner3/'.$banner_3_image);
            }

            $imageName = time() . $originalImage3->getClientOriginalName();
            $thumbnailImage = Image::make($originalImage3);
            $originalPath = public_path() . '/upload/banner/banner3/';
            $thumbnailImage->save($originalPath . $imageName);

            $banner3['banner_image'] = $imageName;
        }

        $banner2 = json_encode($banner2, true);
        $banner3 = json_encode($banner3, true);

        MetaField::updateOrCreate(['name' => 'banner_2'], ['value' => $banner2]);
        MetaField::updateOrCreate(['name' => 'banner_3'], ['value' => $banner3]);
//        MetaField::where('name', 'banner_3')->update(['value' => $banner3]);

        return response()->json(['status' => TRUE, 'message' => 'Banner update successfully']);
    }
}
