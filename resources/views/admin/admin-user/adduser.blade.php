@extends('admin.layouts.app')
@section('content')
@include('admin.include.sidebar')

<div class="pcoded-main-container">
	<div class="pcoded-wrapper">
		<div class="pcoded-content">
			<div class="pcoded-inner-content">

				<!-- Main-body start -->
				<div class="main-body">
					<div class="page-wrapper">
						<!-- Page header start -->
						<div class="page-header">
							<div class="page-header-title">
								<h4>Admin Users</h4>
							</div>
							<div class="page-header-breadcrumb">
								<ul class="breadcrumb-title">
									<li class="breadcrumb-item">
										<a href="{{ route('admin.dashboard') }}">
											<i class="icofont icofont-home"></i>
										</a>
									</li>
									<li class="breadcrumb-item"><a href="javascript:;">Admin Users</a>
									</li>
								</ul>
							</div>
						</div>
						<!-- Page header end -->
						<!-- Page body start -->
						<div class="page-body">
							<div class="row">
								<div class="col-sm-12">
									<!-- Basic Form Inputs card start -->
									@include('admin.messages')
									<div class="card">
										<div class="card-header">
											<h5>Add Admin Users</h5>
											<div class="card-header-right">
												<i class="icofont icofont-rounded-down"></i>
												<i class="icofont icofont-refresh"></i>
												<i class="icofont icofont-close-circled"></i>
											</div>
										</div>
										<div class="card-block">
											<form role="form" method="POST" action="{{route('admin.user.create')}}">
												{{csrf_field()}}
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
													<label class="col-sm-2 col-form-label">Email</label>
													<div class="col-sm-10">
														<input name="email" type="text" class="form-control" value="{{ old('email') }}">
														@if ($errors->has('email'))
														<span style="color:red;">{{$errors->first('email')}}</span>
														@endif
													</div>
												</div>

												<div class="form-group row">
													<label class="col-sm-2 col-form-label">Username</label>
													<div class="col-sm-10">
														<input name="username" type="text" class="form-control" value="{{ old('username') }}">
														@if ($errors->has('username'))
														<span style="color:red;">{{$errors->first('username')}}</span>
														@endif
													</div>
												</div>

												<div class="form-group row">
													<label class="col-sm-2 col-form-label">Password</label>
													<div class="col-sm-10">
														<input name="password" type="password" class="form-control" >
														@if ($errors->has('password'))
														<span style="color:red;">{{$errors->first('password')}}</span>
														@endif
													</div>
												</div>

												<div class="form-group row">
													<label class="col-sm-2 col-form-label">Phone</label>
													<div class="col-sm-10">
														<input name="phone" type="phone" class="form-control" value="{{ old('phone') }}">
														@if ($errors->has('phone'))
														<span style="color:red;">{{$errors->first('phone')}}</span>
														@endif
													</div>
												</div>

												<div class="form-group row">
													<label class="col-sm-2 col-form-label">Role</label>
													<div class="col-sm-10">
														<select class="js-example-basic-single col-sm-12" name="roles" id="roles">
															@foreach($roles as $role)
															<option value="{{ $role }}">{{ $role }}</option>
															@endforeach
														</select>
														@if ($errors->has('roles'))
														<span style="color:red;">{{$errors->first('roles')}}</span>
														@endif
													</div>
												</div>

												<div class="form-group row">
													<label class="col-sm-2 col-form-label">Permissions</label>
													<div class="col-sm-10">
														{!! Form::select('permissions[]', $permissions, null,array('class' => 'js-example-placeholder-multiple form-control col-sm-12','id'=>'permissions','multiple','placeholder'=>'select Permissions')) !!}
														@if ($errors->has('permissions'))
														<span style="color:red;">{{$errors->first('permissions')}}</span>
														@endif
													</div>
												</div>

												<div class="row">
													<label class="col-sm-2"></label>
													<div class="col-sm-10">
														<button type="submit" class="btn btn-primary m-b-0">Submit</button>
														<a href="{{ route('admin.users') }}" class="btn btn-primary m-b-0">Back</a>
													</div>
												</div>
											</form>
										</div>
									</div>

								</div>
							</div>
						</div>
						<!-- Page body end -->
					</div>
				</div>
				<!-- Main-body end -->
				<div id="styleSelector">

				</div>
			</div>
		</div>
	</div>
</div>

@endsection

@push('scripts')
<script>
	$(document).ready(function() {
		CKEDITOR.replace( 'address' );
	});
</script>
@endpush
