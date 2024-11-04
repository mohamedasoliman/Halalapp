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
                <h2>Edit Account Information</h2>

                <form action="{{ route('account-update') }}" method="POST" id="profile_update" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-sm-11">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>First Name:</label>
                                        <input type="text" class="form-control" name="first_name" value="{{ Auth::user()->first_name }}" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Last Name:</label>
                                        <input type="text" class="form-control" name="last_name" value="{{ Auth::user()->last_name }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>E-Mail Address:</label>
                                        <input type="email" class="form-control" name="email" value="{{ Auth::user()->email }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Mobile Number:</label>
                                        <input id="mobile_no" type="number" class="form-control " name="mobile_no" value="{{ Auth::user()->mobile_no }}" autocomplete="mobile_no">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Gender:</label>
                                        <label class="radio-label"><input type="radio" name="gender" value="1" {{ Auth::user()->gender == 1 ? 'checked' : '' }}>Male</label>
                                        <label class="radio-label"><input type="radio" name="gender" value="2" {{ Auth::user()->gender == 2 ? 'checked' : '' }}>Female</label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Profile Image:</label>
                                        <input type="file" class="form-control " name="avatar_location" id="fileUploader">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <img style="height: 100px; width: 100px;" id="display_image" class="img-thumbnail rounded-circle" src="{{ Auth::user()->avatar_location ? asset('assets/frontend/profiles/'.Auth::user()->avatar_location) : asset('assets/images/avatar-1.png') }}">
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
            $('#profile_update').validate({
                rules: {
                    first_name: {
                        required: true,
                    },
                    last_name: {
                        required: true,
                    },
                    mobile_no: {
                        required: true,
                        minlength: 10,
                        maxlength: 10,
                        digits: true,
                    },
                    email: {
                        required: true,
                        email: true,
                    },
                    avatar_location: {
                        // required: true,
                        extension: "jpeg|png|jpg"
                    },
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
                    first_name: {
                        required: 'Please First Name',
                    },
                    last_name: {
                        required: 'Please Last Name',
                    },
                    mobile_no: {
                        required: 'Please Enter Your Mobile No',
                        minlength: 'Please Enter 10 Digit Mobile No',
                    },
                    email: {
                        required: 'Please Enter Your Email',
                    },
                    password: {
                        required: 'Please Enter Your Password',
                        minlength: 'Password Length must be 8 Character',
                    },
                    confirmpassword: {
                        required: 'Please Confirm Your Password',
                    },
                    /*avatar_type: {
                        required: 'Please select image',
                    }*/
                },
                submitHandler: function (form) {
                    $('#loading').show();
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: "{{ route('account-update') }}",
                        type: "POST",
                        data: new FormData(form),
                        dataType: 'json',
                        contentType: false,
                        cache: false,
                        processData: false,
                        success: function (data) {
                            if (data.status) {
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

        $("#fileUploader").change(function () {
            $("#display_image").css("display", "block");
            readIMG(this);
        });

        function readIMG(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#display_image').attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

@endpush
