function message(msg, type) {
    $.notify({
        message: msg
    }, {
        type: type,
        z_index: 999999999999,
    });
}

$("body").on("click", "a.btn-quickview", function (i) {
    $("body").append("<div class='ajax-overlay'><i class='porto-loading-icon'></i></div>");
    var projectId = $(this).data('product-id');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: user_base_url+'/get-product-quick-view',
        type: "POST",
        data: {'project_id': projectId},
        dataType: 'json',
        success: function (data) {
            if (data.status) {
                $(".ajax-overlay").remove();
                $("#quickviewModalCenter").modal('show');
                $(".quickviewContent").html(data.quick_view);
            }
        },
        complete: function (data) {
            setTimeout(function(){ slider(); }, 1000);
        }
    });
});


function slider() {
    var e = $;
    var t = {
        loop: !0,
        margin: 0,
        responsiveClass: !0,
        nav: !1,
        navText: ['<i class="icon-left-open-big">', '<i class="icon-right-open-big">'],
        dots: !0,
        autoplay: !0,
        autoplayTimeout: 15e3,
        items: 1
    };
    e('[data-toggle="owl"]').each(function () {
        var i = e(this).data("owl-options");
        "string" == typeof i && (i = JSON.parse(i.replace(/'/g, '"').replace(";", "")));
        var o = e.extend(!0, {}, t, i);
        e(this).owlCarousel(o);
    }),
        e(".widget-featured-products").owlCarousel(e.extend(!0, {}, t, {
            lazyLoad: !0,
            nav: !0,
            navText: ['<i class="icon-angle-left">', '<i class="icon-angle-right">'],
            dots: !1,
            autoHeight: !0
        })),
        e(".testimonials-slider").owlCarousel(e.extend(!0, {}, t, {
            lazyLoad: !0,
            navText: ['<i class="icon-angle-left">', '<i class="icon-angle-right">'],
            autoHeight: !0
        })),
        e(".entry-slider").each(function () {
            e(this).owlCarousel(e.extend(!0, {}, t, {margin: 2, lazyLoad: !0}));
        }),
        e(".related-posts-carousel").owlCarousel(e.extend(!0, {}, t, {
            loop: !1,
            margin: 30,
            autoplay: !1,
            responsive: {0: {items: 1}, 480: {items: 2}, 1200: {items: 3}}
        })),
        e(".boxed-slider").owlCarousel(e.extend(!0, {}, t, {
            lazyLoad: !0,
            autoplayTimeout: 2e4,
            animateOut: "fadeOut"
        })),
        e(".boxed-slider").on("loaded.owl.lazy", function (t) {
            e(t.element).closest(".boxed-slider").addClass("loaded");
        }),
        e(".product-single-default .product-single-carousel").owlCarousel(
            e.extend(!0, {}, t, {
                nav: !0,
                navText: ['<i class="icon-angle-left">', '<i class="icon-angle-right">'],
                dotsContainer: "#carousel-custom-dots",
                autoplay: !1,
                onInitialized: function () {
                    // alert();
                    var t = this.$element;
                    e.fn.elevateZoom &&
                    t.find("img").each(function () {
                        var t = e(this),
                            i = {
                                responsive: !0,
                                zoomWindowFadeIn: 350,
                                zoomWindowFadeOut: 200,
                                borderSize: 0,
                                zoomContainer: t.parent(),
                                zoomType: "inner",
                                cursor: "grab"
                            };
                        t.elevateZoom(i);
                    });
                },
            })
        ),
        e(".product-single-extended .product-single-carousel").owlCarousel(e.extend(!0, {}, t, {
            dots: !1,
            autoplay: !1,
            responsive: {0: {items: 1}, 480: {items: 2}, 1200: {items: 3}}
        })),
        e("#carousel-custom-dots .owl-dot").click(function () {
            e(".product-single-carousel").trigger("to.owl.carousel", [e(this).index(), 300]);
        });
    /*$(".mfp-content").css("opacity", 0);
    $(".mfp-content").html(data.quick_view);*/
}

$(".cart-icn").hover(function () {
    ajaxViewCart();
});

function ajaxViewCart(cart_id = '', product_id = '', is_delete = '') {
    $.ajax({
        type: "get",
        url: user_base_url+'/ajax-view-cart',
        // url: "{{ route('ajax-view-cart', '') }}",
        data: {'cart_id': cart_id, 'product_id': product_id},
        /*beforeSend: function () {
            $('.loading-overlay').addClass('loader');
        },*/
        success: function (res) {
            // $('.loading-overlay').removeClass('loader');
            if (res.status) {
                $('.ajax-view-cart').html(res.data);
                $('.cart-count').html(res.cart_count);
                if (current_route == 'cart' && is_delete != ''){
                    location.reload();
                }
            }
        },
        error: function (data) {
            $('#loading').hide();
        },
    });
}

$('body').on('click', '.ajax-cart-remove', function() {
    $(this).addClass('inactiveLink');

    var product_id = $(this).data("ajx-product-id");
    var cart_id = $(this).data("ajx-cart-id");
    ajaxViewCart(cart_id, product_id, 1);
});
