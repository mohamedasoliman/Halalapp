@extends('admin.layouts.app')
@section('content')
@include('admin.include.sidebar')

<div class="pcoded-main-container">
	<div class="pcoded-wrapper">
		<div class="pcoded-content">
			<div class="pcoded-inner-content">
				<!-- Main-body start -->
				<div class="main-body">
					<div class="page-wrapper">
						<!-- Page-header start -->
						<div class="page-header">
							<div class="page-header-title">
								<h4>File Management</h4>
							</div>
							<div class="page-header-breadcrumb">
								<ul class="breadcrumb-title">
									<li class="breadcrumb-item">
										<a href="{{ route('admin.dashboard') }}">
											<i class="icofont icofont-home"></i>
										</a>
									</li>
									<li class="breadcrumb-item"><a href="javascript:;">File Management</a>
									</li>
								</ul>
							</div>
						</div>
						<!-- Page-header end -->
						<!-- Page-body start -->
						<div class="page-body">
							<!-- Table header styling table start -->
							@include('admin.messages')
							<!-- Users Management table start -->
							<div class="card">
								<div class="card-header table-card-header">
									<h5>Upload CSV File</h5>
									
								</div>
								<div class="card-block">
									<div class="dt-responsive table-responsive">
									<form action="{{ route('import.process') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="csv_file" accept=".csv">
    <button type="submit">Import CSV</button>
</form>
									</div>
								</div>
							</div>
							<!-- Users Management end -->
						</div>
						<!-- Page-body end -->
					</div>
				</div>
				<!-- Main-body start -->

				<div id="styleSelector">

				</div>

			</div>
		</div>
	</div>
</div>
@include('admin.city.citymodal')
@endsection

@push('scripts')

<script type="text/javascript">

	$(function () {
		oTable = $('#users-table').DataTable({
			"processing": true,
			"serverSide": true,
			ajax: "{{ route("city.index") }}",
			columns: [
			{data: 'DT_RowIndex', name: 'DT_RowIndex'},
			{data: 'fruit_image', name: 'fruit_image', orderable: false},
			{data: 'fruit_name', name: 'fruit_name', orderable: false},
			{data: 'status', name: 'status', orderable: true, searchable: false},
			{data: 'halal_status', name: 'halal_status', orderable: true, searchable: false},
			{data: 'Barcode', name: 'Barcode', orderable: false},
			{data: 'Certification_Status', name: 'Certification_Status', orderable: false},
			{data: 'notes', name: 'notes', orderable: false},
			{data: 'ingredient', name: 'ingredient', orderable: false},
			{data: 'action', name: 'action', orderable: false, searchable: false},
			],
		});

		$(document).on('click', '.status-update', function() {
			$.ajax({
				type: "get",
				url: "{{ route('city.status.update', '') }}/"+$(this).data('id'),
				data: {},
				success: function(res) {
					if (res.status) {
						message(res.message, 'success');
						oTable.rows().invalidate('data').draw(false);
					}
				}
			});
		});
	});
	
	$('#add-main-category').click(function(event) {
		$('.modal-body').find("input,textarea,select").val('').end();
		$('.register-form-errors' ).html('');
		$('#main-category-modal').modal('show');
	});

	$('#halal_status1').click(function(event) {
		$('#halal_status1').val('0');
	});
	$('#halal_status2').click(function(event) {
		$('#halal_status2').val('1');
	});

	$('#add-main-category-form').validate({
		rules: {
			city_name: {
				required: true,
				// remote: {
				// 	headers: {
				// 		'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				// 	},
				// 	url: base_url + "/city/checkcity",
				// 	type: "post",
				// 	data: {
				// 		cityName: function() {
				// 			return $('#cityName').val();
				// 		}
				// 	},
				// 	dataFilter: function (data) {
				// 		var json = JSON.parse(data);
				// 		if (json.msg == "true") {
				// 			return "\"" + "Food already exist, please add different Food name" + "\"";
				// 		} else {
				// 			return 'true';
				// 		}
				// 	}
				// }
			},
			halal_status: {
				required: true,
			},
			city_image: {
				required: true,
				extension: "jpeg|png|jpg|webp"
			},
			Barcode: {
				required: true,
			},
			Certification_Status: {
				required: true,
			},
			notes: {
				required: true,
			},
			ingredient: {
				required: true,
			},
		},
		errorPlacement: function(error, element) {
			if (element.is(":radio")) {
				var name = element.attr('name');
				error.insertAfter("#radio-error");
			}else {
				error.appendTo(element.parent());
			}
		},
		messages:{
			city_name: {
				required: 'Please enter Food name',
			},
			halal_status: {
				required: 'Please Select option',
			},
			city_image: {
				required: 'Please select Food image',
			},
			Barcode: {
				required: 'Please write the product barcode',
			},
			Certification_Status: {
				required: 'Please write the certification status',
			},
			notes: {
				required: 'Please write some notes',
			},
			ingredient: {
				required: 'please add the the ingredients',
			},
		},
		submitHandler: function(form) {
			$('#loading').show();
			$.ajax({
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				url: "{{route('city.store')}}",
				type: "POST",
				data: new FormData(form),
				dataType: 'json',
				contentType: false,
				cache: false,
				processData:false,
				success: function (data) {
					$('#loading').hide();
					if(data.status==1){
						$('#main-category-modal').modal('hide');
						message('Food Add Successfully', 'success');
						oTable.rows().invalidate('data').draw(false);
						window.location.reload(true);
					}
				},
				error: function (data) {
					$('#loading').hide();
					var errorString = '<ul>';
					$.each(data.responseJSON.errors, function( key, value) {
						errorString += '<li>' + value + '</li>';
					});
					errorString += '</ul>';
					message(errorString, 'danger');
				},
			});
			return false;
		}
	});

	function deleteCityModel(id)
	{
		$('#deleteid').val(id);
		$('#delete_modal').modal('show');
	}

	function editCityModel(id)
	{
		var getUrl = window.location;
		var routeUrl = getUrl + '/edit' + "/" + id;

		$('#edi_data_wrap').html('');
		$('#loading').show();

		$.ajax({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			url: routeUrl,
			type: "get",
			dataType: 'json',
			success: function (data) {
				$('#loading').hide();
				$('#edi_data_wrap').html(data.data);
				$('#editModal').modal('show');
			},
			error: function (data) {
			},
		});
	}

	$('#editForm').validate({
		rules: {
			city_name: {
				required: true,
			},
			halal_status: {
				required: true,
			},
			city_image: {
				extension: "jpeg|png|jpg|webp"
			},
			Barcode: {
				required: true,
			},
			Certification_Status: {
				required: true,
			},
			notes: {
				required: true,
			},
			ingredient: {
				required: true,
			},
		},
		errorPlacement: function(error, element) {
			if (element.is(":radio")) {
				var name = element.attr('name');
				error.insertAfter("#radio-error");
			}else {
				error.appendTo(element.parent());
			}
		},
		messages:{
			city_name: {
				required: 'Please enter Food name',
			},
			halal_status: {
				required: 'Please Select option',
			},
			Barcode: {
				required: 'Please write the product barcode',
			},
			Certification_Status: {
				required: 'Please write the certification status',
			},
			notes: {
				required: 'Please write some notes',
			},
			ingredient: {
				required: 'please add the the ingredients',
			},
		},
		submitHandler: function(form) {
			$('#loading').show();
			$.ajax({
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				url: "{{ route('city.update') }}",
				type: "POST",
				data: new FormData(form),
				dataType: 'json',
				contentType: false,
				cache: false,
				processData: false,
				success: function (data) {
					$('#loading').hide();
					if(data.status==1){
						$("#editModal").modal('hide');
						message('Food Update Successfully', 'success');
						oTable.rows().invalidate('data').draw(false);
					}
				},
				error: function (data) {
					$('#loading').hide();
					var errorString = '<ul>';
					$.each(data.responseJSON.errors, function( key, value) {
						errorString += '<li>' + value + '</li>';
					});
					errorString += '</ul>';
					message(errorString, 'danger');
				},
			});
			return false;
		}
	});

	$(document).on('click','.delete_btn', function() {
		$.ajax({
			type: "get",
			url: "{{ route('city.delete','') }}/"+$('#deleteid').val(),
			data: {},
			success: function(data) {
				if(data.status==1){
					$('#delete_modal').modal('hide');
					message(data.messages, 'success');
					oTable.rows().invalidate('data').draw(false);
				}
			}
		});
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

	$("#fileUploader").change(function () {
		$("#display_image").css("display", "block");
		readIMG(this);
	});

	function editReadIMG(input) {
		if (input.files && input.files[0]) {
			var reader = new FileReader();
			reader.onload = function (e) {
				$('#edit_display_image').attr('src', e.target.result);
			}
			reader.readAsDataURL(input.files[0]);
		}
	}

	$(document).on('change', '#editFileUploader', function() {
		editReadIMG(this);
	});

</script>

@endpush