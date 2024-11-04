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
								<h4>Notification Management</h4>
							</div>
							<div class="page-header-breadcrumb">
								<ul class="breadcrumb-title">
									<li class="breadcrumb-item">
										<a href="{{ route('admin.dashboard') }}">
											<i class="icofont icofont-home"></i>
										</a>
									</li>
									<li class="breadcrumb-item"><a href="javascript:;">Notification Management</a>
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
									<h5>Notification Management</h5>
								</div>
								<div class="card-block">
									<div class="dt-responsive table-responsive">
										<table id="users-table" class="table table-striped table-bordered nowrap">
											<thead>
												<tr>
													<th>ID</th>
													<th>Message</th>
													<th>Actions</th>
												</tr>
											</thead>
											<tbody>
												
											</tbody>
											<tfoot>
												<tr>
													<th>ID</th>
													<th>Message</th>
													<th>Actions</th>
												</tr>
											</tfoot>
										</table>
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
<div class="modal fade in" id="delete_modal" role="dialog" tabindex="-1" aria-labelledby="delete_modal" aria-hidden="true" >
  <div class="modal-dialog modal-dialog-centered" style="width: 400px;">
    <div class="modal-content">
      <!--Modal header-->
      <div class="modal-header">
        <h4 class="modal-title">Confirm Delete</h4>
        <button type="button" class="close" data-dismiss="modal"><i class="fa fa-times-circle-o"></i></button>
      </div>
      <!--Modal body-->
      <div class="modal-body">
        <p>Are you sure you want to delete this notification?</p>
        <div class="text-right">
          <form method="post" role="form" id="deleteForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" id="deleteid">
            <button data-dismiss="modal" class="btn btn-default btn-sm" type="button" id="modal_close">Close</button>
            <button type="button" class="btn btn-danger btn-sm delete_btn">Delete</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@push('scripts')

<script type="text/javascript">

	$(function () {
            oTable = $('#users-table').DataTable({
                "processing": true,
                "serverSide": true,
                ajax: "{{ route("show.all.notification") }}",
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                    {data: 'data', name: 'data', orderable: false},
                    {data: 'action', name: 'action', orderable: false, searchable: false},
                ],
            });
        });

	function deleteMainCategoryModel($id)
	{
		$('#deleteid').val($id);
		$('#delete_modal').modal('show');
	}

	$(document).on('click','.delete_btn', function() {
        $.ajax({
            type: "get",
            url: "{{ route('notification.delete','') }}/"+$('#deleteid').val(),
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