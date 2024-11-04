@extends('backend.layouts.app')
@section('content')
@include('backend.include.sidebar')
<div id="page-wrapper" class="gray-bg">
	@include('backend.include.header')
	<div class="row wrapper border-bottom white-bg page-heading">
		<div class="col-lg-10">
			<h2>Change Password</h2>
		</div>
	</div>
	<div class="wrapper wrapper-content animated fadeInRight">
		<div class="row">
			<div class="col-lg-12">
				<div class="ibox ">
					<div class="ibox-content">
						<form action="{{route('admin.user.changepassword')}}" method="post" role="form" autocomplete="off">
							{{csrf_field()}}
							<input type="hidden" name="id" value="{{ $admin->id }}">
							
							<div class="form-group">
								<div class="row">
									<label class="col-md-12 "><strong style="text-transform: uppercase;">New Password</strong></label>
									<div class="col-md-12">
										<input class="form-control input-lg" name="password"  type="password" placeholder="New Password" autocomplete="new-password">
										@if ($errors->has('password'))
										<p style="color:red;">{{$errors->first('password')}}</p>
										@endif
									</div>
								</div>
							</div>

							<div class="form-group">
								<div class="row">
									<label class="col-md-12 "><strong style="text-transform: uppercase;">Confirm Password</strong></label>
									<div class="col-md-12">
										<input class="form-control input-lg" name="confirmpassword"  type="password" placeholder="Confirm Password" autocomplete="new-password">
										@if ($errors->has('confirmpassword'))
										<p style="color:red;">{{$errors->first('confirmpassword')}}</p>
										@endif
									</div>
								</div>
							</div>
							
							<div class="row">
								<div class="col-md-12">
									<button type="submit" class="btn btn-info">Update</button>
								</div>
							</div>

						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	@include('backend.include.footer')
</div>
@endsection
@push('scripts')
<script>
	$("#roles").select2({
		width: '100%',
	});
	$("#permissions").select2({
		width: '100%',
	});
</script>
@endpush