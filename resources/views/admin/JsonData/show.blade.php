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
                                    <h4>JSON Data Details</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}">
                                                <i class="icofont icofont-home"></i>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">JSON Data Management</a></li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Details</a></li>
                                    </ul>
                                </div>
                                <div id="successMessage" class="alert alert-success" style="display: none;font-size:20px;">
                                </div>
                            </div>

                            <div class="page-body">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ $jsondata->Name }}</h5> <!-- Updated heading -->
                                    </div>
                                    <div class="card-body">
                                        <div style="max-height: auto; overflow-y: auto;">
                                            <table class="table table-bordered" id="showjsonDataTable">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th> <!-- Now displaying json2_id -->
                                                        @if (count($metaDataRows) > 0)
                                                            @foreach (array_keys($allMetaKeys) as $key)
                                                                <th>{{ ucfirst($key) }}</th>
                                                                <!-- Meta keys as column headers -->
                                                            @endforeach
                                                        @endif
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($metaDataRows as $row)
                                                        <tr>
                                                            <td>{{ $row['json2_id'] }}</td>
                                                            <!-- Show json2_id for each record -->
                                                            @foreach (array_keys($allMetaKeys) as $key)
                                                                <td>{{ isset($row[$key]) ? $row[$key] : '' }}</td>
                                                                <!-- Display the meta values -->
                                                            @endforeach
                                                            <td>
                                                                <button class="btn btn-sm btn-danger"
                                                                    onclick="deleteJSON({{ $row['json2_id'] }})">Delete</button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th>ID</th> <!-- Now displaying json2_id -->
                                                        @if (count($metaDataRows) > 0)
                                                            @foreach (array_keys($allMetaKeys) as $key)
                                                                <th>{{ ucfirst($key) }}</th>
                                                                <!-- Meta keys as column headers -->
                                                            @endforeach
                                                        @endif
                                                        <th>Action</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div> <!-- End of scrolling div -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="styleSelector"></div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
    @endpush

    @push('scripts')
        <script>
            $(document).ready(function() {
                try {
                    $('#showjsonDataTable').DataTable({
                        pageLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        searching: true,
                        paging: true,
                    });
                } catch (error) {
                    console.error('DataTables initialization error:', error);
                }
            });
        </script>

        <script>
            function deleteJSON(id) {
                // console.log(id);
                if (confirm("Do You Really want to Delete This JSON Record ?")) {
                    $.ajax({
                        url: '/admin/delete-jsondata/' + id,
                        type: 'GET',
                        success: function(response) {
                            if (response.success) {
                                // Reload the DataTablepagination
                                $('#successMessage').text('JSON Data Deleted').show();

                                setTimeout(() => {
                                    $('#successMessage').hide();
                                }, 3000);
                                window.location.reload();

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
