<!-- Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" role="dialog" aria-labelledby="loginModalTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="exampleModalLongTitle">Registration</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="reg-form">
                    @csrf
                    <div class="form-group row">
                        <label for="First Name" class="col-md-2 col-form-label">{{ __('First Name') }}</label>
                        <div class="col-md-4">
                            <input id="first_name" type="text" class="form-control @error('first_name') is-invalid @enderror" name="first_name"
                                   value="{{ old('first_name') }}" autocomplete="first_name" autofocus>

                            @error('first_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>

                        <label for="Last Name" class="col-md-2 col-form-label">{{ __('Last Name') }}</label>
                        <div class="col-md-4">
                            <input id="last_name" type="text" class="form-control @error('last_name') is-invalid @enderror" name="last_name"
                                   value="{{ old('last_name') }}" autocomplete="last_name" autofocus>

                            @error('last_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="Email" class="col-md-2 col-form-label">{{ __('E-Mail Address') }}</label>
                        <div class="col-md-4">
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"name="email" value="{{ old('email') }}" autocomplete="email">

                            @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>

                        <label for="Mobile No" class="col-md-2 col-form-label">{{ __('Mobile Number') }}</label>
                        <div class="col-md-4">
                            <input id="mobile_no" type="number" class="form-control @error('mobile_no') is-invalid @enderror" name="mobile_no"
                                   value="{{ old('mobile_no') }}" autocomplete="mobile_no">

                            @error('mobile_no')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="Gender" class="col-md-2 col-form-label">{{ __('Gender') }}:</label>
                        <div class="col-md-10">
                            <label class="radio-label">
                                <input type="radio" name="gender" value="1" checked>
                                {{ __('Male') }}
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="gender" value="2">
                                {{ __('Female') }}
                            </label>
                            @error('gender')
                            <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="password" class="col-md-2 col-form-label">{{ __('Password') }}</label>
                        <div class="col-md-4">
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password"
                                   autocomplete="new-password">

                            @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                        </div>

                        <label for="password-confirm" class="col-md-2 col-form-label">{{ __('Confirm Password') }}</label>
                        <div class="col-md-4">
                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $('#reg-form').validate({
        rules: {
            first_name: {
                required: true,
            },
            last_name: {
                required: true,
            },
            mobile_no: {
                required: true,
                minlength:10,
                digits: true,
            },
            email: {
                required: true,
                email:true,
            },
            password: {
                required: true,
                minlength: 8,
            },
            password_confirmation: {
                required: true,
                equalTo: "#password",
            },
        },
        messages: {
            first_name: {
                required: 'Please enter first name',
            },
            last_name: {
                required: 'Please enter last name',
            },
            mobile_no: {
                required: 'Please Enter Your Mobile No',
                minlength: 'Please Enter 10 Digit Mobile No',
            },
            email: {
                required: 'Please enter email',
            },
            password:{
                required: 'Please Enter Your Password',
                minlength: 'Password Length must be 8 Character',
            },
            password_confirmation:{
                required: 'Please Confirm Your Password',
            },
        },
        submitHandler: function (form) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('user.register')}}",
                type: "POST",
                data: new FormData(form),
                dataType: 'json',
                contentType: false,
                cache: false,
                processData: false,
                beforeSend: function () {
                    $("body").append("<div class='ajax-overlay'><i class='porto-loading-icon'></i></div>");
                },
                success: function (res) {
                    $(".ajax-overlay").remove();
                    if(res.status) {
                        message(res.message, 'success');
                        $("#registerModal").modal('hide');
                        setTimeout(function(){ location.reload(); }, 4000);
                    } else{
                        message(res.message, 'danger');
                    }
                },
                error: function (data) {
                    $(".ajax-overlay").remove();
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
</script>
