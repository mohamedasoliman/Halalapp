function message(msg, type) {
	$.notify({
		message: msg
	}, {
		type: type,
		z_index: 999999999999,
	});
}

$(document).ready(function() {
	
    var max_fields      = 30; //maximum input boxes allowed
    var wrapper         = $(".input_fields_wrap"); //Fields wrapper
    var add_button      = $(".add_field_button"); //Add button ID
    
    if($('#foreachcount').val() > 0) {
    	var total = $('#foreachcount').val();
    	var x = total - 1; //initlal text box count
    } else {
    	var x = 1; //initlal text box count
	}
    $(add_button).click(function(e) { //on add input button click
    	e.preventDefault();
        if(x < max_fields) { //max input box allowed
        	$('#fields_not_proper').html('');
        	var heading = $("input[name='product_specification_heading["+ x +"]']").val();
        	var values = $("input[name='product_specification_value["+ x +"]']").val();
        	
        	if(heading == '') {

        		$('#fields_not_proper').html('Heading is required');

        	} else if(values == '') {
        		$('#fields_not_proper').html('Values is required');

        	} else {
	            x++; //text box increment
	            $(wrapper).append('<div class="form-group row speci'+x+'">\
	            	<label class="col-sm-2 col-form-label"></label>\
	            	<div class="col-sm-4">\
	            	<input name="product_specification_heading[' + x + ']" id="product_specification_heading" type="text" placeholder="Specification (Heading)" class="form-control">\
	            	</div>\
	            	<div class="col-sm-4">\
	            	<input name="product_specification_value[' + x + ']" id="product_specification_value" placeholder="Specification (Value)" type="text" class="form-control">\
	            	</div>\
	            	<div class="col-sm-2">\
	            	<a data-id="'+x+'" href="#" class="remove_field btn btn-danger">Remove</a> </div>\
	            	</div>\
					</div>'); // add input boxes.
	        }

	    }
	});
    
    $(wrapper).on("click",".remove_field", function(e) { //user click on remove text
    	$('#fields_not_proper').html('');
    	var countid = $(this).data('id');
    	e.preventDefault(); $(".speci"+countid+"").remove(); x--;
    })
});
