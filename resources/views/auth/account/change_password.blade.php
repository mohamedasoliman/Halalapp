@extends('user.layouts.app')
@section('title', 'Profile')

@section('content')

    <nav aria-label="breadcrumb" class="breadcrumb-nav">
        <div class="container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('user.home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">My Account</li>
            </ol>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-lg-9 order-lg-last dashboard-content">
                <h2>Change Password</h2>

                <form action="{{ route('user-password-set') }}" method="POST" id="change_password" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-sm-11">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Password</label>
                                        <input type="password" class="form-control" id="password" name="password">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Confirm Password</label>
                                        <input type="password" class="form-control" name="confirmpassword">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2"></div>

                    <div class="form-footer">
                        <div class="form-footer-right">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
            @include('auth.account.sidebar')
        </div>
    </div>
@stop

@push('scripts')

    <script>
        $(document).ready(function () {
            $('#change_password').validate({
                rules: {
                    password: {
                        required: true,
                        minlength: 8,
                    },
                    confirmpassword: {
                        required: true,
                        equalTo: "#password",
                    },
                },
                errorPlacement: function (error, element) {
                    if (element.is(":radio")) {
                        var name = element.attr('name');
                        error.insertAfter("#" + name + "_radio-error");
                    } else {
                        error.appendTo(element.parent());
                    }
                },
                messages: {
                    password: {
                        required: 'Please Enter Your Password',
                        minlength: 'Password Length must be 8 Character',
                    },
                    confirmpassword: {
                        required: 'Please Confirm Your Password',
                    },
                },
                submitHandler: function (form) {
                    $('#loading').show();
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: "{{ route('user-password-set') }}",
                        type: "POST",
                        data: new FormData(form),
                        dataType: 'json',
                        contentType: false,
                        cache: false,
                        processData: false,
                        success: function (data) {
                            if (data.status) {
                                $("#change_password").trigger('reset');
                                message(data.message, 'success');
                            }
                        },
                        error: function (data) {
                            $('#loading').hide();
                            var errorString = '';
                            $.each(data.responseJSON.errors, function (key, value) {
                                errorString += value + '<br>';
                            });
                            message(errorString, 'danger');
                        },
                    });
                    return false;
                }
            });
        });
    </script>

@endpush
