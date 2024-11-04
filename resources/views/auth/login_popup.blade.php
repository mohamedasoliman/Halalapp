<!-- Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="loginModalTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="exampleModalLongTitle">Login</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="login-form">
                    @csrf
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required="">
                    <input type="password" name="password" class="form-control" placeholder="Password" required="">

                    <div class="form-footer">
                        <div class="row">
                            <div class="col-md-12 col-sm-12 col-lg-8">
                                <button type="submit" class="btn btn-primary">LOGIN</button>
                                <a href="{{ route('password.request') }}" class="forget-pass"> Forgot your password?</a>
                            </div>
                            <div class="col-md-12 col-sm-12 col-lg-2">
                                <h2 style="margin-top: 2.4rem;">OR</h2>
                            </div>
                            <div class="col-md-12 col-sm-12 col-lg-2">
                                <button type="button" data-toggle="modal" data-target="#registerModal" class="btn btn-primary reg_class">Register</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $('.reg_class').click(function () {
        $("#loginModal").modal('hide');
    })

    $('#login-form').validate({
        rules: {
            email: {
                required: true,
            },
            password: {
                required: true,
            },
        },
        messages: {
            email: {
                required: 'Please enter email',
            },
            password: {
                required: 'Please enter password',
            },
        },
        submitHandler: function (form) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('user.login')}}",
                type: "POST",
                data: new FormData(form),
                dataType: 'json',
                contentType: false,
                cache: false,
                processData: false,
                beforeSend: function() {
                    $("body").append("<div class='ajax-overlay'><i class='porto-loading-icon'></i></div>");
                },
                success: function (res) {
                    $(".ajax-overlay").remove();
                    if(res.status) {
                        message(res.message, 'success');
                        $("#loginModal").modal('hide');
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
