<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ config('app.name', 'Login') }}</title>

    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Phoenixcoded">
    <meta name="keywords" content="">
    <meta name="author" content="">
    <!-- Favicon icon -->

    <link rel="icon" href="{{asset('assets/images/favicon.png')}}" type="image/x-icon">
    <!-- Google font-->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet">
    <!-- Required Fremwork -->
    <link rel="stylesheet" type="text/css" href="{{asset('assets/bower_components/bootstrap/dist/css/bootstrap.min.css')}}">
    <!-- themify-icons line icon -->
    <link rel="stylesheet" type="text/css" href="{{asset('assets/icon/themify-icons/themify-icons.css')}}">
    <!-- ico font -->
    <link rel="stylesheet" type="text/css" href="{{asset('assets/icon/icofont/css/icofont.css')}}">
    <!-- Style.css -->
    <link rel="stylesheet" type="text/css" href="{{asset('assets/css/custom.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/css/style.css')}}">
    <!-- color .css -->
    <link rel="stylesheet" type="text/css" href="{{asset('assets/css/color/color-1.css')}}" id="color"/>
    <script type="text/javascript" src="{{asset('assets/bower_components/jquery/dist/jquery.min.js')}}"></script>
    <!-- Validation js -->
    <script src="{{asset('assets/js/jquery-validate/jquery.validate.min.js')}}"></script>
    <script src="{{asset('assets/js/jquery-validate/additional-methods.min.js')}}"></script>
</head>
<body class="fix-menu">

    @yield('content')

    @yield('before-scripts')

    <script type="text/javascript" src="{{asset('assets/bower_components/jquery-ui/jquery-ui.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('assets/bower_components/tether/dist/js/tether.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('assets/bower_components/bootstrap/dist/js/bootstrap.min.js')}}"></script>
    <!-- jquery slimscroll js -->
    <script type="text/javascript" src="{{asset('assets/bower_components/jquery-slimscroll/jquery.slimscroll.js')}}"></script>
    <!-- Validation js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.14.0/jquery.validate.min.js"></script>
    <!-- modernizr js -->
    <script type="text/javascript" src="{{asset('assets/bower_components/modernizr/modernizr.js')}}"></script>
    <script type="text/javascript" src="{{asset('assets/bower_components/modernizr/feature-detects/css-scrollbars.js')}}"></script>
    <!-- i18next.min.js -->
    <script type="text/javascript" src="{{asset('assets/bower_components/i18next/i18next.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('assets/bower_components/i18next-xhr-backend/i18nextXHRBackend.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('assets/bower_components/i18next-browser-languagedetector/i18nextBrowserLanguageDetector.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('assets/bower_components/jquery-i18next/jquery-i18next.min.js')}}"></script>
    <!-- Custom js -->
    <script type="text/javascript" src="{{asset('assets/js/script.js')}}"></script>
    <!---- color js --->
{{--    <script type="text/javascript" src="{{asset('assets/js/common-pages.js')}}"></script>--}}

    <script>
    $('#login-id').click(function() {
        $('#login-form').validate({
            rules: {
                email: {
                    required: true,
                },
                password: {
                    required: true,
                },
            },
            errorPlacement: function(error, element) {
                if (element.is(":radio")) {
                    error.insertAfter("#radio-error");
                }
                else if(element.is(":checkbox"))
                {
                    error.insertAfter("#checkbox-error");
                }
                else {
                    error.appendTo(element.parent());
                }
            },
            messages:{
                email:{
                    required: 'Please Enter Your Email',
                },
                password:{
                    required: 'Please Enter Your Password',
                },
            },
        });
    });
</script>

    @yield('after-scripts')

</body>
</html>
