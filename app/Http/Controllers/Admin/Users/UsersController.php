<?php

namespace App\Http\Controllers\Admin\Users;

use App\User;
use DataTables;
use Nette\Utils\Image;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $users = User::orderBy('status', 'DESC')->orderBy('id', 'DESC')->get();
            return Datatables::of($users)
                ->addIndexColumn()
                ->addColumn('avatar_location', function ($user) {
                    if(!empty($user->avatar_location) && file_exists('assets/frontend/profiles/' . $user->avatar_location)){
                    return "<img src='" . url('/') . "/assets/frontend/profiles/" . $user->avatar_location . "' width='50' height='50'>";
                }else{
                    if($user->gender == 1)
                    {
                        return "<img src='" . url('/') . "/assets/images/avatar-1.png' width='50' height='50'>";
                    }
                    else
                    {
                        return "<img src='" . url('/') . "/assets/images/avatar-5.png' width='50' height='50'>";
                    }
                }
                })
                ->addColumn('name', function ($user) {
                    return $user->first_name.' '.$user->last_name;
                })
                ->addColumn('gender', function ($user) {
                    if ($user->gender == '1') {
                        return "<label class='label label-primary'>Male</label>";
                    }else{
                        return "<label class='label label-warning'>Female</label>";
                    }
                })
                ->addColumn('status', function ($user) {
                    if ($user->status == '1') {
                        return "<label data-id='".$user->id."' class='label label-info status-update status_list'>Active</label>";
                    }else{
                        return "<label data-id='".$user->id."' class='label label-danger status-update status_list'>Deactive</label>";
                    }
                })
                ->addColumn('actions', function ($user) {
                    $data ='<a href="javascript:;" onclick="editMainCategoryModel('.$user->id.')" class="btn btn-outline-warning" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Edit User"><i class="icofont icofont-edit"></i></a>

                        <a href="'.route('users.view',$user->id).'" class="btn btn-outline-primary" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="View User"><i class="icofont icofont-eye-alt"></i></a>

                        <button type="button" class="btn btn-outline-danger" onclick="deleteUserModel('.$user->id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Delete User"><i class="icofont icofont-trash"></i>
                        </button> ';

                    return $data;
                })
                ->rawColumns(['avatar_location','actions','status','gender'])
                ->make(true);
        } else {
            return view('admin.users.index');
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $messages = [
            'first_name.required' => 'Please enter first name',
            'last_name.required' => 'Please enter last name',
            'email.required' => 'Please enter email',
            'mobile_no.required' => 'Please enter mobile no',
            'gender.required' => 'Please select gender',
            'password.required' => 'Please enter password',
        ];

        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|unique:users',
            'mobile_no' => 'required|unique:users',
            'gender' => 'required',
            'password' => 'required',
        ], $messages);

        User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'mobile_no'  => $request->mobile_no,
            'gender'     => $request->gender,
            'created_by' => Auth::user()->id,
            'password'   => Hash::make($request->password),
        ]);

        return json_encode([
            'status' => 1,
            'messages' => 'User add successfully',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data['users'] = User::find($id);

        $editData = view('admin.users.edit', $data)->render();

        return json_encode([
            'status'=>1,
            'data'=>$editData
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $users = User::find($id);
        if ($users->delete()) {
            return response()->json(['status' => 1, 'messages' => 'User delete successfully']);
        }
    }

    public function UserDelete(Request $request){

        $user = User::find($request->id);
        $user->delete();

        Session::flash('error', 'User Deleted successfully!');
        return redirect()->route('users.index');
    }

    public function UserBlock(Request $request){

        $user = User::find($request->id);

        User::where('id', $request->id)->update(['status' => 0]);

        return response()->json(['status' => 1, 'messages' => 'User Deactive successfully!']);
    }

    public function UserUnBlock(Request $request){

        $user = User::find($request->id);

        User::where('id', $request->id)->update(['status' => 1]);

        Session::flash('success', 'User Active successfully!');
        return redirect()->route('users.index');
    }

    public function UserView($id){

        $data['user'] = User::where('id', $id)->first();
        if(!empty($data['user'])) {
            return view('admin.users.userview',$data);
        } else {
            return view('admin.404');
        }
    }

    public function userPassword(Request $request,$id) {

        $messages = [
            'password.required' => 'The new password field is required',
            'password.confirmed' => "Password does'nt match"
        ];
        $validator = Validator::make($request->all(), [
            'confirmpassword' => 'required',
            'password' => 'required|confirmed'
        ], $messages);

        $user = User::find($id);
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json(['status' => 1, 'message' => 'Password changed successfully!']);

    }

    public function userProfileUpdate(Request $request,$id)
    {
        $request->validate([
            'avatar_location' => 'bail|required|file|max:5000kb|mimes:jpeg,png,jpg',
        ]);

        $originalImage = $request->file('avatar_location');
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

        User::where('id', $id)->update(['avatar_location' => $imageName]);

        return response()->json(['status' => 1, 'message' => 'Profile image update successfully.','url'=>$url]);
    }

    public function addUsers()
    {
        return view('admin.users.adduser');
    }

    public function storeUsers(Request $request)
    {
        User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'mobile_no'  => $request->mobile_no,
            'gender'     => $request->gender,
            'password'   => Hash::make($request->password),
        ]);

        Session::flash('success', 'Profile Add successfully!');
        return redirect()->route('users.index');
    }

    public function Useredit($id)
    {
        $data['users'] = User::find($id);

        $editData = view('admin.users.edit', $data)->render();

        return json_encode([
            'status'=>1,
            'data'=>$editData
        ]);
    }

    public function userUpdate(Request $request)
    {
        $messages = [
            'first_name.required' => 'Please enter first name',
            'last_name.required' => 'Please enter last name',
            'email.required' => 'Please enter email',
            'mobile_no.required' => 'Please enter mobile no',
            'gender.required' => 'Please select gender',
        ];

        $validatedData = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'mobile_no' => 'required',
            'gender' => 'required',
        ], $messages);

        User::where('id', $request->update_id)->update([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'mobile_no'  => $request->mobile_no,
            'created_by' => Auth::user()->id,
            'gender'     => $request->gender,
        ]);

        return json_encode([
            'status'=>1,
            'messages'=> 'Users update successfully'
        ]);
    }

    public function statusUpdate($id)
    {
        $users = User::find($id);
        if($users->status == 1) {
            $status = 0;
        } else {
            $status = 1;
        }
        User::where('id', $id)->update([
            'status' => $status,
        ]);

        return response()->json(['status' => TRUE, 'messages' => 'User status change successfully.']);
    }

    public function checkUniqueEmail(Request $request)
    {
        $email = $request->email;
        $id = $request->update_id;

        if(!empty($id)) {
            $emailcount = User::where('id','!=',$id)->where('email', $email)->get();
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
            $emailcount = User::where('email', $email)->get();
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

    public function checkUniqueMobileNo(Request $request)
    {
        $mobileno = $request->mobileno;
        $id = $request->update_id;
        if(!empty($id)) {
            $mobilecount = User::where('id','!=',$id)->where('mobile_no', $mobileno)->get();
            if($mobilecount->count()) {
                return json_encode([
                    'msg' => 'true'
                ]);
            } else {
                return json_encode([
                    'msg' => 'false'
                ]);
            }
        } else {
            $mobilecount = User::where('mobile_no', $mobileno)->get();
            if($mobilecount->count()) {
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
}
