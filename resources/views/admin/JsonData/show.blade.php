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
                                <!-- API Routes Information Card -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5><i class="icofont icofont-code"></i> API Routes for "{{ $jsondata->Name }}"</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><strong>Collection Endpoints:</strong></h6>
                                                <div class="mb-3">
                                                    <label class="text-muted">GET All Data:</label><br>
                                                    <a href="https://halalapp.info/api/jsondata/{{ $jsondata->id }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="icofont icofont-external-link"></i> https://halalapp.info/api/jsondata/{{ $jsondata->id }}
                                                    </a>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted">ADD New Record:</label><br>
                                                    <code class="bg-light p-2 d-block">POST https://halalapp.info/api/addjsondata/{{ $jsondata->id }}</code>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6><strong>Record Edit Endpoints:</strong></h6>
                                                <div class="mb-3">
                                                    <label class="text-muted">GET Record for Edit:</label><br>
                                                    <code class="bg-light p-2 d-block">GET https://halalapp.info/api/editjsondata/{record_id}</code>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted">UPDATE Record:</label><br>
                                                    <code class="bg-light p-2 d-block">PUT https://halalapp.info/api/editjsondata/{record_id}</code>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted">DELETE Record:</label><br>
                                                    <code class="bg-light p-2 d-block">DELETE https://halalapp.info/api/deletejsondata/{record_id}</code>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="alert alert-info">
                                            <i class="icofont icofont-info-circle"></i>
                                            <strong>Note:</strong> Replace <code>{record_id}</code> with actual record IDs from the table below. 
                                            All endpoints require <code>X-API-Key</code> header with your application key.
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ $jsondata->Name }}</h5> <!-- Updated heading -->
                                    </div>
                                    <div class="card-body">
                                        <div style="max-height: auto; overflow-y: auto;">
                                            <table class="table table-bordered" id="showjsonDataTable">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th> <!-- Now displaying record id -->
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
                                                            <td>{{ $row['id'] }}</td>
                                                            <!-- Show record id for each record -->
                                                            @foreach (array_keys($allMetaKeys) as $key)
                                                                <td>{{ isset($row[$key]) ? $row[$key] : '' }}</td>
                                                                <!-- Display the meta values -->
                                                            @endforeach
                                                            <td>
                                                                <button class="btn btn-sm btn-info mr-1"
                                                                    onclick="showEditEndpoints({{ $row['id'] }})">Edit API</button>
                                                                <button class="btn btn-sm btn-danger"
                                                                    onclick="deleteJSON({{ $row['id'] }})">Delete</button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th>ID</th> <!-- Now displaying record id -->
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

    <!-- Edit API Endpoints Modal -->
    <div class="modal fade" id="editApiModal" tabindex="-1" role="dialog" aria-labelledby="editApiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editApiModalLabel">
                        <i class="icofont icofont-code"></i> Edit API Endpoints for Record ID: <span id="recordId"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><strong>Get Record for Editing:</strong></h6>
                            <div class="form-group">
                                <label class="text-muted">Method: GET</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="getEditUrl" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('getEditUrl')">
                                            <i class="icofont icofont-copy"></i>
                                        </button>
                                        <a id="testGetEdit" href="#" target="_blank" class="btn btn-outline-primary">
                                            <i class="icofont icofont-external-link"></i> Test
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><strong>Update Record:</strong></h6>
                            <div class="form-group">
                                <label class="text-muted">Method: PUT</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="putEditUrl" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('putEditUrl')">
                                            <i class="icofont icofont-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <h6><strong>Delete Record:</strong></h6>
                            <div class="form-group">
                                <label class="text-muted">Method: DELETE</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="deleteUrl" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('deleteUrl')">
                                            <i class="icofont icofont-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="icofont icofont-warning"></i>
                        <strong>Required Headers:</strong><br>
                        <code>X-API-Key: your_application_key</code><br>
                        <code>Content-Type: application/json</code> (for PUT requests)
                    </div>
                    <div class="alert alert-info">
                        <i class="icofont icofont-info-circle"></i>
                        <strong>Usage:</strong><br>
                        1. Use GET endpoint to retrieve current record data<br>
                        2. Modify the data as needed<br>
                        3. Use PUT endpoint to update the record with new data
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
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

            function showEditEndpoints(recordId) {
                // Set the record ID in the modal
                $('#recordId').text(recordId);
                
                // Build the API URLs
                var getEditUrl = `https://halalapp.info/api/editjsondata/${recordId}`;
                var putEditUrl = `https://halalapp.info/api/editjsondata/${recordId}`;
                var deleteUrl = `https://halalapp.info/api/deletejsondata/${recordId}`;
                
                // Set the URLs in the input fields
                $('#getEditUrl').val(getEditUrl);
                $('#putEditUrl').val(putEditUrl);
                $('#deleteUrl').val(deleteUrl);
                
                // Set the test link
                $('#testGetEdit').attr('href', getEditUrl);
                
                // Show the modal
                $('#editApiModal').modal('show');
            }

            function copyToClipboard(elementId) {
                var copyText = document.getElementById(elementId);
                copyText.select();
                copyText.setSelectionRange(0, 99999); // For mobile devices
                
                try {
                    document.execCommand('copy');
                    // Show success message
                    var button = event.target.closest('button');
                    var originalHtml = button.innerHTML;
                    button.innerHTML = '<i class="icofont icofont-check"></i>';
                    button.classList.add('btn-success');
                    button.classList.remove('btn-outline-secondary');
                    
                    setTimeout(function() {
                        button.innerHTML = originalHtml;
                        button.classList.remove('btn-success');
                        button.classList.add('btn-outline-secondary');
                    }, 2000);
                } catch (err) {
                    alert('Failed to copy URL. Please copy manually.');
                }
            }
        </script>
    @endpush
@endsection
