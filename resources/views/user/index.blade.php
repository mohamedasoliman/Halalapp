@extends('user.layouts.app')
@section('title', 'Home Page')

@section('content')
    <div class="top-slider owl-carousel owl-theme" data-toggle="owl" data-owl-options="{
                    'items' : 1,
                    'margin' : 0,
                    'nav': true,
                    'dots': false,
                    'autoplay': false
                }">
        <div class="home-slide">
            <div class="slide-content flex-column flex-lg-row">
                <div class="content-left mx-auto mr-lg-0 py-5">
                    <span>EXTRA</span>
                    <h2>20% OFF</h2>
                    <h4 class="cross-txt">BIKES</h4>
                    <h3 class="mb-2 mb-lg-8">Summer Sale</h3>
                    <button class="btn">Shop All Sale</button>
                </div>
                <div class="image-container mx-auto py-5">
                    <img src="{{ asset('assets/user_assets/images/slider/slide2.png') }}" class="slide-img1"
                         alt="slide image">
                    <div class="image-info mt-2 mt-lg-6 flex-column flex-sm-row">
                        <div class="info-left">
                            <h4>only <span><sup>$</sup>399<sup>99</sup></span></h4>
                        </div>
                        <div class="info-right">
                            <h4>Start Shopping Right Now</h4>
                            <p>*Get Plus Discount Buying Package</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="home-slide">
            <div class="slide-content flex-column flex-lg-row">
                <img src="{{ asset('assets/user_assets/images/slider/slide1.png') }}" class="mx-auto mr-lg-0 py-5"
                     alt="slide image">
                <div class="content-right order-first order-lg-1 mx-auto py-5">
                    <span>EXTRA</span>
                    <h2>20% OFF</h2>
                    <h4 class="cross-txt">BIKES</h4>
                    <h3 class="mb-2 mb-lg-8">Summer Sale</h3>
                    <button class="btn">Shop All Sale</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <section class="product-panel">
            <div class="section-title">
                <h2>Most Popular Products</h2>
            </div>
            <div class="owl-carousel owl-theme" data-toggle="owl" data-owl-options="{
                            'margin': 4,
                            'items': 2,
                            'autoplayTimeout': 5000,
                            'dots': false,
                            'nav' : true,
                            'responsive': {
                                '768': {
                                    'items': 3
                                },
                                '992' : {
                                    'items' : 4
                                },
                                '1200': {
                                    'items': 5
                                }
                            }
                        }">
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-8.jpg') }}">
                        </a>
                        <div class="label-group">
                            <div class="product-label label-black">1<sup>o</sup></div>
                        </div>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-9.jpg') }}">
                        </a>
                        <div class="label-group">
                            <div class="product-label label-black">2<sup>o</sup></div>
                        </div>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-10.jpg') }}">
                        </a>
                        <div class="label-group">
                            <div class="product-label label-black">3<sup>o</sup></div>
                        </div>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-6.jpg') }}">
                        </a>
                        <div class="label-group">
                            <div class="product-label label-black">4<sup>o</sup></div>
                        </div>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-7.jpg') }}">
                        </a>
                        <div class="label-group">
                            <div class="product-label label-black">5<sup>o</sup></div>
                        </div>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-6.jpg') }}">
                        </a>
                        <div class="label-group">
                            <div class="product-label label-black">6<sup>o</sup></div>
                        </div>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
            </div>
        </section>
    </div>

    <div class="home-banner mb-3 flex-wrap flex-md-nowrap">
        <div class="banner-left mb-1 mb-md-0 mx-auto ml-md-0 mr-md-3">
            <img src="{{ asset('assets/user_assets/images/banners/banner1.jpg') }}" alt="banner">
        </div>
        <div class="banner-right">
            <img src="{{ asset('assets/user_assets/images/banners/banner2.jpg') }}" alt="banner">
            <div class="banner-content">
                <h2>get ready</h2>
                <div class="mb-1">
                    <h3 class="align-middle d-inline">20% Off</h3>
                    <a href="#" class="btn">Shop All Sale</a>
                </div>
                <h4 class="cross-txt">bikes</h4>
            </div>
        </div>
    </div>

    <div class="container">
        <section class="product-panel">
            <div class="section-title">
                <h2>Trending Accessories</h2>
            </div>
            <div class="owl-carousel owl-theme" data-toggle="owl" data-owl-options="{
                            'margin': 4,
                            'items': 2,
                            'autoplayTimeout': 5000,
                            'dots': false,
                            'nav' : true,
                            'responsive': {
                                '768': {
                                    'items': 3
                                },
                                '992' : {
                                    'items' : 4
                                },
                                '1200': {
                                    'items': 5
                                }
                            }
                        }">
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-1.jpg') }}">
                        </a>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-2.jpg') }}">
                        </a>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-3.jpg') }}">
                        </a>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-4.jpg') }}">
                        </a>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-5.jpg') }}">
                        </a>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
                <div class="product-default inner-quickview inner-icon center-details">
                    <figure>
                        <a href="product.html">
                            <img src="{{ asset('assets/user_assets/images/products/grey/product-6.jpg') }}">
                        </a>
                        <div class="btn-icon-group">
                            <button class="btn-icon btn-add-cart" data-toggle="modal" data-target="#addCartModal"><i
                                    class="icon-bag"></i></button>
                        </div>
                        <a href="ajax/product-quick-view.html" class="btn-quickview" title="Quick View">Quick
                            View</a>
                    </figure>
                    <div class="product-details">
                        <div class="category-wrap">
                            <div class="category-list">
                                <a href="category.html" class="product-category">category</a>
                            </div>
                        </div>
                        <h2 class="product-title">
                            <a href="product.html">Product Short Name</a>
                        </h2>
                        <div class="ratings-container">
                            <div class="product-ratings">
                                <span class="ratings" style="width:100%"></span><!-- End .ratings -->
                                <span class="tooltiptext tooltip-top"></span>
                            </div><!-- End .product-ratings -->
                        </div><!-- End .product-container -->
                        <div class="price-box">
                            <span class="old-price">$59.00</span>
                            <span class="product-price">$49.00</span>
                        </div><!-- End .price-box -->
                    </div><!-- End .product-details -->
                </div>
            </div>
        </section>

        <div class="row row-sm mb-5 home-banner4 text-center">
            <div class="col-sm-6">
                <div class="row row-sm home-banner4-white">
                    <div class="col-md-4">
                        <span>Summer Sale</span>
                        <h3>20% OFF</h3>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <img class="banner-image" src="{{ asset('assets/user_assets/images/banners/banner4.jpg') }}"
                             alt="banner">
                    </div>
                    <div class="col-md-4 d-flex align-items-center justify-content-center">
                        <button class="btn">shop all sale</button>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="row row-sm home-banner4-primary">
                    <div class="col-md-4">
                        <span>Flash Sale</span>
                        <h3>30% OFF</h3>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <img class="banner-image" src="{{ asset('assets/user_assets/images/banners/banner5.jpg') }}"
                             alt="banner">
                    </div>
                    <div class="col-md-4 d-flex align-items-center justify-content-center">
                        <button class="btn">shop all sale</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop
