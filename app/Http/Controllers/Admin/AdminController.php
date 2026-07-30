<?php

namespace App\Http\Controllers\Admin;

use App\Admin;
use App\Http\Controllers\Controller;
use App\Models\MasjidModel\Masjid;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\Role\CustomRole;
use Carbon\Carbon;
use DataTables;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Nette\Utils\Image;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
     * @return Renderable
     */
    public function index()
    {
        $stats = [];

        try {
            $stats['total_products'] = Product::count();
            $stats['active_products'] = Product::where('status', 1)->count();
            $stats['halal_products'] = Product::where('halal_status', 0)->count();
            $stats['not_halal_products'] = Product::where('halal_status', 1)->count();
            $stats['not_sure_products'] = Product::where('halal_status', 2)->count();
            $stats['mashbooh_products'] = Product::where('halal_status', 3)->count();
            $stats['total_mosques'] = Masjid::count();
            $restaurantJson = public_path('data/HalalRestaurantsList.json');
            $stats['total_restaurants'] = file_exists($restaurantJson) ? count(json_decode(file_get_contents($restaurantJson), true) ?? []) : 0;
            $stats['total_admins'] = Admin::count();
            $stats['pending_requests'] = PrioritisationRequest::whereNotIn('status', ['resolved', 'dead_end'])->count();
            $stats['review_requests'] = PrioritisationRequest::where('status', 'ready_for_review')->count();
        } catch (\Exception $e) {
            // Tables may not exist yet during setup
        }

        return view('admin.admin', compact('stats'));
    }

    public function adminProfile($id)
    {
        $authenticatedAdmin = Auth::guard('admin')->user();
        abort_unless(
            (int) $authenticatedAdmin->id === (int) $id || (int) $authenticatedAdmin->role_id === 1,
            403
        );

        $data['user'] = Admin::with('getRole')->where('id', $id)->first();
        if (! empty($data['user'])) {
            return view('admin.adminprofile.profile', $data);
        } else {
            return view('admin.404');
        }
    }

    public function updateAdminProfile(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        $admin->save();

        Session::flash('success', 'Admin Details Update successfully!');

        return redirect()->back();
    }

    public function updatePassword(Request $request, $id)
    {
        $authenticatedAdmin = Auth::guard('admin')->user();

        abort_unless(
            (int) $authenticatedAdmin->id === (int) $id || (int) $authenticatedAdmin->role_id === 1,
            403
        );

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $authenticatedAdmin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current administrator password is incorrect.',
            ]);
        }

        $user = Admin::findOrFail($id);
        $user->password = Hash::make($validated['password']);
        $user->remember_token = Str::random(60);
        $user->save();

        $request->session()->regenerate();

        return response()->json(['status' => 1, 'message' => 'Password changed successfully!']);
    }

    public function updateprofile(Request $request, $id = null)
    {
        $authenticatedAdmin = Auth::guard('admin')->user();
        $id ??= $authenticatedAdmin->id;

        abort_unless(
            (int) $authenticatedAdmin->id === (int) $id || (int) $authenticatedAdmin->role_id === 1,
            403
        );

        $request->validate([
            'admin_image' => 'bail|required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $originalImage = $request->file('admin_image');
        $imageName = '';
        if ($originalImage) {
            $imageName = Str::uuid().'.'.$originalImage->extension();
            $thumbnailImage = Image::make($originalImage);
            $originalPath = './assets/frontend/profiles/';
            if (! is_dir($originalPath)) {
                mkdir($originalPath, 0755, true);
            }
            $thumbnailImage->save($originalPath.$imageName);
            $thumbnailImage->resize(150, 150);
        }

        $url = '/assets/frontend/profiles/'.''.$imageName;

        Admin::where('id', $id)->update(['admin_image' => $imageName]);

        return response()->json(['status' => 1, 'message' => 'Admin image update successfully.', 'url' => $url]);
    }

    public function adminUser(Request $request)
    {
        // $data['adminUsers'] = Admin::with('roles')->get();
        // $data['roles'] = Role::orderby('name','asc')->pluck('name','name')->all();
        // $data['permissions']=Permission::orderby('name','asc')->pluck('name','name')->all();
        // return view('admin.admin-user.index', $data);

        if ($request->ajax()) {
            $whereData = [
                ['id', '!=', '0'],
            ];
            if (! userRoleCheck([1])) {
                $whereData[] = ['id', '=', Auth::id()];
            }
            $users = Admin::with('getRole')->where($whereData)->orderBy('status', 'DESC')->orderBy('id', 'DESC')->get();

            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('admin_image', function ($user) {
                    if (! empty($user->admin_image) && file_exists('assets/frontend/profiles/'.$user->admin_image)) {
                        $imageUrl = e(url('/').'/assets/frontend/profiles/'.$user->admin_image);

                        return "<img src='{$imageUrl}' width='50' height='50' alt=''>";
                    } else {
                        $imageUrl = e(url('/').'/assets/images/avatar-1.png');

                        return "<img src='{$imageUrl}' width='50' height='50' alt=''>";
                    }
                })
                ->addColumn('name', function ($user) {
                    return $user->name;
                })
                ->addColumn('role_name', function ($user) {
                    return str_replace('_', ' ', ucwords($user->getRole->name, '_'));
                })
                ->addColumn('status', function ($user) {
                    if ($user->status == '1') {
                        return "<label data-id='".$user->id."' class='label label-info status-update status_list'>Active</label>";
                    } else {
                        return "<label data-id='".$user->id."' class='label label-danger status-update status_list'>Deactive</label>";
                    }
                })
                ->addColumn('actions', function ($user) {
                    $goToShop = '';
                    $data = '<a href="javascript:;" onclick="editMainCategoryModel('.$user->id.')" class="btn btn-outline-warning" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Edit User"><i class="icofont icofont-edit"></i></a>

                        <a href="'.route('admin.adminProfile', $user->id).'" class="btn btn-outline-primary" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="View User"><i class="icofont icofont-eye-alt"></i></a>

                        <button type="button" class="btn btn-outline-danger" onclick="deleteUserModel('.$user->id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Delete User"><i class="icofont icofont-trash"></i>
                        </button>'.$goToShop;

                    return $data;
                })
                ->rawColumns(['admin_image', 'actions', 'status'])
                ->make(true);
        } else {
            $data['roles'] = CustomRole::select('id', 'name')->get();

            return view('admin.admin-user.index', $data);
        }

    }

    public function addadminUser(Request $request)
    {

        $messages = [
            'name.required' => 'Please enter your name',
            'email.required' => 'Please enter email',
            'role_id.required' => 'Please select role',
            'password.required' => 'Please enter password',
        ];

        $validationArray = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:admins,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
            'phone' => ['required', 'string', 'max:32', 'unique:admins,phone'],
        ];
        $validatedData = $request->validate($validationArray, $messages);

        $userData = Admin::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'role_id' => $validatedData['role_id'],
            'phone' => $validatedData['phone'],
            'password' => Hash::make($validatedData['password']),
            'status' => 1,
        ]);

        Password::broker('admins')->sendResetLink(['email' => $userData->email]);

        return json_encode([
            'status' => 1,
            'messages' => ucfirst(getRoleNameBYId($request->role_id)).' added. A secure password setup link was sent.',
        ]);
    }

    public function adminUserEdit($id)
    {

        $data['users'] = Admin::find($id);
        $data['roles'] = CustomRole::select('id', 'name')->get();

        $editData = view('admin.admin-user.edit', $data)->render();

        return json_encode([
            'status' => 1,
            'data' => $editData,
        ]);

    }

    public function adminUserchange($id)
    {
        $data['admin'] = Admin::with('roles')->find($id);
        $data['roles'] = Role::orderby('name', 'asc')->pluck('name', 'name')->all();
        $data['permissions'] = Permission::orderby('name', 'asc')->pluck('name', 'name')->all();
        $data['userRole'] = $data['admin']->roles->pluck('name', 'name')->all();
        $data['userPermissions'] = $data['admin']->permissions->pluck('name', 'name')->all();

        return view('admin.admin-user.changepassword', $data);
    }

    public function adminUserCreate(Request $request)
    {

        $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc|max:255|unique:admins,email,'.$request->id,
            'username' => 'required|string|max:255|unique:admins,username,'.$request->id,
            'roles' => 'required',
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        $user = Admin::create([
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

    public function adminUserUpdate(Request $request)
    {

        $messages = [
            'name.required' => 'Please enter your name',
            'email.required' => 'Please enter email',
        ];

        $validatedData = $request->validate([
            'update_id' => 'required|integer|exists:admins,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc|max:255|unique:admins,email,'.$request->update_id,
            'phone' => 'required|unique:admins,phone,'.$request->update_id,
        ], $messages);

        Admin::where('id', $request->update_id)->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return json_encode([
            'status' => 1,
            'messages' => 'User update successfully',
        ]);

    }

    public function adminUserDelete(Request $request)
    {
        $user = Admin::with('roles')->findOrFail($request->id);
        $this->assertAdminCanBeDisabledOrDeleted($user);
        $user->delete();

        Session::flash('success', 'User Deleted successfully!');

        return redirect()->route('admin.users');
    }

    public function destroy(Request $request, $id)
    {
        $users = Admin::findOrFail($id);
        $this->assertAdminCanBeDisabledOrDeleted($users);
        if ($users->delete()) {
            return response()->json(['status' => 1, 'messages' => 'Admin delete successfully']);
        }
    }

    public function statusUpdate($id)
    {
        $users = Admin::findOrFail($id);
        if ((int) $users->status === 1) {
            $this->assertAdminCanBeDisabledOrDeleted($users);
        }
        if ($users->status == 1) {
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

        if (! empty($id)) {
            $emailcount = Admin::where('id', '!=', $id)->where('email', $email)->get();
            if ($emailcount->count()) {
                return json_encode([
                    'msg' => 'true',
                ]);
            } else {
                return json_encode([
                    'msg' => 'false',
                ]);
            }
        } else {
            $emailcount = Admin::where('email', $email)->get();
            if ($emailcount->count()) {
                return json_encode([
                    'msg' => 'true',
                ]);
            } else {
                return json_encode([
                    'msg' => 'false',
                ]);
            }
        }
    }

    public function showNotification(Request $request)
    {
        if ($request->ajax()) {
            $notification = DB::table('notifications')->orderBy('created_at', 'desc')->get();

            return DataTables::of($notification)
                ->addIndexColumn()
                ->editColumn('data', function ($user) {
                    $message = json_decode($user->data);

                    return $message->data;
                })
                ->addColumn('action', function ($user) {
                    $data = '<button type="button" class="btn btn-outline-danger" onclick="deleteMainCategoryModel('.$user->notifiable_id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Delete Category"><i class="icofont icofont-trash"></i>
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
            ->where('read_at', '=', null)
            ->update(['read_at' => Carbon::now()]);

        return redirect()->route('show.all.notification');
    }

    public function deleteNotification($id)
    {
        DB::table('notifications')
            ->where('notifiable_id', $id)
            ->delete();

        return response()->json(['status' => 1, 'messages' => 'Notification delete successfully']);
    }

    public function OtpSendForDelivery(Request $request)
    {
        $otp = random_int(100000, 999999);
        $order = Orders::where('id', $request->order_id)->first();
        $order->delivery_otp = $otp;
        $order->save();
        $data = ['otp' => $otp];
        $email = 'user@gmail.com';
        $view = 'admin.delivery.otp_send';
        Mail::send($view, $data, function ($message) use ($email) {
            $message->to($email)
                ->subject('Order Confirm Otp');
        });

        return response()->json(['status' => true]);
    }

    public function orderConfirmByOtp(Request $request)
    {
        $messages = [
            'otp.required' => 'Please enter OTP',
        ];

        $validatedData = $request->validate([
            'otp' => 'required|digits:6',
        ], $messages);

        $conditions = [
            ['order_status', '=', 0],
            ['delivery_otp', '=', $request->otp],
        ];
        $order = Orders::where($conditions)->first();
        if (empty($order) || $order->delivery_otp != $request->otp) {
            return response()->json(['status' => false, 'message' => 'Invalid OTP entered']);
        } else {
            $order->order_status = 1;
            $order->save();

            return response()->json(['status' => true, 'message' => 'Order confirm']);
        }
    }

    private function assertAdminCanBeDisabledOrDeleted(Admin $admin): void
    {
        abort_if(
            (int) $admin->id === (int) Auth::guard('admin')->id(),
            422,
            'You cannot disable or delete your own administrator account.'
        );

        if ((int) $admin->role_id === 1) {
            $activeSuperAdmins = Admin::query()
                ->where('role_id', 1)
                ->where('status', 1)
                ->whereKeyNot($admin->id)
                ->count();

            abort_if(
                $activeSuperAdmins === 0,
                422,
                'At least one active full administrator must remain.'
            );
        }
    }
}
