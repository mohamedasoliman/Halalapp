<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <title>{{ config('app.name', 'Dynamic Admin') }}</title>
  <!-- Meta -->
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
  <meta name="description" content="">
  <meta name="keywords" content="">
  <!-- CSRF Token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <meta name="author" content="">
  <!-- Favicon icon -->
  <link rel="icon" href="{{asset('assets/images/favicon.png')}}" type="image/x-icon">
  <!-- Google font-->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet">
  <!-- Required Fremwork -->

  <link rel="stylesheet" type="text/css" href="https://halalapp.info/assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <!-- themify icon -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/icon/themify-icons/themify-icons.css')}}">
  <!-- ico font -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/icon/icofont/css/icofont.css')}}">
  <!-- flag icon framework css -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/pages/flag-icon/flag-icon.min.css')}}">
  <!-- Menu-Search css -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/pages/menu-search/css/component.css')}}">
  <!-- Horizontal-Timeline css -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/pages/dashboard/horizontal-timeline/css/style.css')}}">
  <!-- amchart css -->
  {{-- <link rel="stylesheet" type="text/css" href="{{asset('assets/pages/dashboard/amchart/css/amchart.css')}}"> --}}
  <!-- flag icon framework css -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/pages/flag-icon/flag-icon.min.css')}}">
  <!-- Select 2 css -->
  <link rel="stylesheet" href="{{asset('assets/bower_components/select2/dist/css/select2.min.css')}}" />
  <!-- Multi Select css -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/bower_components/bootstrap-multiselect/dist/css/bootstrap-multiselect.css')}}" />
  <link rel="stylesheet" type="text/css" href="{{asset('assets/bower_components/multiselect/css/multi-select.css')}}" />
  <!-- Data Table Css -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/bower_components/datatables.net-bs4/css/dataTables.bootstrap4.min.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/pages/data-table/css/buttons.dataTables.min.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/bower_components/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/pages/data-table/extensions/buttons/css/buttons.dataTables.min.css')}}">
  <!-- Style.css -->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/custom.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/style.css')}}">
  <!--color css-->
  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/color/color-1.css')}}" id="color" />
  <link rel="stylesheet" type="text/css" href="{{asset('assets/bower_components/sweetalert/sweetalert.css')}}" id="color" />

  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/linearicons.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/simple-line-icons.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/ionicons.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/jquery.mCustomScrollbar.css')}}">
  <link rel="stylesheet" type="text/css" href="{{asset('assets/css/general.css')}}">
    <link rel="stylesheet" href="{{asset('assets/bower_components/jquery-ui/jquery-ui.min.css')}}"/>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

  <script src="https://cdn.ckeditor.com/4.15.0/standard/ckeditor.js"></script>

  @yield('after-styles')

  @stack('styles')
</head>
<body>
  <!-- Pre-loader start -->
  <div class="theme-loader">
    <div class="ball-scale">
      <div></div>
    </div>
  </div>
  <!-- Pre-loader end -->
  @include('admin.include.menuheader')

  @yield('before-scripts')

  <script type="text/javascript" src="{{asset('assets/bower_components/jquery/dist/jquery.min.js')}}"></script>
  <script src="{{asset('assets/bower_components/jquery-ui/jquery-ui.min.js')}}"></script>
  <script type="text/javascript" src="{{asset('assets/bower_components/tether/dist/js/tether.min.js')}}"></script>
  <script type="text/javascript" src="{{asset('assets/bower_components/bootstrap/dist/js/bootstrap.min.js')}}"></script>
  <!-- jquery slimscroll js -->
  <script type="text/javascript" src="{{asset('assets/bower_components/jquery-slimscroll/jquery.slimscroll.js')}}"></script>
  <!-- modernizr js -->
  <script type="text/javascript" src="{{asset('assets/bower_components/modernizr/modernizr.js')}}"></script>
  <script type="text/javascript" src="{{asset('assets/bower_components/modernizr/feature-detects/css-scrollbars.js')}}"></script>

  <!-- Validation js -->
  <script src="{{asset('assets/js/jquery-validate/jquery.validate.min.js')}}"></script>
  <script src="{{asset('assets/js/jquery-validate/additional-methods.min.js')}}"></script>
  <!-- classie js -->
  <script type="text/javascript" src="{{asset('assets/bower_components/classie/classie.js')}}"></script>
  <!-- data-table js -->

  <!-- data-table js -->
  <script src="{{asset('assets/bower_components/datatables.net/js/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('assets/bower_components/datatables.net-buttons/js/dataTables.buttons.min.js')}}"></script>
  {{-- <script src="{{asset('assets/pages/data-table/js/jszip.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/js/pdfmake.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/js/vfs_fonts.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/extensions/buttons/js/dataTables.buttons.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/extensions/buttons/js/buttons.flash.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/extensions/buttons/js/jszip.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/extensions/buttons/js/pdfmake.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/extensions/buttons/js/vfs_fonts.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/extensions/buttons/js/buttons.colVis.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/data-table/extensions/buttons/js/pdfmake.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/bower_components/datatables.net-buttons/js/buttons.print.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/bower_components/datatables.net-buttons/js/buttons.html5.min.js')}}"></script> --}}
  <script src="{{asset('assets/bower_components/datatables.net-bs4/js/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('assets/bower_components/datatables.net-responsive/js/dataTables.responsive.min.js')}}"></script>
  <script src="{{asset('assets/bower_components/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js')}}"></script>

  {{-- <script src="{{asset('assets/bower_components/datatables.net/js/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('assets/bower_components/datatables.net-bs4/js/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('assets/bower_components/datatables.net-responsive/js/dataTables.responsive.min.js')}}"></script>
  <script src="{{asset('assets/bower_components/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js')}}"></script> --}}
  <!-- Rickshow Chart js -->
  {{-- <script src="{{asset('assets/bower_components/d3/d3.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/bower_components/rickshaw/rickshaw.js')}}"></script> --}}
  <!-- Morris Chart js -->
  {{-- <script src="{{asset('assets/bower_components/raphael/raphael.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/bower_components/morris.js/morris.js')}}"></script> --}}
  <!-- Horizontal-Timeline js -->
  <script type="text/javascript" src="{{asset('assets/pages/dashboard/horizontal-timeline/js/main.js')}}"></script>
  <!-- amchart js -->
  {{-- <script type="text/javascript" src="{{asset('assets/pages/dashboard/amchart/js/amcharts.js')}}"></script> --}}
  {{-- <script type="text/javascript" src="{{asset('assets/pages/dashboard/amchart/js/serial.js')}}"></script> --}}
  {{-- <script type="text/javascript" src="{{asset('assets/pages/dashboard/amchart/js/light.js')}}"></script> --}}
  {{-- <script type="text/javascript" src="{{asset('assets/pages/dashboard/amchart/js/custom-amchart.js')}}"></script> --}}
  <!-- i18next.min.js -->
  {{-- <script type="text/javascript" src="{{asset('assets/bower_components/i18next/i18next.min.js')}}"></script> --}}
  {{-- <script type="text/javascript" src="{{asset('assets/bower_components/i18next-xhr-backend/i18nextXHRBackend.min.js')}}"></script> --}}
  {{-- <script type="text/javascript" src="{{asset('assets/bower_components/i18next-browser-languagedetector/i18nextBrowserLanguageDetector.min.js')}}"></script> --}}
  {{-- <script type="text/javascript" src="{{asset('assets/bower_components/jquery-i18next/jquery-i18next.min.js')}}"></script> --}}
  {{-- <script src="{{asset('assets/pages/chart/echarts/js/echarts-all.js')}}" type="text/javascript"></script> --}}
  <!-- Select 2 js -->
  <script type="text/javascript" src="{{asset('assets/bower_components/select2/dist/js/select2.full.min.js')}}"></script>
  <!-- Multiselect js -->
  <script type="text/javascript" src="{{asset('assets/bower_components/bootstrap-multiselect/dist/js/bootstrap-multiselect.js')}}">
  </script>
  <script type="text/javascript" src="{{asset('assets/bower_components/multiselect/js/jquery.multi-select.js')}}"></script>
  <!-- Custom js -->
  <script src="{{asset('assets/pages/data-table/extensions/buttons/js/extension-btns-custom.js')}}"></script>
  <script type="text/javascript" src="{{asset('assets/bower_components/sweetalert/sweetalert.min.js')}}"></script>
  <script type="text/javascript" src="{{asset('assets/pages/dashboard/custom-dashboard.js')}}"></script>
  <script type="text/javascript" src="{{asset('assets/js/script.js')}}"></script>
  <script type="text/javascript" src="{{asset('assets/pages/advance-elements/select2-custom.js')}}"></script>
  <!-- pcmenu js -->
  <script src="{{asset('assets/js/pcoded.min.js')}}"></script>
  <script src="{{asset('assets/js/demo-12.js')}}"></script>
  <script src="{{asset('assets/js/custom.js')}}"></script>
  <script src="{{asset('assets/js/jquery.mCustomScrollbar.concat.min.js')}}"></script>
  <script src="{{asset('assets/js/jquery.mousewheel.min.js')}}"></script>
  <script src="{{asset('assets/js/bootstrap-notify.min.js')}}"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

  <script src="{{asset('assets/js/general.js')}}"></script>
  <script>
      var base_url = '{{ env('ADMIN_APP_URL') }}';
      var user_base_url = '{{ env('APP_URL') }}';
  </script>
  @yield('after-scripts')
  @stack('scripts')
</body>
</html>
