@extends('admin.layouts.app')

@section('content')

    <div class="pcoded-main-container">
        @include('admin.include.sidebar')
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- Main-body start -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- Page-header start -->
                            <div class="page-header">
                                <div class="page-header-title">
                                    <h4>Masjid Management</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}">
                                                <i class="icofont icofont-home"></i>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Masjid Management</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div id="successMessage" class="alert alert-success" style="display: none;font-size:30px;"></div>

                            <!-- Page-header end -->
                            <!-- Page-body start -->
                            <div class="page-body">
                                <!-- Table header styling table start -->
                                @include('admin.messages')
                                <!-- Users Management table start -->
                                <div class="card">
                                    <div class="card-header table-card-header">
                                        <h5>Masjid Management</h5>
                                        <div class="float-right">

                                            <a href="javascript:;" class="btn btn-primary" id="add-masjid" data-bs-toggle="modal" data-bs-target="#addMasjidModal"><i class="fa fa-plus" ></i> Add Masjid</a>
                                            <a href="javascript:;" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" id="add-main-category"><i class="fa fa-plus"></i> Import CSV</a>
                                            <button type="button" id="deleteAllRecords" class="btn btn-danger" onclick="deleteall()">Delete All Records</button>
                                        </div>
                                    </div>
                                    <div class="card-block">
                                        <div class="dt-responsive table-responsive">
                                            <!-- DataTable for displaying records -->
                                            <table id="masjid-table" class="table table-striped table-bordered nowrap">

                                                    <thead>
                                                      <tr>
                                                        <th >id</th>
                                                        <th >Masjid Name</th>
                                                        <th >Address</th>
                                                        <th >Area id</th>
                                                        <th >Area Name</th>
                                                        <th >Website</th>
                                                        <th >Fajar</th>
                                                        <th >Duhur</th>
                                                        <th >Asr</th>
                                                        <th >Maghrib</th>
                                                        <th >ishaa</th>
                                                        <th >Jumaa</th>
                                                        <th >Longitude</th>
                                                        <th >Latitude</th>
                                                        <th >Actions</th>
                                                      </tr>
                                                    </thead>
                                                    <tbody>

                                                    </tbody>

                                                <tfoot>
                                                    <tr>
                                                        <th >id</th>
                                                        <th >Masjid Name</th>
                                                        <th >Address</th>
                                                        <th >Area id</th>
                                                        <th >Area Name</th>
                                                        <th >Website</th>
                                                        <th >Fajar</th>
                                                        <th >Duhur</th>
                                                        <th >Asr</th>
                                                        <th >Maghrib</th>
                                                        <th >ishaa</th>
                                                        <th >Jumaa</th>
                                                        <th >Longitude</th>
                                                        <th >Latitude</th>
                                                        <th >Actions</th>
                                                      </tr>
                                                </tfoot>
                                            </table>
                                            <!-- Modal for importing CSV data -->
                                            <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exampleModalLabel">Import CSV Data</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <form action="{{ route('import.csv') }}" method="POST" enctype="multipart/form-data">
                                                                        @csrf
                                                                        <input type="file" name="csv_file" accept=".csv" required>
                                                                        <button  type="submit" class="btn btn-primary">Import CSV</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Modal for adding a new Masjid -->

                                        </div>
                                    </div>
                                </div>
                                <!-- Users Management end -->
                            </div>
                            <!-- Page-body end -->
                        </div>
                    </div>
                    <!-- Main-body start -->
                    <div id="styleSelector"></div>
                </div>
            </div>
        </div>
    </div>
    {{-- this file includes the add masjid and update modal forms --}}
    {{-- this file also contains their respective ajax functions --}}
    @include('admin.masjid.masjidmodel')
@endsection

<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    //to show data using datatables with jquery
    $(document).ready(function() {
        $('#masjid-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('masjid.index') }}",
            columns: [
                { data: 'id', name: 'id' },
                { data: 'Masjid_name', name: 'Masjid_name' },
                { data: 'Address', name: 'Address' },
                { data: 'Area_id', name: 'Area_id' },
                { data: 'Area_name', name: 'Area_name' },
                { data: 'Website', name: 'Website' },
                { data: 'Fajar', name: 'Fajar' },
                { data: 'Duhur', name: 'Duhur' },
                { data: 'Asr', name: 'Asr' },
                { data: 'Maghrib', name: 'Maghrib' },
                { data: 'Ishaa', name: 'Ishaa' },
                { data: 'Jumaa', name: 'Jumaa' },
                { data: 'Longitude', name: 'Longitude' },
                { data: 'Latitude', name: 'Latitude' },
                {data: null, // This column doesn't have specific data, so use null
                render: function (data, type, row) {
                    // Create custom HTML for the "Actions" column
                    return `
                        <button class="btn btn-sm btn-primary" title="Edit ID: ${data.id}" onclick="editRecord(${data.id})" id="edit-btn">Edit</button>

                        <button class="btn btn-sm btn-danger" title="delete ID: ${data.id}" onclick="deleteRecord(${data.id})" data-dlt-id='${data.id}'>Delete</button>
                    `;
        },
    },
            ],

            pageLength: 10, // Number of rows to display per page
            lengthMenu: [10, 25, 50, 100], // Dropdown for selecting page length

        });
    });

    //to show modal forms
    $("#add-masjid").on('click',function(){
        // console.log('button clicked');
        $('#addMasjidModal').modal('show');
    });

    $(".close").on('click',function(){
        $('#addMasjidModal').modal('hide');
    });

    $("#edit-btn").on('click',function(){
        console.log('clicked');
    });

    //to delete all records using onclick function on delete all record button
    function deleteall(){
            if (confirm("Are you sure to delete all records?")) {

                $.ajax({
                    url : '{{route('masjid.deleteall')}}',
                    type : 'GET',
                    data: {
                        _token: '{{ csrf_token() }}',
                    },
                    success: function (response) {
                        //if returns true
                    if (response.success) {
                        $('#masjid-table').DataTable().ajax.reload();
                        $('#successMessage').html('All Records Dele ted successfully.').show();
                        setTimeout(function () {
                        $('#successMessage').fadeOut('fast');
                        }, 5000);
                    } else {
                        alert('Deletion failed. Please try again.');
                    }
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        alert('An error occurred while deleting records.');
                    }
                });
            }
        }

        //to delete only one record at a time using onclick function on the delete button in actions field

        function deleteRecord(id)
        {
            // console.log(id);

            if (confirm("Do you want to delete this record?")) {
                // console.log(id);
                $.ajax({
                    url : '/admin/masjid/delete/' + id,
                    type : 'GET',
                    success: function (response) {
                        //if returns true
                    if (response.success) {
                        $('#masjid-table').DataTable().ajax.reload();
                        $('#successMessage').html('Record Deleted successfully.').show();
                        setTimeout(function () {
                        $('#successMessage').fadeOut('fast');
                        }, 5000);
                    } else {
                        alert('Deletion failed. Please try again.');
                    }
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        alert('An error occurred while deleting record.');
                    }
                });
            }
        }

        function editRecord(id){

            $.ajax({
                url : '/admin/masjid/edit/' + id,
                type : 'GET',
                success : function(response){
                    if (response.success) {
                        $('#editMasjidId').val(response.data.id);
                        $('#editMasjidName').val(response.data.Masjid_name);
                        $('#editAddress').val(response.data.Address);
                        $('#editAreaId').val(response.data.Area_id);
                        $('#editAreaName').val(response.data.Area_name);
                        $('#editWebsite').val(response.data.Website);
                        $('#editFajar').val(response.data.Fajar);
                        $('#editDuhur').val(response.data.Duhur);
                        $('#editAsr').val(response.data.Asr);
                        $('#editMaghrib').val(response.data.Maghrib);
                        $('#editIshaa').val(response.data.Ishaa);
                        $('#editJumaa').val(response.data.Jumaa);
                        $('#editLongitude').val(response.data.Longitude);
                        $('#editLatitude').val(response.data.Latitude);
                        $("#editMasjidModal").modal('show');
                    }
                },
                error: function(xhr, status, error){
                    console.error(xhr.responseText);
                }
            });
        }
</script>
