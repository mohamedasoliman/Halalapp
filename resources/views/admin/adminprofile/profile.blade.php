@extends('admin.layouts.app')
@section('content')
@include('admin.include.sidebar')

<div class="pcoded-main-container">
	<div class="pcoded-wrapper">
		<div class="pcoded-content">
			<div class="pcoded-inner-content">
				<!-- Main body start -->
				<div class="main-body user-profile">
					<div class="page-wrapper">
						<!-- Page-header start -->
						<div class="page-header">
							<div class="page-header-title">
								<h4>{{ getLoginUserRoleName() }} Profile</h4>
							</div>
							<div class="page-header-breadcrumb">
								<ul class="breadcrumb-title">
									<li class="breadcrumb-item">
										<a href="{{ route('admin.dashboard') }}">
											<i class="icofont icofont-home"></i>
										</a>
									</li>
									<li class="breadcrumb-item">
										<a href="{{ route('admin.users') }}">
											{{ getLoginUserRoleName() }} List
										</a>
									</li>
									<li class="breadcrumb-item"><a href="javascript:;">{{ getLoginUserRoleName() }} Profile</a>
									</li>
								</ul>
							</div>
						</div>
						<!-- Page-header end -->
						<!-- Page-body start -->
						<div class="page-body">
							<!--profile cover start-->
							<div class="row">
								<div class="col-lg-12">
									<div class="cover-profile">
										<div class="profile-bg-img">
											<img class="profile-bg-img img-fluid" src="{{asset('assets/images/user-profile/bg-img1.jpg') }}" alt="bg-img">
											<div class="card-block user-info">
												<div class="col-md-12">
													<div class="media-left">
														<a href="javascript:;" class="profile-image">
															@if(!empty($user->admin_image))
															<img class="user-img img-circle for_update_src" src="{{asset('assets/frontend/profiles/'.$user->admin_image.'')}}" alt="user-img" width='136' height='136'>
															@else
															<img class="user-img img-circle for_update_src" src="{{asset('assets/images/avatar-1.png')}}" alt="user-img">
															@endif
														</a>
													</div>
													<div class="media-body row">
														<div class="col-lg-12">
															<div class="user-title">
																<h2>{{  $user->name }}</h2>
																<span class="text-white">{{ getLoginUserRoleName() }} <!--User--></span>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<!--profile cover end-->
							<div class="row">
								<div class="col-lg-12">
									<!-- tab header start -->
									<div class="tab-header">
										<ul class="nav nav-tabs md-tabs tab-timeline" role="tablist" id="mytab">
											<li class="nav-item">
												<a class="nav-link active" data-toggle="tab" href="#personal" role="tab">User Info</a>
												<div class="slide"></div>
											</li>
											<li class="nav-item">
												<a class="nav-link" data-toggle="tab" href="#binfo" role="tab">Change Password</a>
												<div class="slide"></div>
											</li>
											<li class="nav-item">
												<a class="nav-link" data-toggle="tab" href="#contacts" role="tab">{{ getLoginUserRoleName() }} Profile Image</a>
												<div class="slide"></div>
											</li>
											<li class="nav-item">
												<a class="nav-link" data-toggle="tab" href="#review" role="tab">Review</a>
												<div class="slide"></div>
											</li>
										</ul>
									</div>
									<!-- tab header end -->
									<!-- tab content start -->
									<div class="tab-content">
										<!-- tab panel personal start -->
										<div class="tab-pane active" id="personal" role="tabpanel">
											<!-- personal card start -->
											<div class="card">
												@include('admin.messages')
												<div class="card-header">
													<h5 class="card-header-text">About Me</h5>
												</div>
												<div class="card-block">
													<div class="view-info">
														<div class="row">
															<div class="col-lg-12">
																<div class="general-info">
																	<div class="row">
																		<div class="col-lg-12 col-xl-6">
																			<table class="table m-0">
                                                                                <tbody>
                                                                                @if(userRoleCheck([2]))
                                                                                    <tr>
                                                                                        <th scope="row">Shop Name</th>
                                                                                        <td>
                                                                                            {{ getShop(Auth()->user()->shop_id)->shop_name }}
                                                                                        </td>
                                                                                    </tr>
                                                                                @endif
                                                                                <tr>
                                                                                    <th scope="row">Role</th>
                                                                                    <td>
                                                                                        {{ str_replace('_',' ',ucwords($user->getRole->name,'_')) }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <th scope="row">Status</th>
                                                                                    <td>
                                                                                        @if($user->status == 1)
                                                                                            <label
                                                                                                class="label label-info">Active</label>
                                                                                        @else
                                                                                            <label
                                                                                                class="label label-danger">Deactive</label>
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                                </tbody>
																			</table>
																		</div>
																		<!-- end of table col-lg-6 -->
																		<div class="col-lg-12 col-xl-6">
																			<table class="table">
																				<tbody>
                                                                                    <tr>
                                                                                        <th scope="row">User Name</th>
                                                                                        <td>{{  $user->name }}</td>
                                                                                    </tr>
																					<tr>
																						<th scope="row">Email</th>
																						<td>
                                                                                            <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                                                                                        </td>
                                                                                    </tr>
																				</tbody>
																			</table>
																		</div>
																		<!-- end of table col-lg-6 -->
																	</div>
																	<!-- end of row -->
																</div>
																<!-- end of general info -->
															</div>
															<!-- end of col-lg-12 -->
														</div>
														<!-- end of row -->
													</div>
												</div>
												<!-- end of card-block -->
											</div>

											<!-- personal card end-->
										</div>
										<!-- tab pane personal end -->
										<!-- tab pane info start -->
										<div class="tab-pane" id="binfo" role="tabpanel">
											<!-- info card start -->

											<div class="card">
												<div class="card-header">
													<h5>Change Password</h5>
													<div class="card-header-right">
														<i class="icofont icofont-rounded-down"></i>
														<i class="icofont icofont-refresh"></i>
														<i class="icofont icofont-close-circled"></i>
													</div>
												</div>
												<div class="card-block">

													<form action="{{ route('user.changepassword',$user->id) }}" method="POST" id="changepasswordform" novalidate="">
														{{csrf_field()}}
														<input type="hidden" name="viewchangeuserid" id="viewchangeuserid" value="{{ $user->id }}">
														<div class="form-group row has-error">
															<label class="col-sm-2 col-form-label">New Password</label>
															<div class="col-sm-10">
																<input type="password" class="form-control" id="password" name="password" placeholder="New Password" value="{{ old('password') }}">
															</div>
														</div>
														<!-- end of table col-lg-6 -->
														<div class="form-group row has-error">
															<label class="col-sm-2 col-form-label">Confirm Password</label>
															<div class="col-sm-10">
																<input type="password" class="form-control" name="confirmpassword" id="confirmpassword" placeholder="Confirm Password" value="{{ old('confirmpassword') }}">
															</div>
														</div>
														<!-- end of table col-lg-6 -->
														<!-- end of row -->
														<div class="text-center">
															<input type="submit" id="changepasswordid" class="btn btn-primary waves-effect waves-light m-r-20" value="Save">
															<a href="{{ route('admin.users') }}" id="edit-cancel" class="btn btn-default waves-effect">Cancel</a>
														</div>
													</form>

												</div>
											</div>
											<!-- end of edit info -->
											<!-- info card end -->
										</div>
										<!-- tab pane info end -->
										<!-- tab pane contact start -->
										<div class="tab-pane" id="contacts" role="tabpanel">
											<div class="card">
												<div class="card-header">
													<h5>Upload {{ getLoginUserRoleName() }} Image</h5>
													<div class="card-header-right">
														<i class="icofont icofont-rounded-down"></i>
														<i class="icofont icofont-refresh"></i>
														<i class="icofont icofont-close-circled"></i>
													</div>
												</div>
												<div class="card-block">

													<form method="post" id="userImageForm" enctype="multipart/form-data">
														{{csrf_field()}}
														<input type="hidden" id="viewuserid" name="viewuserid" value="{{ $user->id }}">
														<div class="form-group row has-error">
															<label class="col-sm-2 col-form-label">
																<img style="height: 100px; width: 100px; display: none;" id="display_image" class="" src="">
																{{-- <img class="" src="{{asset('assets/frontend/profiles/'.$user->admin_image.'')}}" height="100px" width="120px" alt="User-Profile-Image"> --}}
															</label>

															<div class="col-sm-10">
																<input type="file" class="form-control" id="admin_image" name="admin_image" >
															</div>
														</div>
														<!-- end of row -->
														<div class="text-center">
															<input type="submit" id="changeuserimage" class="btn btn-primary waves-effect waves-light m-r-20" value="Upload">
															<a href="{{ route('users.index') }}" id="edit-cancel" class="btn btn-default waves-effect">Cancel</a>
														</div>
													</form>

												</div>
											</div>
										</div>
										<!-- tab pane contact end -->
										<div class="tab-pane" id="review" role="tabpanel">

										</div>
									</div>
									<!-- tab content end -->
								</div>
							</div>
						</div>
						<!-- Page-body end -->
					</div>
				</div>
				<!-- Main body end -->
				<div id="styleSelector">
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
@push('scripts')
<!-- Custom js -->
<script type="text/javascript" src="{{asset('assets/pages/advance-elements/moment-with-locales.min.js')}}"></script>
<script src="{{asset('assets/pages/user-profile.js')}}"></script>
<script src="{{asset('assets/js/pcoded.min.js')}}"></script>
{{-- <script src="{{asset('assets/js/demo-12.js')}}"></script> --}}

<script>
	$('#changepasswordid').click(function() {
		$('#changepasswordform').validate({
			rules: {
				password: {
					required: true,
					minlength:8,
				},
				confirmpassword: {
					required: true,
					equalTo: "#password",
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
				password:{
					required: 'Please Enter Your Password',
					minlength: 'Password Length must be 8 Character',
				},
				confirmpassword:{
					required: 'Please Confirm Your Password',
				},
			},
			submitHandler: function (form) {
                $('#loading').show();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: "{{ route('admin.user.changepassword','') }}/"+$('#viewchangeuserid').val(),
                    type: "POST",
                    data: new FormData(form),
                    dataType: 'json',
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (data) {
                        $('#loading').hide();
                        if (data.status) {
                            message(data.message, 'success');
                            $('#password').val('');
                            $('#confirmpassword').val('');
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
	});

$('#changeuserimage').click(function() {
	$('#userImageForm').validate({
            rules: {
                admin_image: {
                    required: true,
                    extension: "jpeg|png|jpg"
                }
            },
            errorPlacement: function (error, element) {
                if (element.is(":radio")) {
                    var name = element.attr('name');
                    error.insertAfter("#" + name + "_radio-error");
                } else {
                    error.appendTo(element.parent());
                }
            },
            messages: {
                admin_image: {
                    required: 'Please select user image',
                }
            },
            submitHandler: function (form) {
                $('#loading').show();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: "{{ route('admin.profilesimage.update','') }}/"+$('#viewuserid').val(),
                    type: "POST",
                    data: new FormData(form),
                    dataType: 'json',
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (data) {
                        $('#loading').hide();
                        if (data.status) {
                            message(data.message, 'success');
                            $('.for_update_src').attr('src','');
                            var urls = user_base_url + data.url;
                            $('.for_update_src').attr('src', urls);
                            $('#display_image').css('display','none');
                            $('#admin_image').attr('value', '');
                            $('#admin_image').val("");
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

    $("#admin_image").change(function () {
        $("#display_image").css("display", "block");
        readIMG(this);
    });
</script>
@endpush
