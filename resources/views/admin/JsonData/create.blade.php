@extends('admin.layouts.app')

@section('content')
    <div class="pcoded-main-container">
        @include('admin.include.sidebar')
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="page-header">
                                <div class="page-header-title">
                                    <h4>JSON Data Management</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}">
                                                <i class="icofont icofont-home"></i>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">JSON Data Management</a></li>
                                    </ul>
                                </div>
                                <div id="successMessage" class="alert alert-success" style="display: none;font-size:20px;">
                                </div>
                            </div>


                            <div class="page-body">
                                <!-- Form start -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Create JSON Data</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="jsonDataForm" method="POST" action="{{ route('jsonAdd.index') }}">
                                            @csrf
                                            <div class="form-group">
                                                <label for="name">Name</label>
                                                <input type="text" id="name" name="name" class="form-control"
                                                    placeholder="Enter Name" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="description">Description</label>
                                                <input type="text" id="description" name="description"
                                                    class="form-control" placeholder="Enter Description" required>
                                            </div>
                                            <div class="text-right">
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <!-- DataTable -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5>JSON Data</h5>
                                    </div>
                                    <div class="card-body">
                                        <table id="jsonDataTable" class="display" style="width:100%">
                                            <thead>
                                                <tr align="center">
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Description</th>
                                                    <th>API Routes</th> <!-- New Column for Routes -->
                                                    <th colspan="2">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="styleSelector"></div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
    @endpush

    @push('scripts')
        <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
        <script>
            $(document).ready(function() {
                var table = $('#jsonDataTable').DataTable({
                    ajax: {
                        url: '{{ route('jsonData.index') }}', // Your route to fetch data
                        dataSrc: ''
                    },
                    columns: [{
                            data: 'id'
                        },
                        {
                            data: 'Name'
                        },
                        {
                            data: 'Description'
                        },
                        {
                            data: null, // Custom data rendering for the API routes column
                            render: function(data, type, row) {
                                if (row && row.id) { // Check if data exists and row.id is valid
                                    var getUrl = `https://halalapp.info/api/jsondata/${row.id}`;
                                    var postUrl = `https://halalapp.info/api/addjsondata/${row.id}`;
                                    return `
                                <strong>GET Data endpoint(api):</strong> <a href="${getUrl}" target="_blank">${getUrl}</a><br>
                                <strong>SAVE Data endpoint(api):</strong> <a href="${postUrl}">${postUrl}</a>
                            `;
                                } else {
                                    return 'No API routes available'; // Message when no data
                                }
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<a href="/admin/jsondata/${data.id}" class="btn btn-sm btn-primary" title="Show: ${data.id}">Show Data</a>

                                <button onclick="confirmDelete(${data.id})" class="btn btn-sm btn-danger" title="Delete: ${data.id}">Delete All JSON Data</button>
                                `;
                            },
                        },


                    ]
                });

                $('#jsonDataForm').on('submit', function(e) {
                    e.preventDefault();
                    var formData = $(this).serialize();

                    $.ajax({
                        type: "POST",
                        url: "{{ route('jsonAdd.index') }}",
                        data: formData,
                        success: function(response) {
                            $('#successMessage').text('Data added successfully').show();
                            setTimeout(() => {
                                $('#successMessage').hide();
                            }, 3000);

                            $('#jsonDataForm')[0].reset();
                            table.ajax.reload(); // Reload DataTable data
                        },
                        error: function(response) {
                            alert('Error: ' + response.responseText);
                        }
                    });
                });
            });
        </script>

        <script>
            function confirmDelete(id) {
                if (confirm("Do You Really want to Delete All JSON Data")) {
                    $.ajax({
                        url: '/admin/delete-all-jsondata/' + id,
                        type: 'GET',
                        success: function(response) {
                            if (response.success) {
                                $("#jsonDataTable").DataTable().ajax.reload();
                                $('#successMessage').text('JSON Data Deleted').show();
                                setTimeout(() => {
                                    $('#successMessage').hide();
                                }, 3000);
                            } else {
                                alert('Deletion failed. Please try again.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
                            alert('An error occurred while deleting record.');
                        }
                    })
                }
            }
        </script>
    @endpush
@endsection
