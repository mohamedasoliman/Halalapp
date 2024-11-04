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
								<h4>Admin Users Management</h4>
							</div>
							<div class="page-header-breadcrumb">
								<ul class="breadcrumb-title">
									<li class="breadcrumb-item">
										<a href="{{ route('admin.dashboard') }}">
											<i class="icofont icofont-home"></i>
										</a>
									</li>
									<li class="breadcrumb-item"><a href="javascript:;">Admin Users Management</a>
									</li>
								</ul>
							</div>
						</div>
						<!-- Page-header end -->
						<!-- Page-body start -->
						<div class="page-body">
							<!-- Table header styling table start -->
							<div class="card">
								<div class="card-header">
									<h5>Admin Users Management</h5>
                                    @if(userRoleCheck([1]))
                                        <div class="float-right">
                                            <a href="javascript:;" id="add-new-user" class="btn btn-primary"><i class="fa fa-plus"></i> Add Admin User</a>
                                        </div>
                                    @endif
								</div>
								<div class="card-block">
									<div class="dt-responsive table-responsive">
										<table id="users-table" class="table table-striped table-bordered nowrap">
											<thead>
												<tr>
													<th>ID</th>
													<th>Image</th>
													<th>Name</th>
													<th>Role</th>
													<th>Email</th>
													<th>Status</th>
													<th>Actions</th>
												</tr>
											</thead>
											<tbody>

											</tbody>
											<tfoot>
												<tr>
													<th>ID</th>
													<th>Image</th>
													<th>Name</th>
                                                    <th>Role</th>
													<th>Email</th>
													<th>Status</th>
													<th>Actions</th>
												</tr>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
							<!-- Table header styling table end -->
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

<div class="modal fade" id="shopUserModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">Shop User Note</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="shop_success_message">

            </div>
            <div class="modal-footer">
                <button type="button" id="shop_user_create" class="btn btn-primary" data-dismiss="modal">Add Shop User</button>
            </div>
        </div>
    </div>
</div>
@include('admin.admin-user.admin-modals')
@endsection

@push('scripts')
<script>

    @if(Session::has('success'))
    var msg = "{{ Session::get('success') }}";
    $("#shop_success_message").html(msg);
    $('#shopUserModal').modal('show');
    @endif

	$(function () {
	    $("#shop_user_create").click(function () {
	        $( "#add-new-user" ).trigger( "click" );
        });

        oTable = $('#users-table').DataTable({
            "processing": true,
            "serverSide": true,
            ajax: "{{ route("admin.users") }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'admin_image', name: 'admin_image', sortable: false},
				{data: 'name', name: 'name', sortable: false},
				{data: 'role_name', name: 'role_name', sortable: true},
				{data: 'email', name: 'email', sortable: false},
				{data: 'status', name: 'status',sortable: true},
				{data: 'actions', name: 'actions', searchable: false, sortable: false,"width": "15%"}
            ],
        });

        $(document).on('click', '.status-update', function() {
            $.ajax({
                type: "get",
                url: "{{ route('admin.status.update', '') }}/"+$(this).data('id'),
                data: {},
                success: function(res) {
                    if (res.status) {
                    	message(res.messages, 'success');
                        oTable.rows().invalidate('data').draw(false);
                    }
                }
            });
        });
    });


	$('#add-new-user').click(function(event) {
		$('.modal-body').find("input[type=text],input[type=email],input[type=number],input[type=password],textarea,select").val('').end();
		$('#main-user-modal').modal('show');
	})

    $("#role_id").change(function () {
        if ($('#role_id').val() == 2) {
            $(".shop_cls").removeClass('d-none');
        } else {
            $(".shop_cls").addClass('d-none');
        }
    });

	$("#shop_id").change(function () {
        if ($(this).val() == '') {
            $('#shop_error').html('Please select shop');
        } else {
            $('#shop_error').html('');
        }
    });

	$('#add-main-user-form').validate({
		rules: {
			name: {
				required: true,
			},
            role: {
				required: true,
			},
			email: {
				required: true,
				email:true,
				remote: {
					headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
					},
					url: base_url + "/admin/checkemail",
					type: "post",
					data: {
						email: function() {
							return $('#emailaddress').val();
						}
					},
				    dataFilter: function (data) {
				        var json = JSON.parse(data);
				        if (json.msg == "true") {
				            return "\"" + "Email already exist, please add different email address" + "\"";
				        } else {
				            return 'true';
				        }
				    }
				}
			},
            phone: {
                required: true,
                minlength: 10,
                maxlength: 10,
                digits: true,
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
		errorPlacement: function(error, element) {
			if (element.is(":radio")) {
				var name = element.attr('name');
				error.insertAfter("#"+name+"_radio-error");
			}else {
				error.appendTo(element.parent());
			}
		},
		messages:{
			name: {
				required: 'Please Enter Your Name',
			},
            role: {
				required: 'Please Select Role',
			},
			email: {
				required: 'Please Enter Your Email',
			},
            phone: {
                required: 'Please Enter Your Mobile No',
                minlength: 'Please Enter 10 Digit Mobile No',
            },
			password:{
				required: 'Please Enter Your Password',
				minlength: 'Password Length must be 8 Character',
			},
			password_confirmation:{
				required: 'Please Confirm Your Password',
			},
		},
		submitHandler: function(form) {
			$.ajax({
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				url: "{{route('add.adminusers')}}",
				type: "POST",
				data: new FormData(form),
				dataType: 'json',
				contentType: false,
				cache: false,
				processData:false,
				success: function (data) {
					$('#loading').hide();
					if(data.status==1){
						$('#main-user-modal').modal('hide');
						message(data.messages, 'success');
                        oTable.rows().invalidate('data').draw(false);
                        setTimeout(function(){ location.reload() }, 3000);
					}
				},
                beforeSend: function() {
				    if($('#role_id').val() == 2 && $('#shop_id').val() == '') {
                        // message('Please select shop', 'danger');
                        $('#shop_error').html('Please select shop');
                        return false;
                    }
				    $("#loading").show();
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

	function deleteMainCategoryModel($id)
	{
		$('#deleteid').val($id);
		$('#delete_modal').modal('show');
	}

	function deleteUserModel($id)
	{
		$('#deleteid').val($id);
		$('#delete_modal').modal('show');
	}

	function UserBlockModal($id)
	{
		$('#blockid').val($id);
		$('#block_modal').modal('show');
	}

	function UserUnblockModal($id)
	{
		$('#unblockid').val($id);
		$('#unblock_modal').modal('show');
	}

    function editMainCategoryModel(id)
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
			name: {
				required: true,
			},
			email: {
				required: true,
				email:true,
				remote: {
					headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
					},
					url: base_url + "/admin/checkemail",
					type: "post",
					data: {
						email: function() {
							return $('#editemailaddress').val();
						},
						update_id: function() {
							return $('#updateId').val();
						},
					},
				    dataFilter: function (data) {
				        var json = JSON.parse(data);
				        if (json.msg == "true") {
				            return "\"" + "Email already exist, please add different email address" + "\"";
				        } else {
				            return 'true';
				        }
				    }
				}
			},
			password: {
				required: true,
				minlength: 8,
			},
            phone: {
                required: true,
                minlength: 10,
                maxlength: 10,
                digits: true,
            },
			password_confirmation: {
				required: true,
				equalTo: "#password",
			},
		},
		errorPlacement: function(error, element) {
			if (element.is(":radio")) {
				var name = element.attr('name');
				error.insertAfter("#"+name+"_radio-error");
			}else {
				error.appendTo(element.parent());
			}
		},
		messages:{
			name: {
				required: 'Please Enter Your Name',
			},
			email: {
				required: 'Please Enter Your Email',
			},
            phone: {
                required: 'Please Enter Your Mobile No',
                minlength: 'Please Enter 10 Digit Mobile No',
            },
			password:{
				required: 'Please Enter Your Password',
				minlength: 'Password Length must be 8 Character',
			},
			password_confirmation:{
				required: 'Please Confirm Your Password',
			},
		},
    	submitHandler: function(form) {
	        $('#loading').show();
	        $.ajax({
	            headers: {
	                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	            },
	            url: "{{ route('admin.user.update') }}",
	            type: "POST",
	            data: $("#editForm").serialize(),
	            dataType: 'json',
	            success: function (data) {
	                $('#loading').hide();
	                if(data.status==1){
	                    $("#editModal").modal('hide');
	                    message(data.messages, 'success');
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
            url: "{{ route('admins.destroy','') }}/"+$('#deleteid').val(),
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

</script>
@endpush
