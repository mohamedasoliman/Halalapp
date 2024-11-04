{{-- @extends('admin.layouts.app-login')

@section('content')
<div class="middle-box text-center  animated fadeInDown">
	<div class="top-content">
		<h1 class="logo-name"><a href="{{ url('/') }}"><<img src="{{asset('assets/frontend/logo/logo.jpg')}}" ></a></h1>
		<h2>Admin Reset Password</h2>
		@if ($message = Session::get('status'))
		<div class="alert alert-success">
			<button type="button" class="close" data-dismiss="alert">×</button>
			<strong>{{ $message }}</strong>
		</div>
		@endif
		<form method="POST" action="{{ route('admin.password.request') }}">
			@csrf

			<input type="hidden" name="token" value="{{ $token }}">

			<div class="form-group">
				<label for="email" class="">{{ __('Enter Your Email') }}</label>

				<div class="form-item">
					<input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ $email ?? old('email') }}" required autofocus placeholder="Enter Your Email">

					@if ($errors->has('email'))
					<span class="invalid-feedback" role="alert">
						<strong>{{ $errors->first('email') }}</strong>
					</span>
					@endif
				</div>
			</div>

			<div class="form-group">
				<label for="password" class="">{{ __('Enter Your Password') }}</label>

				<div class="form-item">
					<input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" placeholder="Enter Your Password" required >

					@if ($errors->has('password'))
					<span class="invalid-feedback" role="alert">
						<strong>{{ $errors->first('password') }}</strong>
					</span>
					@endif
				</div>
			</div>

			<div class="form-group">
				<label for="password-confirm" class="">{{ __('Confirm Your Password') }}</label>

				<div class="form-item">
					<input id="password-confirm" type="password" class="form-control" name="password_confirmation" placeholder="Confirm Your Password" required>
				</div>
			</div>

			<div class="form-group">
				<div class="form-item">
					<button type="submit" class="btn btn-primary">
						{{ __('Reset Password') }}
					</button>
				</div>
			</div>
		</form>
	</div>
</div>
@endsection


--}}
@extends('admin.layouts.loginapp')

@section('content')
<section class="login p-fixed d-flex text-center bg-primary common-img-bg">
	<!-- Container-fluid starts -->
	<div class="container-fluid">
		<div class="row">
			<div class="col-sm-12">
				<!-- Authentication card start -->
				<div class="login-card card-block auth-body">
					<form class="md-float-material">
						<div class="text-center">
							<img src="{{asset('assets/images/logo.png')}}" alt="logo.png">
						</div>
						<div class="auth-box">
							<div class="row m-b-20">
								<div class="col-md-12">
									<h3 class="text-left">Reset Your Password</h3>
								</div>
							</div>
							@if ($message = Session::get('status'))
							<div class="alert alert-success">
								<button type="button" class="close" data-dismiss="alert">×</button>
								<strong>{{ $message }}</strong>
							</div>
							@endif
							@if ($message = Session::get('success'))
							<div class="alert alert-success">
								<button type="button" class="close" data-dismiss="alert">×</button>
								<strong>{{ $message }}</strong>
							</div>
							@endif
                            @if ($message = Session::get('error'))
							<div class="alert alert-danger">
								<button type="button" class="close" data-dismiss="alert">×</button>
								<strong>{{ $message }}</strong>
							</div>
							@endif
							<p class="text-inverse b-b-default text-right">Back to <a href="{{ route('admin.login') }}">Login.</a></p>

							<form method="post" action="{{ route('admin.password.emails') }}">

							</form>

							<form class="m-t" role="form" id="reset_pass" method="POST" action="{{ route('admin.password.request') }}">
								@csrf
								<input type="hidden" name="token" value="{{ $token }}">

								<div class="form-group row has-error">
									<div class="col-sm-12">
										<input type="email" class="form-control" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="Your Email Address">
									</div>
									<span class="md-line"></span>
								</div>
								@error('email')
								<strong style="color: red;">{{ $message }}</strong>
								@enderror

								<div class="form-group row has-error">
									<div class="col-sm-12">
										<input type="password" class="form-control" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" id="password" value="{{ old('password') }}" placeholder="Your Password" required="">
									</div>
									<span class="md-line"></span>
								</div>
								@error('password')
								<strong style="color: red;">{{ $message }}</strong>
								@enderror

								<div class="form-group row has-error">
									<div class="col-sm-12">
										<input type="password" class="form-control" class="form-control{{ $errors->has('password_confirmation') ? ' is-invalid' : '' }}" name="password_confirmation" id="password_confirmation" value="{{ old('password_confirmation') }}" placeholder="Confirm Password" required="">
									</div>
									<span class="md-line"></span>
								</div>
								@error('password_confirmation')
								<strong style="color: red;">{{ $message }}</strong>
								@enderror

								<div class="row">
									<div class="col-md-12">
										<input type="submit" id="update_pass" class="btn btn-primary btn-md btn-block" value="
										Update">
									</div>
								</div>
								<hr/>
							</form>

							<div class="row">
								<div class="col-md-12">
									<p class="text-inverse text-left m-b-0">Thank you and enjoy our website.</p>
									<p class="text-inverse text-left"><b>Your Autentification Team</b></p>
								</div>
							</div>

						</div>
					</form>
					<!-- end of form -->
				</div>
				<!-- Authentication card end -->
			</div>
			<!-- end of col-sm-12 -->
		</div>
		<!-- end of row -->
	</div>
	<!-- end of container-fluid -->
</section>

<script>
    $('#update_pass').click(function () {
        $('#reset_pass').validate({
            rules: {
                email: {
                    required: true,
                },
                password: {
                  required: true,
                  minlength: 8
                },
                password_confirmation: {
                  equalTo: "#password"
                }
            },
            errorPlacement: function (error, element) {
                if (element.is(":radio")) {
                    error.insertAfter("#radio-error");
                } else if (element.is(":checkbox")) {
                    error.insertAfter("#checkbox-error");
                } else {
                    error.appendTo(element.parent());
                }
            },
            messages: {
                email: {
                    required: 'Please Enter Your Email',
                },
                password: {
                    required: 'Please Enter Your Password',
                },
            },
        });
    });
</script>
@endsection
