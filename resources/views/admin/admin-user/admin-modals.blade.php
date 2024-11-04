<div id="main-user-modal" class="modal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Admin User</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form method="post" role="form" id="add-main-user-form" enctype="multipart/form-data">
				@csrf
				<div class="modal-body">
					<div class="register-form-errors"></div>
					<div class="form-group row">
						<label class="col-sm-2 col-form-label">Name</label>
						<div class="col-sm-10">
							<input name="name" type="text" class="form-control" value="{{ old('name') }}">
							@if ($errors->has('name'))
							<span style="color:red;">{{$errors->first('name')}}</span>
							@endif
						</div>
					</div>

					<div class="form-group row">
						<label class="col-sm-2 col-form-label">E-Mail Address</label>
						<div class="col-sm-10">
							<input name="email" id="emailaddress" type="email" class="form-control" value="{{ old('email') }}">
							@if ($errors->has('email'))
							<span style="color:red;">{{$errors->first('email')}}</span>
							@endif
						</div>
					</div>

                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label">Mobile No</label>
                        <div class="col-sm-10">
                            <input name="phone" type="number" class="form-control" value="{{ old('phone') }}">
                            @if ($errors->has('phone'))
                                <span style="color:red;">{{$errors->first('phone')}}</span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label">Role</label>
                        <div class="col-sm-10">
                            <select class="form-control" name="role_id" id="role_id">
                                <option value="">Select Role</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ str_replace('_',' ',ucwords($role->name,'_')) }}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('roles'))
                                <span style="color:red;">{{$errors->first('roles')}}</span>
                            @endif
                        </div>
                    </div>

                    

					<div class="form-group row">
						<label for="password" class="col-sm-2 col-form-label">{{ __('Password') }}</label>

						<div class="col-sm-10">
							<input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="new-password">

							@error('password')
							<span class="invalid-feedback" role="alert">
								<strong>{{ $message }}</strong>
							</span>
							@enderror
						</div>
					</div>

					<div class="form-group row">
						<label for="password-confirm" class="col-sm-2 col-form-label">{{ __('Confirm Password') }}</label>

						<div class="col-sm-10">
							<input id="password-confirm" type="password" class="form-control" name="password_confirmation" autocomplete="new-password">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Submit</button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade bd-example-modal-lg" id="editModal" tabindex="-1" role="dialog"
aria-labelledby="addServiceTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="deleteModalLongTitle">Edit Admin User</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <form  method="post" role="form" id="editForm" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="register-form-errors"></div>
                {{csrf_field()}}
                <div id="edi_data_wrap"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-white" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>
</div>

<div class="modal fade in" id="delete_modal" role="dialog" tabindex="-1" aria-labelledby="delete_modal" aria-hidden="true" >
	<div class="modal-dialog modal-dialog-centered" style="width: 400px;">
		<div class="modal-content">
			<!--Modal header-->
			<div class="modal-header">
				<h4 class="modal-title">Confirm Delete</h4>
				<button type="button" class="close" data-dismiss="modal"><i class="fa fa-times-circle-o"></i></button>
			</div>
			<!--Modal body-->
			<div class="modal-body">
				<p>Are You Sure You Want To Delete This Admin User?</p>
				<div class="text-right">
					<form method="post" role="form" id="deleteForm" enctype="multipart/form-data">
						@csrf
						<input type="hidden" name="id" id="deleteid">
						<button data-dismiss="modal" class="btn btn-default btn-sm" type="button" id="modal_close">Close</button>
						<button type="button" class="btn btn-danger btn-sm delete_btn">Delete</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
