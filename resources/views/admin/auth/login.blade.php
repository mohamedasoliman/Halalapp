@extends('admin.layouts.loginapp')

@section('content')

<section class="login p-fixed d-flex text-center bg-primary common-img-bg">
    <!-- Container-fluid starts -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Authentication card start -->
                <div class="login-card card-block auth-body">
                    <form class="md-float-material" id="login-form" method="POST" action="{{ route('admin.login.submit') }}">
                        @csrf
                        <div class="text-center">
                            <img src="{{asset('assets/images/logo.png')}}" alt="logo.png">
                        </div>
                        <div class="auth-box">
                            <div class="row m-b-20">
                                <div class="col-md-12">
                                    <h3 class="text-center txt-primary">Sign In</h3>
                                </div>
                            </div>
                            <hr/>
                            @include('admin.messages')
                            <div class="form-group row has-error">
                                <div class="col-sm-12">
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" placeholder="Your Email Address" value="{{ old('email') }}" autocomplete="email">
                                </div>
                                <span class="md-line"></span>
                            </div>
                            @error('email')
                            <strong style="color: red;">{{ $message }}</strong>
                            @enderror
                            <div class="form-group row has-error">
                                <div class="col-sm-12">
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="Password" name="password" autocomplete="current-password">
                                </div>
                                <span class="md-line"></span>
                            </div>
                            @error('password')
                            <strong style="color: red;">{{ $message }}</strong>
                            @enderror
                            <div class="row m-t-25 text-left">
                                <div class="col-sm-6 col-xs-12">
                                    <div class="checkbox-fade fade-in-primary">
                                        <label>
                                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                            <span class="cr"><i class="cr-icon icofont icofont-ui-check txt-primary"></i></span>
                                            <span class="text-inverse">Remember me</span>
                                        </label>
                                    </div>
                                </div>
                                <!-- <div class="col-sm-6 col-xs-12 forgot-phone text-right">
                                    @if (Route::has('password.request'))
                                    <a class="text-right f-w-600 text-inverse" href="{{ route('admin.forgot.password.email') }}">
                                        {{ __('Forgot Your Password?') }}
                                    </a>
                                    @endif
                                </div> -->
                            </div>
                            <div class="row m-t-30">
                                <div class="col-md-12">
                                    <input type="submit" id="login-id" class="btn btn-primary btn-md btn-block waves-effect text-center m-b-20" value="Login">
                                </div>
                            </div>
                            <hr/>
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

@endsection
