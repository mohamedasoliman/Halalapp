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
                                    <h3 class="text-left">You forgot your Password? </h3>
                                    <h3 class="text-left">Don't worry.</h3>
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
                            <p class="text-inverse b-b-default text-right">Back to <a href="{{ route('admin.login') }}">Login.</a></p>

                            <form method="post" action="{{ route('admin.password.emails') }}">

                            </form>

                            <form class="m-t" role="form" id="loginform" method="POST" action="{{ route('admin.password.emails') }}">
                                @csrf

                                <div class="form-group row has-error">
                                    <div class="col-sm-12">
                                        <input type="email" class="form-control" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="Your Email Address" required="">
                                    </div>
                                    <span class="md-line"></span>
                                </div>
                                @error('email')
                                <strong style="color: red;">{{ $message }}</strong>
                                @enderror

                                <div class="row">
                                    <div class="col-md-12">
                                        <input type="submit" class="btn btn-primary btn-md btn-block" value="
                                        Send Password Reset Link">
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

@endsection
