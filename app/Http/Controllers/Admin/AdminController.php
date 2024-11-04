<?php

namespace App\Http\Controllers\Admin;

use App\Admin;
use DataTables;
use Nette\Utils\Image;
use Illuminate\Http\Request;
use App\Models\Role\CustomRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;


class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('admin.admin');
    }


    public function adminProfile($id)
    {
        $data['user'] = Admin::with('getRole')->where('id',$id)->first();
        if(!empty($data['user'])) {
            return view('admin.adminprofile.profile',$data);
        } else {
            return view('admin.404');
        }
    }

    public function updateAdminProfile(Request $request)
    {
        $admin = Admin::where('id',auth()->user()->id)->first();
        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->save();

        Session::flash('success', 'Admin Details Update successfully!');
        return redirect()->back();
    }

    public function updatePassword(Request $request,$id) {

        $messages = [
            'password.required' => 'The new password field is required',
            'password.confirmed' => "Password does'nt match"
        ];
        $validator = Validator::make($request->all(), [
            'confirmpassword' => 'required',
            'password' => 'required|confirmed'
        ], $messages);

        $user = Admin::find($id);
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json(['status' => 1, 'message' => 'Password changed successfully!']);
    }

    public function updateprofile(Request $request,$id) {

        $request->validate([
            'admin_image' => 'bail|required|file|mimes:jpeg,png,jpg',
        ]);

        $originalImage = $request->file('admin_image');
        $imageName = '';
        if ($originalImage) {
            $imageName = time().$originalImage->getClientOriginalName();
            $thumbnailImage = Image::make($originalImage);
            $thumbnailPath = './assets/frontend/profiles/';
            $originalPath = './assets/frontend/profiles/';
            $thumbnailImage->save($originalPath.$imageName);
            $thumbnailImage->resize(150,150);
        }

        $url = '/assets/frontend/profiles/'.''.$imageName;

        Admin::where('id', $id)->update(['admin_image' => $imageName]);

        return response()->json(['status' => 1, 'message' => 'Admin image update successfully.','url'=>$url]);
    }

    public function adminUser(Request $request){
        // $data['adminUsers'] = Admin::with('roles')->get();
        // $data['roles'] = Role::orderby('name','asc')->pluck('name','name')->all();
        // $data['permissions']=Permission::orderby('name','asc')->pluck('name','name')->all();
        // return view('admin.admin-user.index', $data);

        if ($request->ajax()) {
            $whereData = [
                ['id', '!=', '0']
            ];
            if (!userRoleCheck([1])) {
                $whereData[] = ['id', '=', Auth::id()];
            }
            $users = Admin::with('getRole')->where($whereData)->orderBy('status', 'DESC')->orderBy('id', 'DESC')->get();
            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('admin_image', function ($user) {
                    if(!empty($user->admin_image) && file_exists('assets/frontend/profiles/' . $user->admin_image)){
                    return "<img src='" . url('/') . "/assets/frontend/profiles/" . $user->admin_image . "' width='50' height='50'>";
                }else{
                    return "<img src='" . url('/') . "/assets/images/avatar-1.png' width='50' height='50'>";
                }
                })
                ->addColumn('name', function ($user) {
                    return $user->name;
                })
                ->addColumn('role_name', function ($user) {
                    return str_replace('_',' ',ucwords($user->getRole->name,'_'));
                })
                ->addColumn('status', function ($user) {
                    if ($user->status == '1') {
                        return "<label data-id='".$user->id."' class='label label-info status-update status_list'>Active</label>";
                    }else{
                        return "<label data-id='".$user->id."' class='label label-danger status-update status_list'>Deactive</label>";
                    }
                })
                ->addColumn('actions', function ($user) {
                    $goToShop = $user->getShop && $user->role_id == 2 ? '<a href="'.route('shop.list', $user->getShop->id).'" class="btn btn-outline-danger" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Go to shop"><i class="icofont icofont-share"></i></a>' : '';
                    $data ='<a href="javascript:;" onclick="editMainCategoryModel('.$user->id.')" class="btn btn-outline-warning" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Edit User"><i class="icofont icofont-edit"></i></a>

                        <a href="'.route('admin.adminProfile',$user->id).'" class="btn btn-outline-primary" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="View User"><i class="icofont icofont-eye-alt"></i></a>

                        <button type="button" class="btn btn-outline-danger" onclick="deleteUserModel('.$user->id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Delete User"><i class="icofont icofont-trash"></i>
                        </button>'.$goToShop;

                    return $data;
                })
                ->rawColumns(['admin_image','actions','status'])
                ->make(true);
        } else {
            $data['roles'] = CustomRole::select('id', 'name')->get();
            $data['shopList'] = Shop::select('id', 'shop_name')->where(array('shop_user_status' => 0, 'status' => 1))->get();
            return view('admin.admin-user.index', $data);
        }

    }

    public function addadminUser(Request $request){

        $messages = [
            'name.required' => 'Please enter your name',
            'email.required' => 'Please enter email',
            'role_id.required' => 'Please select role',
            'password.required' => 'Please enter password',
        ];

        $validationArray = [
            'name' => 'required',
            'email' => 'required|unique:users',
            'role_id' => 'required',
            'password' => 'required',
            'phone' => 'required|unique:admins',
        ];
        if ($request->role_id && $request->role_id == 2) {
            $messages['shop_id.required'] = 'Please select shop';
            $validationArray['shop_id'] = 'required';
        }
        $validatedData = $request->validate($validationArray, $messages);

        $userData = Admin::create([
            'name' => $request->name,
            'email'      => $request->email,
            'role_id'      => $request->role_id,
            'shop_id'      => $request->shop_id && $request->role_id == 2 ? $request->shop_id : 0,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
        ]);

        if ($request->role_id && $request->role_id == 2 && $request->shop_id) {
            Shop::where('id', $request->shop_id)->update([
                'shop_user_status' => 1,
            ]);
        }

        $credentials_send = 'admin.admin-user.credentials_send';
        Mail::send($credentials_send, $request->all(), function ($message) use($request){
            $message->to($request->email)
                ->subject("Welcome to ".strtolower(getSiteSetting()->website_title)." for ".getRoleNameBYId($request->role_id)." role.");
        });

        return json_encode([
            'status' => 1,
            'messages' => ucfirst(getRoleNameBYId($request->role_id)).' add successfully',
        ]);
    }

    public function adminUserEdit($id){

        $data['users'] = Admin::find($id);
        $data['roles'] = CustomRole::select('id', 'name')->get();

        $editData = view('admin.admin-user.edit', $data)->render();

        return json_encode([
            'status'=>1,
            'data'=>$editData
        ]);

    }

    public function adminUserchange($id){
        $data['admin'] = Admin::with('roles')->find($id);
        $data['roles'] = Role::orderby('name','asc')->pluck('name','name')->all();
        $data['permissions']=Permission::orderby('name','asc')->pluck('name','name')->all();
        $data['userRole'] = $data['admin']->roles->pluck('name','name')->all();
        $data['userPermissions'] = $data['admin']->permissions->pluck('name','name')->all();
        return view('admin.admin-user.changepassword', $data);
    }

    public function adminUserCreate(Request $request){

        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|unique:admins,email,'.$request->id,
            'username' => 'required|unique:admins,username,'.$request->id,
            'roles' => 'required',
            'password' => 'required|min:8'
        ]);

        $user=Admin::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'phone' => $request->phone,
        ]);

        $user->assignRole($request->roles);
        $user->syncPermissions($request->permissions);

        Session::flash('success', 'User Added successfully!');
        return redirect()->route('admin.users');
    }

    public function adminUserUpdate(Request $request) {

        $messages = [
            'name.required' => 'Please enter your name',
            'email.required' => 'Please enter email',
        ];

        $validatedData = $request->validate([
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required|unique:admins,phone,'.$request->update_id,
        ], $messages);

        Admin::where('id', $request->update_id)->update([
            'name' => $request->name,
            'email'      => $request->email,
            'phone'      => $request->phone,
        ]);

        return json_encode([
            'status'=>1,
            'messages'=> 'User update successfully'
        ]);

    }

    public function adminUserDelete(Request $request){


        $user = Admin::with('roles')->find($request->id);
        $user->delete();

        Session::flash('success', 'User Deleted successfully!');
        return redirect()->route('admin.users');
    }

    public function destroy(Request $request,$id){

        $users = Admin::find($id);
        if ($users->delete()) {
            return response()->json(['status' => 1, 'messages' => 'Admin delete successfully']);
        }
    }

    public function statusUpdate($id)
    {
        $users = Admin::find($id);
        if($users->status == 1) {
            $status = 0;
        } else {
            $status = 1;
        }
        Admin::where('id', $id)->update([
            'status' => $status,
        ]);

        return response()->json(['status' => 1, 'messages' => 'Admin status change successfully.']);
    }

    public function checkUniqueEmail(Request $request)
    {
        $email = $request->email;
        $id = $request->update_id;

        if(!empty($id)) {
            $emailcount = Admin::where('id','!=',$id)->where('email', $email)->get();
            if($emailcount->count()) {
                return json_encode([
                    'msg' => 'true'
                ]);
            } else {
                return json_encode([
                    'msg' => 'false'
                ]);
            }
        } else {
            $emailcount = Admin::where('email', $email)->get();
            if($emailcount->count()) {
                return json_encode([
                    'msg' => 'true'
                ]);
            } else {
                return json_encode([
                    'msg' => 'false'
                ]);
            }
        }
    }

    public function showNotification(Request $request)
    {
        if ($request->ajax()) {
            $notification =  DB::table('notifications')->orderBy('created_at','desc')->get();
            return DataTables::of($notification)
                ->addIndexColumn()
                ->editColumn('data', function ($user) {
                    $message = json_decode($user->data);
                    return $message->data;
                })
                ->addColumn('action', function ($user) {
                    $data ='<button type="button" class="btn btn-outline-danger" onclick="deleteMainCategoryModel('.$user->notifiable_id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Delete Category"><i class="icofont icofont-trash"></i>
                    </button> ';
                    return $data;
                })
                ->rawColumns(['action'])
                ->make(true);
        } else {
            return view('admin.notification');
        }
    }

    public function readNotification()
    {
        DB::table('notifications')
            ->where('read_at','=',NULL)
            ->update(['read_at' => \Carbon\Carbon::now()]);
        return redirect()->route('show.all.notification');
    }

    public function deleteNotification($id)
    {
        DB::table('notifications')
            ->where('notifiable_id',$id)
            ->delete();
        return response()->json(['status' => 1, 'messages' => 'Notification delete successfully']);
    }

    public function OtpSendForDelivery(Request $request)
    {
        $otp = random_int(100000, 999999);
        $order = Orders::where('id',$request->order_id)->first();
        $order->delivery_otp = $otp;
        $order->save();
        $data = array('otp' => $otp);
        $email = 'user@gmail.com';
        $view = 'admin.delivery.otp_send';
        Mail::send($view, $data, function ($message) use ($email) {
            $message->to($email)
                ->subject("Order Confirm Otp");
        });
        return response()->json(['status' => TRUE]);
    }

    public function orderConfirmByOtp(Request $request) {
        $messages = [
            'otp.required' => 'Please enter OTP',
        ];

        $validatedData = $request->validate([
            'otp' => 'required|digits:6',
        ], $messages);

        $conditions = array(
            array('order_status', '=', 0),
            array('delivery_otp', '=', $request->otp),
        );
        $order = Orders::where($conditions)->first();
        if (empty($order) || $order->delivery_otp != $request->otp){
            return response()->json(['status' => FALSE, 'message' => 'Invalid OTP entered']);
        }else{
            $order->order_status = 1;
            $order->save();
            return response()->json(['status' => TRUE, 'message' => 'Order confirm']);
        }
    }


}
