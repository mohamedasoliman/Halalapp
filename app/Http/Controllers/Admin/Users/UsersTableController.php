<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use App\User;
use Carbon\Carbon;

class UsersTableController extends Controller
{
	public function __invoke()
	{
		return Datatables::make($this->getForDataTable())
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
				return "<label class='label label-info'>Active</label>";
			}else{
				return "<label class='label label-danger'>Deactive</label>";
			}
		})
		->addColumn('actions', function ($user) {
			$data ='<a href="'.route('users.edit',$user->id).'" class="btn btn-outline-warning" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Edit User"><i class="icofont icofont-edit"></i></a>
			<a href="'.route('users.view',$user->id).'" class="btn btn-outline-primary" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="View User"><i class="icofont icofont-eye-alt"></i></a> 
			
			<button type="button" class="btn btn-outline-danger" onclick="deleteUserModel('.$user->id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Delete User"><i class="icofont icofont-trash"></i>
			</button> ';

			// if($user->email_verified_at == ""){
			// 	$data .='<a href="javascript:;" class="btn btn-outline-warning" onclick="UserVerifyModal('.$user->id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Verify Member"> <i class="fa fa-check"></i></a> ';
			// }else{	
			// 	$data .='<a href="javascript:;" class="btn btn-success" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Email Verifed"><i class="fa fa-thumbs-up"></i></a> ';
			// }
			if($user->status != "0"){
				$data .='<a href="javascript:;" class="btn btn-outline-info" onclick="UserBlockModal('.$user->id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Deactive User"> <i class="icofont icofont-ban"></i></a> ';
			}else{
				$data .='<a href="javascript:;" class="btn btn-outline-info" onclick="UserUnblockModal('.$user->id.')" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Active User"> <i class="icofont icofont-check"></i></a> ';
			}
			return $data;
		})
		->rawColumns(['avatar_location','gender','actions','status'])
		->make(true);
	}

	public function getForDataTable()
	{
        /**
         * Note: You must return deleted_at or the Career getActionButtonsAttribute won't
         * be able to differentiate what buttons to show for each row.
         */
        return User::query();
    }
}
