<!DOCTYPE html>
<html lang="en">
@php
    $setting = App\Models\GeneralSetting::first();

@endphp
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>{{ $setting->website_title ? $setting->website_title: '' }} - @yield('title')</title>

    <meta name="keywords" content="HTML5 Template"/>
    <meta name="description" content="Porto - Bootstrap eCommerce Template">
    <meta name="author" content="SW-THEMES">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/user_assets/images/icons/favicon.ico') }}">
{{--    <link href="https://harshen.github.io/jquery-countdownTimer/CSS/jquery.countdownTimer.css">--}}


    <script type="text/javascript">
        WebFontConfig = {
            google: {families: ['Open+Sans:300,400,600,700,800', 'Poppins:300,400,500,600,700']}
        };
        (function (d) {
            var wf = d.createElement('script'), s = d.scripts[0];
            wf.src = '{{ asset('assets/user_assets/js/webfont.js') }}';
            wf.async = true;
            s.parentNode.insertBefore(wf, s);
        })(document);
    </script>

    <!-- Plugins CSS File -->
    <link rel="stylesheet" href="{{ asset('assets/user_assets/css/bootstrap.min.css') }}">

    <!-- Main CSS File -->
    <link rel="stylesheet" href="{{ asset('assets/user_assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/user_assets/css/general.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/user_assets/css/ravigeneral.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/user_assets/vendor/fontawesome-free/css/all.min.css') }}">
    <script src="{{ asset('assets/user_assets/js/jquery.min.js') }}"></script>

    <!-- Validation js -->
    <script src="{{asset('assets/js/jquery-validate/jquery.validate.min.js')}}"></script>
    <script src="{{asset('assets/js/jquery-validate/additional-methods.min.js')}}"></script>
    <script>
        var base_url = '{{ env('ADMIN_APP_URL') }}';
        var user_base_url = '{{ env('APP_URL') }}';
        var current_route = '{{ \Request::route()->getName() }}';
    </script>
    @yield('after-styles')

    @stack('styles')
</head>
<body>
    <div class="page-wrapper">


<main class="home main">
    @yield('content')
</main><!-- End .main -->

<footer class="footer">
    <div class="footer-top">
        <div class="container">
            <div class="newsletter-widget">
                <i class="icon-envolope"></i>

                <div class="newsletter-info">
                    <h3>Get Special Offers and Savings</h3>
                    <p>Get all the latest information on Events, Sales and Offers.</p>
                </div>

                <form method="post" id="emailsubscriberform">
                    @csrf
                    <div class="submit-wrapper">
                        <input type="search" class="form-control" name="email" id="emailsubscriber"
                        placeholder="Enter Your E-mail Address..." required="">
                        <button class="btn" type="submit">Sign Up</button>
                    </div>
                    <span id="email_radio-error" style="color: white;"></span>
                </form>
            </div>

            <div class="social-icons">
               {{-- @foreach(socialdata() as $social)
               <a href="{{ $social->url }}" target="_blank" class="social-icon"><i class="{{ $social->fontawesome_code }}"></i></a>
               @endforeach --}}
           </div>
       </div>
   </div>
   <div class="footer-middle">
    <div class="container">
        <div class="row row-sm">
            <div class="col-md-12 text-center">
                <h4 class="pb-4 delivered_msg">
                    <i class="fab fa-alarm-clock"></i>
                    <span>Time Left For Today's Delivery </span>
                    <span id="hs_timer" class="text-danger"></span>
                </h4>
            </div>
            <div class="col-md-6 col-lg-3">
                <img src="{{ asset('assets/user_assets/images/logo-white.png') }}">
                <p>If you have any query please contact us.</p>
                <div class="social-link">
                    <h3 class="link-title">CALL</h3>
                    <a href="tel:{{ $setting->phone ? $setting->phone : '' }}">{{ $setting->phone ? $setting->phone : '' }}</a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="widget">
                    <h3 class="widget-title">Social</h3>
                    <div class="widget-content row row-sm">
                        <ul class="col-xl-12">
                            {{-- @foreach(socialdata() as $social)
                            <li><a href="{{ $social->url }}" target="_blank">{{ ucfirst($social->title) }}</a></li>
                            @endforeach --}}
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="widget">
                    <h3 class="widget-title">About</h3>
                    <div class="widget-content row row-sm">
                        <ul class="col-xl-12">
                            {{-- @foreach(footerpages() as $page)
                            <li><a href="{{ route('page',$page->slug) }}" title="{{ $page->title }}">{{ $page->title }}</a></li>
                            @endforeach --}}
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="widget">
                    <h3 class="widget-title text-center">Contact Info</h3>
                    <div class="widget-content">
                        <ul class="footer-contact">
{{--                            <li><a href="javascript:void(0)"><i class="icon-location fa-2x text-white"></i></a>{{ $setting->address ? $setting->address : '' }}</li>--}}
                            <li><a href="tel:{{ $setting->phone ? $setting->phone: '' }}"><i class="icon-mobile fa-2x text-white"></i></a>{{ $setting->phone ? $setting->phone: '' }}</li>
                            <li><a href="mailto:{{ $setting->email ? $setting->email: '' }}"><i class="icon-mail-alt fa-2x text-white"></i></a>{{ $setting->email ? $setting->email : '' }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="footer-bottom container">
    <p>{{ $setting->website_title ? $setting->website_title: '' }}. © {{ date('Y') }}. All Rights Reserved</p>
</div>
</footer><!-- End .footer -->
</div><!-- End .page-wrapper -->

<div class="mobile-menu-overlay"></div><!-- End .mobil-menu-overlay -->

<div class="mobile-menu-container">
    <div class="mobile-menu-wrapper">
        <span class="mobile-menu-close"><i class="icon-retweet"></i></span>
        <nav class="mobile-nav">
            <ul class="mobile-menu">
                <li class="{{ Request::route()->getName() == 'user.home' ? 'active' : '' }}"><a href="{{ route('user.home') }}">Home</a></li>
                <li>
                    <a href="#">Categories</a>
                    <ul>
                        @php
                            $allmenus = allMainCategory();
                        @endphp
                        @if(!empty($allmenus))
                            @foreach($allmenus as $menu)
                                <li>
                                    <a href="{{ route('maincategories.catname',$menu->id) }}">{{ $menu->main_category_name }}</a>
                                    @php
                                        $allsubmenus = allSubCategory($menu->id);
                                    @endphp
                                    @if(!empty($allsubmenus))
                                        <ul>
                                            @foreach($allsubmenus as $submenu)
                                                <li><a href="#">{{ $submenu->sub_category_name }}</a></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </li>
                <li class="{{ Request::route()->getName() == 'product-list' ? 'active' : '' }}"><a href="{{ route('product-list') }}">Product List</a></li>
                <li class="{{ Request::route()->getName() == 'shop-form' ? 'active' : '' }}"><a href="{{ route('shop-form') }}">Shop Inquiry</a></li>
                <li class="{{ Request::route()->getName() == 'contact.us' ? 'active' : '' }}"><a href="{{ route('contact.us') }}">Contact Us</a></li>
            </ul>
        </nav><!-- End .mobile-nav -->

        <div class="social-icons">
            <a href="#" class="social-icon" target="_blank"><i class="icon-facebook"></i></a>
            <a href="#" class="social-icon" target="_blank"><i class="icon-twitter"></i></a>
            <a href="#" class="social-icon" target="_blank"><i class="icon-instagram"></i></a>
        </div><!-- End .social-icons -->
    </div><!-- End .mobile-menu-wrapper -->
</div><!-- End .mobile-menu-container -->

<!-- newsletter-popup-form -->
{{--<div class="newsletter-popup mfp-hide bg-img" id="newsletter-popup-form"
     style="background-image: url({{ asset('assets/user_assets/images/newsletter_popup_bg.jpg') }})">
    <div class="newsletter-popup-content">
        <img src="{{ asset('assets/user_assets/images/logo-black.png') }}" alt="Logo" class="logo-newsletter">
        <h2>BE THE FIRST TO KNOW</h2>
        <p>Subscribe to the Porto eCommerce newsletter to receive timely updates from your favorite products.</p>
        <form action="#">
            <div class="input-group">
                <input type="email" class="form-control" id="newsletter-email" name="newsletter-email"
                       placeholder="Email address" required>
                <input type="submit" class="btn" value="Go!">
            </div>
        </form>
        <div class="newsletter-subscribe">
            <div class="checkbox">
                <label>
                    <input type="checkbox" value="1">
                    Don't show this popup again
                </label>
            </div>
        </div>
    </div>
</div>--}}

<!-- Add Cart Modal -->
<div class="modal fade" id="addCartModal" tabindex="-1" role="dialog" aria-labelledby="addCartModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body add-cart-box text-center">
                <p>You've just added this product to the<br>cart:</p>
                <h4 id="productTitle"></h4>
                <img src="#" id="productImage" width="100" height="100" alt="adding cart image">
                <div class="btn-actions">
                    <a href="cart.html">
                        <button class="btn-primary">Go to cart page</button>
                    </a>
                    <a href="#">
                        <button class="btn-primary" data-dismiss="modal">Continue</button>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Whishlist Modal -->
<div class="modal fade" id="addWhishlistModal" tabindex="-1" role="dialog" aria-labelledby="addWhishlistModal" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body add-cart-box text-center">
                <p>You've just <span class="cartmsg"></span> this product to the<br>whishlist:</p>
                <h4 id="productTitle"></h4>
                <img src="#" id="productImage" width="100" height="100" alt="adding cart image">
                <div class="btn-actions">
                    <a href="{{ route('wishlist.listing') }}">
                        <button class="btn-primary">Go to whishlist</button>
                    </a>
                    <a href="javascript:void(0)">
                        <button class="btn-primary" data-dismiss="modal">Continue</button>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="modal fade" id="quickviewModalCenter" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="height: 43px;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="quickviewContent">
                    {{--Ajax response--}}
                </div>
            </div>
        </div>
    </div>

<a id="scroll-top" href="#top" title="Top" role="button"><i class="icon-angle-up"></i></a>
@include('auth.login_popup')
@include('auth.register_popup')

<!-- Plugins JS File -->
<script src="{{ asset('assets/user_assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/user_assets/js/plugins.min.js') }}"></script>
<script src="{{ asset('assets/user_assets/js/nouislider.min.js') }}"></script>
{{--<script src="https://refreshless.com/nouislider/documentation/assets/wNumb.js"></script>--}}
<script src="{{ asset('assets/user_assets/js/plugins/isotope-docs.min.js') }}"></script>

<!-- Main JS File -->
<script src="{{ asset('assets/user_assets/js/main.min.js') }}"></script>
<script src="{{asset('assets/js/bootstrap-notify.min.js')}}"></script>
<script src="{{ asset('assets/user_assets/js/general.js') }}"></script>
<script src="{{ asset('assets/user_assets/js/ravigeneral.js') }}"></script>
    <script src="{{ asset('assets/user_assets/js/jquery.countdownTimer.min.js') }}"></script>

    @php
        $timer = footerTimer();
        if ($timer['status']) {
            @endphp
                <script>
                    $(function(){
                        $("#hs_timer").countdowntimer({
                            hours: '{{ $timer['diff']->h }}',
                            minutes: '{{ $timer['diff']->i }}',
                            seconds: '{{ $timer['diff']->s }}',
                            size: "lg"
                        });
                    });
                </script>
            @php
        }else{
            echo "<script>$('.delivered_msg').html('Your order/package is accepted and delivered by tomorrow.')</script>";
        }
    @endphp
<script>
    @if(Session::has('status'))
    var msg = "{{ Session::get('status') }}";
    message(msg, 'success');
    @endif

    @if(Session::has('success'))
    var msg = "{{ Session::get('success') }}";
    message(msg, 'success');
    @endif

    @if(Session::has('error'))
    var msg = "{{ Session::get('error') }}";
    message(msg, 'danger');
    @endif

    @if(\Request::route()->getName() == 'login' || \Request::route()->getName() == 'register')
        window.location.href = user_base_url;
    @endif
</script>
@yield('after-scripts')
@stack('scripts')
</body>

</html>
