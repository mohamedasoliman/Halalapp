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
                                    <h4>Resturant Management</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}">
                                                <i class="icofont icofont-home"></i>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Resturant Management</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div id="successMessage" class="alert alert-success" style="display: none;font-size:20px;"></div>

                            <!-- Page-header end -->
                            <!-- Page-body start -->
                            <div class="page-body">
                                <!-- Table header styling table start -->
                                @include('admin.messages')
                                <!-- Users Management table start -->
                                <div class="card">
                                    <div class="card-header table-card-header">
                                        <h5>Resturant Management</h5>
                                        <div class="float-right">

                                            <a href="javascript:;" class="btn btn-primary" id="add-resturant" data-bs-toggle="modal" data-bs-target="#addresturantModal"><i class="fa fa-plus" ></i> Add Resturant</a>
                                            <a href="javascript:;" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal" id="add-main-category"><i class="fa fa-plus"></i> Import CSV</a>
                                            <button type="button" id="deleteAllRecords" class="btn btn-danger" onclick="deleteall()">Delete All Records</button>
                                        </div>
                                    </div>
                                    <div class="card-block">
                                        <div class="dt-responsive table-responsive">
                                            <!-- DataTable for displaying records -->
                                            <table id="resturant-table" class="table table-striped table-bordered nowrap">

                                                    <thead>
                                                      <tr>
                                                        <th >id</th>
                                                        <th >Resturant Name</th>
                                                        <th >Description</th>
                                                        <th >Website</th>
                                                        <th >Logo</th>
                                                        <th >Image 1</th>
                                                        <th >Image 2</th>
                                                        <th >Image 3</th>
                                                        <th >Image 4</th>
                                                        <th >Image 5</th>
                                                        <th >Image 6</th>
                                                        <th >Phone</th>
                                                        <th >Address</th>
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
                                                        <th >Resturant Name</th>
                                                        <th >Description</th>
                                                        <th >Website</th>
                                                        <th >Logo</th>
                                                        <th >Image 1</th>
                                                        <th >Image 2</th>
                                                        <th >Image 3</th>
                                                        <th >Image 4</th>
                                                        <th >Image 5</th>
                                                        <th >Image 6</th>
                                                        <th >Phone</th>
                                                        <th >Address</th>
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
                                                                    <form  method="POST" enctype="multipart/form-data" action="{{route('resturant.csv')}}">
                                                                        @csrf
                                                                        <input type="file" name="csv_file" accept=".csv">
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

                                            <!-- Modal for adding a new Resturant -->

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
    {{-- this file includes the add resturant and update modal forms --}}
    {{-- this file also contains their respective ajax functions --}}
    @include('admin.resturant.resturantmodel')
@endsection

<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>


<script>
    $(document).ready(function () {
    $("#resturant-table").DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('resturant.index') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'Resturant_name', name: 'Resturant_name' },
            { data: 'Description', name: 'Description' },
            { data: 'Website', name: 'Website' },
            {
                data: 'Logo',
                name: 'Logo',
                render: function (data, type, row) {
                    // Assuming 'Logo' contains the image filename
                    var imageUrl = '{{ asset("public_html/upload/resturant") }}' + '/' + data; // Adjust the URL path to your image location
                    return `<img src="${imageUrl}" alt="Logo" width="60px" height="60px" style="border-radius:50%;">`;
                }
            },
            {
                data: 'Image_1',
                name: 'Image_1',
                render: function (data, type, row) {
                    var imageUrl = '{{ asset("public_html/upload/resturant") }}' + '/' + data;
                    return `<img src="${imageUrl}" alt="Image_1" width="60px" height="60px" style="border-radius:50%;">`;                }
            },
            {
                data: 'Image_2',
                name: 'Image_2',
                render: function (data, type, row) {

                    var imageUrl = '{{ asset("public_html/upload/resturant") }}' + '/' + data; // Adjust the URL path to your image location
                    return `<img src="${imageUrl}" alt="Image_2" width="60px" height="60px" style="border-radius:50%;">`;
                }
            },
            {
                data: 'Image_3',
                name: 'Image_3',
                render: function (data, type, row) {

                    var imageUrl = '{{ asset("public_html/upload/resturant") }}' + '/' + data; // Adjust the URL path to your image location
                    return `<img src="${imageUrl}" alt="Image_3" width="60px" height="60px" style="border-radius:50%;">`;
                }
            },
            {
                data: 'Image_4',
                name: 'Image_4',
                render: function (data, type, row) {

                    var imageUrl = '{{ asset("public_html/upload/resturant") }}' + '/' + data; // Adjust the URL path to your image location
                    return `<img src="${imageUrl}" alt="Image_4" width="60px" height="60px" style="border-radius:50%;">`;
                }
            },
            {
                data: 'Image_5',
                name: 'Image_5',
                render: function (data, type, row) {

                    var imageUrl = '{{ asset("public_html/upload/resturant") }}' + '/' + data; // Adjust the URL path to your image location
                    return `<img src="${imageUrl}" alt="Image_5" width="60px" height="60px" style="border-radius:50%;">`;
                }
            },
            {
                data: 'Image_6',
                name: 'Image_6',
                render: function (data, type, row) {

                    var imageUrl = '{{ asset("public_html/upload/resturant") }}' + '/' + data; // Adjust the URL path to your image location
                    return `<img src="${imageUrl}" alt="Image_6" width="60px" height="60px" style="border-radius:50%;">`;
                }
            },

            { data: 'Phone', name: 'Phone' },
            { data: 'Address', name: 'Address' },
            { data: 'Longitude', name: 'Longitude' },
            { data: 'Latitude', name: 'Latitude' },
            {
                data: null,
                render: function (data, type, row) {
                    return `
                        <button class="btn btn-sm btn-primary" id="edit-btn" title="id:${data.id}" onclick="editRecord(${data.id})">Edit</button>

                        <button class="btn btn-sm btn-danger" id="dlt-btn" title="id:${data.id}" onclick="deleteRecord(${data.id})">Delete</button>
                    `;
                },
            },
        ],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });


        $("#add-resturant").on('click',function(){
            $("#addresturantModal").modal('show');
        });

        $(".close").on('click',function(){
        $('#addresturantModal').modal('hide');
        });

        // $("#edit-btn").on('click',function(){
        //     $("#addresturantModal").modal('show');
        // });
    });

    function deleteall(){
        if (confirm("Are you sure to delete all records?")) {
            $.ajax({
            url : "{{route('resturant.deleteall')}}",
            type: "GET",
            success: function(response){
                if(response.success){
                    $("#resturant-table").DataTable().ajax.reload();
                    $("#successMessage").html("All records Deleted").show();
                    setTimeout(function() {
                    $("#successMessage").fadeOut('fast');
                    }, 5000);
                }else{
                    alert("An error occured during deleting records");
                }
            },
            error: function(xhr,status,error){
                console.log(xhr.responseText);
                alert("Something went wrong");
            }
        });
        }
    }

    //to delete specific records
    function deleteRecord(id)
        {
            // console.log(id);

            if (confirm("Do you want to delete this record?")) {
                // console.log(id);
                $.ajax({
                    url : '/admin/resturant/delete/' + id,
                    type : 'GET',
                    success: function (response) {
                        //if returns true
                    if (response.success) {
                        $('#resturant-table').DataTable().ajax.reload();
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
                url : '/admin/resturant/edit/' + id,
                type : 'GET',
                success : function(response){
                    if (response.success) {
                        $('#editResturantId').val(response.data.id);
                        $('#editresturantName').val(response.data.Resturant_name);
                        $('#editDescription').val(response.data.Description);
                        $('#editWebsite').val(response.data.Website);
                        $('#editPhone').val(response.data.Phone);
                        $('#editAddress').val(response.data.Address);
                        $('#editLongitude').val(response.data.Longitude);
                        $('#editLatitude').val(response.data.Latitude);

                        $("#editResturantModal").modal('show');
                    }
                },
                error: function(xhr, status, error){
                    console.error(xhr.responseText);
                }
            });
        }

</script>

