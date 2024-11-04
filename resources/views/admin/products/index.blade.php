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
                                    <h4>Products Management</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}">
                                                <i class="icofont icofont-home"></i>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Products Management</a>
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
                                        <div class="float-right">
                                            <div class="form-group" style="display:inline; margin-inline: 50px">
                                                <!-- <label for="categoryFilter">Select Category:</label> -->
                                                <select name="category" id="categoryFilter">
                                                    <option value="">Select a Category</option>
                                                    @foreach ($categories as $category)
                                                        <option value="{{ $category }}">{{ $category }}</option>
                                                    @endforeach
                                                </select>

                                                <button type="button" id="deleteByCategory" class="btn btn-warning">
                                                    Delete by Category
                                                </button>
                                            </div>
                                            <a href="javascript:;" class="btn btn-primary" id="add-main-category"><i
                                                    class="fa fa-plus"></i>Add Product</a>
                                            <a href="javascript:;" class="btn btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#exampleModal" id="add-main-category"><i
                                                    class="fa fa-plus"></i>Import CSV</a>
                                            <button type="button" id="deleteAllProducts" class="btn btn-danger">Delete All
                                                Products</button>
                                        </div>
                                    </div>
                                    <div class="card-block">
                                        <div class="dt-responsive table-responsive">
                                            <table id="users-table" class="table table-striped table-bordered nowrap">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Product Image</th>
                                                        <th>Product Name</th>
                                                        <th>Status</th>
                                                        <th>Halal Status</th>
                                                        <th>Barcode</th>
                                                        <th>Certification Status</th>
                                                        <th>Category</th>
                                                        <th>Notes</th>
                                                        <th>Ingredients</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>

                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Product Image</th>
                                                        <th>Product Name</th>
                                                        <th>Status</th>
                                                        <th>Halal Status</th>
                                                        <th>Barcode</th>
                                                        <th>Certification Status</th>
                                                        <th>Category</th>
                                                        <th>Notes</th>
                                                        <th>Ingredients</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                            <!-- Modal -->
                                            <div class="modal fade" id="exampleModal" tabindex="-1"
                                                aria-labelledby="exampleModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exampleModalLabel">Import CSV Data
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <form action="{{route('import.process')}}" method="POST"
                                                                        enctype="multipart/form-data">
                                                                        @csrf
                                                                        <input type="file" name="csv_file"
                                                                            accept=".csv" required>
                                                                        <button type="submit"
                                                                            class="btn btn-primary">Import CSV</button>
                                                                    </form>

                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Close</button>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
    @include('admin.products.Productmodal')
@endsection

@push('scripts')
    <script type="text/javascript">
        $(function() {
            oTable = $('#users-table').DataTable({
                "processing": true,
                "serverSide": true,
                ajax: "{{ route('product.index') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex'
                    },
                    {
                        data: 'product_image',
                        name: 'product_image',
                        orderable: false
                    },
                    {
                        data: 'product_name',
                        name: 'product_name',
                        orderable: false
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: true,
                        searchable: false
                    },
                    {
                        data: 'halal_status',
                        name: 'halal_status',
                        orderable: true,
                        searchable: false
                    },
                    {
                        data: 'Barcode',
                        name: 'Barcode',
                        orderable: false
                    },
                    {
                        data: 'Certification_Status',
                        name: 'Certification_Status',
                        orderable: false
                    },
                    {
                        data: 'category',
                        name: 'category',
                        orderable: false
                    },
                    {
                        data: 'notes',
                        name: 'notes',
                        orderable: false
                    },
                    {
                        data: 'ingredient',
                        name: 'ingredient',
                        orderable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    },
                ],
            });

            $(document).on('click', '.status-update', function() {
                $.ajax({
                    type: "get",
                    url: "{{ route('product.status.update', '') }}/" + $(this).data('id'),
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
            $('.register-form-errors').html('');
            $('#main-category-modal').modal('show');
        });

        $(document).on('click', '.close, .btn-secondary', function() {
            $('#main-category-modal').modal('hide');
        });




        $('#halal_status1').click(function(event) {
            $('#halal_status1').val('0');
        });
        $('#halal_status2').click(function(event) {
            $('#halal_status2').val('1');
        });
        $('#halal_status3').click(function(event) {
            $('#halal_status3').val('2');
        });

        $('#add-main-category-form').validate({
            rules: {
                product_name: {
                    required: true,
                },
                halal_status: {
                    required: true,
                },
                product_image: {
                    required: true,
                    extension: "jpeg|png|jpg|webp"
                },
                Barcode: {
                    required: true,
                },
                Certification_Status: {
                    required: true,
                },
                category: {
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
                } else {
                    error.appendTo(element.parent());
                }
            },
            messages: {
                product_name: {
                    required: 'Please enter Product name',
                },
                halal_status: {
                    required: 'Please Select option',
                },
                product_image: {
                    required: 'Please select Product image',
                },
                Barcode: {
                    required: 'Please write the product barcode',
                },
                Certification_Status: {
                    required: 'Please write the certification status',
                },
                category: {
                    required: 'Please write the category',
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
                    url: "{{ route('product.store') }}",
                    type: "POST",
                    data: new FormData(form),
                    dataType: 'json',
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function(data) {
                        $('#loading').hide();
                        if (data.status == 1) {
                            $('#main-category-modal').modal('hide');
                            message('Product Add Successfully', 'success');
                            oTable.rows().invalidate('data').draw(false);
                            window.location.reload(true);
                        }
                    },
                    error: function(data) {
                        $('#loading').hide();
                        var errorString = '<ul>';
                        $.each(data.responseJSON.errors, function(key, value) {
                            errorString += '<li>' + value + '</li>';
                        });
                        errorString += '</ul>';
                        message(errorString, 'danger');
                    },
                });
                return false;
            }
        });

        function deleteproductModel(id) {
            $('#deleteid').val(id);
            $('#delete_modal').modal('show');
        }

        function editproductModel(id) {
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
                success: function(data) {
                    $('#loading').hide();
                    $('#edi_data_wrap').html(data.data);
                    $('#editModal').modal('show');
                },
                error: function(data) {},
            });
        }

        $('#editForm').validate({
            rules: {
                product_name: {
                    required: true,
                },
                halal_status: {
                    required: true,
                },
                product_image: {
                    extension: "jpeg|png|jpg|webp"
                },
                Barcode: {
                    required: true,
                },
                Certification_Status: {
                    required: true,
                },
                category: {
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
                } else {
                    error.appendTo(element.parent());
                }
            },
            messages: {
                product_name: {
                    required: 'Please enter Product name',
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
                category: {
                    required: 'Please write the category',
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
                    url: "{{ route('product.update') }}",
                    type: "POST",
                    data: new FormData(form),
                    dataType: 'json',
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function(data) {
                        $('#loading').hide();
                        if (data.status == 1) {
                            $("#editModal").modal('hide');
                            message('Product Update Successfully', 'success');
                            oTable.rows().invalidate('data').draw(false);
                        }
                    },
                    error: function(data) {
                        $('#loading').hide();
                        var errorString = '<ul>';
                        $.each(data.responseJSON.errors, function(key, value) {
                            errorString += '<li>' + value + '</li>';
                        });
                        errorString += '</ul>';
                        message(errorString, 'danger');
                    },
                });
                return false;
            }
        });

        // hide edit modal
        $(document).on('click', '.close, .close_btn', function() {
            $('#editModal').modal('hide');
        });


        // Handle category filter and deletion
        $('#deleteByCategory').click(function() {
            var selectedCategory = $('#categoryFilter').val();
            if (selectedCategory) {
                if (confirm('Are you sure you want to delete products in the selected category?')) {
                    $.ajax({
                        type: "POST",
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: "{{ route('product.deleteByCategory') }}",
                        data: {
                            category: selectedCategory
                        },
                        success: function(data) {
                            if (data.status == 1) {
                                message('Products deleted successfully', 'success');
                                oTable.ajax.reload(); // Reload the DataTable with updated data
                            } else {
                                message('Failed to delete products', 'danger');
                            }
                        }
                    });
                }
            } else {
                alert('Please select a category to delete products.');
            }
        });

        $('#deleteAllProducts').click(function() {
            if (confirm('Are you sure you want to delete all products?')) {
                $.ajax({
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: "{{ route('product.deleteAllProducts') }}",
                    success: function(data) {
                        if (data.status == 1) {
                            alert('All products have been deleted successfully');
                            oTable.ajax.reload();
                            // You can update the DataTable or the product list here if needed.
                        } else {
                            alert('Failed to delete products');
                        }
                    }
                });
            }
        });


        $(document).on('click', '.delete_btn', function() {
            $.ajax({
                type: "get",
                url: "{{ route('product.delete', '') }}/" + $('#deleteid').val(),
                data: {},
                success: function(data) {
                    if (data.status == 1) {
                        $('#delete_modal').modal('hide');
                        message(data.messages, 'success');
                        oTable.rows().invalidate('data').draw(false);
                    }
                }
            });
        });

        // hide delete modal
        $(document).on('click', '.close_btn', function() {
            $('#delete_modal').modal('hide');
        });

        function readIMG(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#display_image').attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#fileUploader").change(function() {
            $("#display_image").css("display", "block");
            readIMG(this);
        });

        function editReadIMG(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
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
