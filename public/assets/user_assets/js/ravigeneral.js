$(function () {
	$('[data-toggle="tooltip"]').tooltip()
})
$('body').on('click', '.btn-add-cart', function() {
	$('.sticky-header').css('margin-right','0px');
});


// Whishlist Products
$('body').on('click', '#whishlist-product', function() {
	$('.sticky-header').css('margin-right','0px');
	var product_id = $(this).data("product-id");

	$.ajax({
		headers: {
			'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		},
		url : user_base_url+'/wishlist',
		type: "POST",
		data: { 'product_id':product_id },
		dataType: 'json',
		success: function (data) {
			console.log($('.product-remove'+data.product_id+''));
			$('#loading').hide();
			if (data.status == 1) {
				$('.cartmsg').html(data.message);
				$('.heart'+data.product_id+'').addClass('fillheart');
				$('.carts-count').html(data.wishlistcount);
			} else if(data.status == 2) {
				$('.cartmsg').html(data.message);
				$('.heart'+data.product_id+'').removeClass('fillheart');
				$('.product-remove'+data.product_id+'').css('dispaly','none');
				$('.product-remove'+data.product_id+'').addClass('prodcut-none');
				$('.carts-count').html(data.wishlistcount);
			} else {
				$('.heart'+data.product_id+'').removeClass('fillheart');
				$('.product-remove'+data.product_id+'').addClass('prodcut-none');
			}
		},
		error: function (data) {
			$('#loading').hide();
		},
	});
	
});


// Email Subscriber Form
$('#emailsubscriberform').validate({
	rules: {
		email: {
			required: true,
			email:true,
		},
	},
	errorPlacement: function (error, element) {
		if (element.is(":input")) {
			var name = element.attr('name');
			console.log(name);
			error.insertAfter("#" + name + "_radio-error");
		} else {
			error.appendTo(element.parent());
		}
	},
	messages: {
		email: {
			required: 'Please Enter Email Address',
		},
	},
	submitHandler: function (form) {
		$('#loading').show();
		$.ajax({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			url: user_base_url+'/emailsubscriber',
			type: "POST",
			data: new FormData(form),
			dataType: 'json',
			contentType: false,
			cache: false,
			processData:false,
			success: function (data) {
				$('#email_radio-error').html(' ');
				$('#loading').hide();
				if (data.status == 1) {
					$('#email_radio-error').html(data.messages);
				} else if(data.status == 2) {
					$('#email_radio-error').html(data.messages);
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


// Add to Cart Products

$('body').on('click', '.add-to-carts', function() {

	if($('.normal-size-chart').length > 0)
	{	
		if($('.normal-size-chart').hasClass('active')) { } else {
			message('Please select a Size to proceed','danger');
			return false;
		}
	}

	if($('.shooes-size-chart').length > 0)
	{
		if($('.shooes-size-chart').hasClass('active')) { } else {
			message('Please select a Size UK/India to proceed', 'danger');
			return false;
		}
	}

	$('#loading').show();
	var selectednormalsize = $('.selectednormalsize').val();
	var selectedshooessize = $('.selectedshooessize').val();
	var selectedproduct = $('.selectedproduct').val();
		$.ajax({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			url: user_base_url+'/addtocart',
			type: "POST",
			data: { 'selectednormalsize':selectednormalsize,'selectedshooessize':selectedshooessize,'selectedproduct':selectedproduct },
			dataType: 'json',
			success: function (data) {
				$('#loading').hide();
				if (data.status == 1) {
					message(data.messages, 'danger');
					location.reload();
				} else if(data.status == 2) {
					window.location.href = user_base_url+'/cart';
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

});

// Choose S/M/XL Size
$('body').on('click', '.normal-size-chart', function() {
	$(".normal-size-chart").removeClass('active');
	$(".selectednormalsize").val('');
	$(this).addClass('active');
	$(".selectednormalsize").val($(this).data('size'));
});

// Choose Shooes Size
$('body').on('click', '.shooes-size-chart', function() {
	$(".shooes-size-chart").removeClass('active');
	$(".selectedshooessize").val('');
	$(this).addClass('active');
	$(".selectedshooessize").val($(this).data('size'));
});

// Choose Shipping address and payments
$('body').on('click', '.on-next-shipping', function() {
	var address_id = $('.address_id').val()
	var payment_method = $('input[type="radio"][name="shipping-method"]:checked').val();
	$('.error-shipping-page').html('');
	if($('.address_id').val() == '') {
		$('.error-shipping-page').html('Please Choose Shipping Address');
	} else if($('input[type="radio"][name="shipping-method"]:checked').val() == undefined) {
		$('.error-shipping-page').html('Please Choose Payment method');
	} else {
		$('.error-shipping-page').html('');
		$.ajax({
            headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: user_base_url+'/shipping-order',
            type: "POST",
            data: { 'address_id':address_id,'payment_method':payment_method },
            dataType: 'json',
            success: function (data) {
                window.location.href = user_base_url+'/checkout-review';
            },
            error: function (data) {
                $('#loading').hide();
            },
        });
	}
});