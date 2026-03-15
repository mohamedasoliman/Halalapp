@extends('admin.layouts.loginapp')

@section('content')

<div class="auth-wrapper">
    <div class="auth-content">
        <div class="text-center mb-4">
            <img src="{{asset('assets/images/logo.png')}}" alt="Halal Kiwi" style="max-width: 220px; height: auto;">
        </div>

        <h4 class="text-center mb-4">Admin Panel</h4>

        <form id="login-form" method="POST" action="{{ route('admin.login.submit') }}">
            @csrf

            @include('admin.messages')

            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                       name="email" placeholder="Enter your email"
                       value="{{ old('email') }}" autocomplete="email" required>
                @error('email')
                <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                       placeholder="Enter your password" name="password"
                       autocomplete="current-password" required>
                @error('password')
                <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <div class="d-flex align-items-center">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                           {{ old('remember') ? 'checked' : '' }} style="margin-right: 8px;">
                    <label class="form-check-label mb-0" for="remember">Remember me</label>
                </div>
            </div>

            <div class="form-group mt-4">
                <button type="submit" id="login-id" class="btn btn-primary btn-block">
                    Sign In
                </button>
            </div>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">HalalKiwi Admin Panel</small>
        </div>
    </div>
</div>

<style>
    body {
        margin: 0;
        padding: 0;
    }

    .auth-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #004644 0%, #006663 100%);
        padding: 20px;
    }

    .auth-content {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        padding: 40px;
        width: 100%;
        max-width: 400px;
    }

    .auth-content h4 {
        color: #1e293b;
        font-weight: 700;
        font-size: 24px;
    }

    .auth-content .form-group {
        margin-bottom: 20px;
    }

    .auth-content label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 8px;
        display: block;
        font-size: 14px;
    }

    .auth-content .form-control {
        padding: 12px 16px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .auth-content .form-control:focus {
        border-color: #004644;
        box-shadow: 0 0 0 3px rgba(0, 70, 68, 0.1);
        outline: none;
    }

    .auth-content .btn-primary {
        background: #004644;
        border: none;
        padding: 14px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        width: 100%;
        transition: all 0.2s ease;
    }

    .auth-content .btn-primary:hover {
        background: #006663;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 70, 68, 0.4);
    }

    .text-danger {
        color: #ef4444;
        font-size: 12px;
        margin-top: 4px;
        display: block;
    }

    .form-check-input {
        width: 16px;
        height: 16px;
        accent-color: #004644;
    }

    .form-check-label {
        font-size: 14px;
        color: #6b7280;
    }
</style>

@endsection
