<input name="update_id" id="updateId" type="hidden" class="form-control" value="{{ $users->id }}">
<div class="form-group row">
	<label class="col-sm-2 col-form-label">Name</label>
	<div class="col-sm-10">
		<input name="name" type="text" class="form-control" value="{{ $users->name }}">
		@if ($errors->has('name'))
		<span style="color:red;">{{$errors->first('name')}}</span>
		@endif
	</div>
</div>

<div class="form-group row">
	<label class="col-sm-2 col-form-label">E-Mail Address</label>
	<div class="col-sm-10">
		<input name="email" id="editemailaddress" type="email" class="form-control" value="{{ $users->email }}">
		@if ($errors->has('email'))
		<span style="color:red;">{{$errors->first('email')}}</span>
		@endif
	</div>
</div>

<div class="form-group row">
    <label class="col-sm-2 col-form-label">Mobile No</label>
    <div class="col-sm-10">
        <input name="phone" type="number" class="form-control" value="{{ $users->phone }}">
        @if ($errors->has('phone'))
            <span style="color:red;">{{$errors->first('phone')}}</span>
        @endif
    </div>
</div>

{{--<div class="form-group row">
    <label class="col-sm-2 col-form-label">Role</label>
    <div class="col-sm-10">
        <select class="form-control" name="role_id">
            <option value="">Select Role</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ $role->id == $users->role_id ? 'selected' : ''}}>{{ str_replace('_',' ',ucwords($role->name,'_')) }}</option>
            @endforeach
        </select>
        @if ($errors->has('roles'))
            <span style="color:red;">{{$errors->first('roles')}}</span>
        @endif
    </div>
</div>--}}
