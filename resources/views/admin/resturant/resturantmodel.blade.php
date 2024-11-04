<!-- Modal for adding a new Resturant -->
<div class="modal fade" id="addresturantModal" tabindex="-1" role="dialog" aria-labelledby="addresturantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addresturantModalLabel">Add New Resturant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addResturantForm" method="POST" enctype="multipart/form-data" action="{{route('resturant.store')}}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="resturantName">Resturant Name</label>
                                <input type="text" class="form-control" id="resturantName" name="resturantName">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" class="form-control" id="description" name="description">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="website">Website</label>
                                <input type="text" class="form-control" id="website" name="website">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="logo">Logo</label>
                                <input type="file" class="form-control" id="logo" name="logo">
                                @error('logo')
                                    <p class="alert alert-danger">{{$message}}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image1">Image 1</label>
                                <input type="file" class="form-control" id="image1" name="image1">
                                @error('image1')
                                    <p class="alert alert-danger">{{$message}}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image2">Image 2</label>
                                <input type="file" class="form-control" id="image2" name="image2">
                                @error('image2')
                                    <p class="alert alert-danger">{{$message}}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image3">Image 3</label>
                                <input type="file" class="form-control" id="image3" name="image3">
                                @error('image3')
                                    <p class="alert alert-danger">{{$message}}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image4">Image 4</label>
                                <input type="file" class="form-control" id="image4" name="image4">
                                @error('image4')
                                    <p class="alert alert-danger">{{$message}}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image5">Image 5</label>
                                <input type="file" class="form-control" id="image5" name="image5">
                                @error('image5')
                                    <p class="alert alert-danger">{{$message}}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image6">Image 6</label>
                                <input type="file" class="form-control" id="image6" name="image6">
                                @error('image6')
                                    <p class="alert alert-danger">{{$message}}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" class="form-control" id="address" name="address">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="longitude">Longitude</label>
                                <input type="text" class="form-control" id="longitude" name="longitude">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="latitude">Latitude</label>
                                <input type="text" class="form-control" id="latitude" name="latitude">
                            </div>
                        </div>
                    </div>
                    <!-- Submit button -->
                    <button type="submit" class="btn btn-primary" style="padding: 5px 40px;">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>


{{-- insert the records --}}
{{-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function(){
        $("#addResturantForm").submit(function(e){
            e.preventDefault();

            var formData = new FormData(this);

            $.ajax({
                url : "{{route('masjid.store')}}",
                type : "POST",
                data : formData,
                processData:false,
                contentType:false,
                success: function (response) {
                        //if returns true
                        console.log(response);
                    if (response.success) {
                        $("#addMasjidForm")[0].reset();
                        $("#addMasjidModal").modal('hide');
                        $('#masjid-table').DataTable().ajax.reload();
                        $('#successMessage').html('Data added successfully.').show();
                        setTimeout(function () {
                        $('#successMessage').fadeOut('fast');
                        }, 5000);
                    } else {
                        alert('Insertion failed. Please try again.');
                    }
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        // alert('An error occurred while inserting record.');

                    }
            });
        });
    });
</script> --}}


<div class="modal fade" id="editResturantModal" tabindex="-1" aria-labelledby="editResturantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editResturantModalLabel">Edit Resturant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editResturantForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="editResturantId" name="id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editresturantName">Resturant Name</label>
                                <input type="text" class="form-control" id="editresturantName" name="editresturantName">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editDescription">Description</label>
                                <input type="text" class="form-control" id="editDescription" name="editDescription">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editWebsite">Website</label>
                                <input type="text" class="form-control" id="editWebsite" name="editWebsite">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editLogo">Logo</label>
                                <input type="file" class="form-control" id="editLogo" name="editLogo">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editImage1">Image 1</label>
                                <input type="file" class="form-control" id="editImage1" name="editImage1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editImage2">Image 2</label>
                                <input type="file" class="form-control" id="editImage2" name="editImage2">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editImage3">Image 3</label>
                                <input type="file" class="form-control" id="editImage3" name="editImage3">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editImage">Image 4</label>
                                <input type="file" class="form-control" id="editImage" name="editImage">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editImage5">Image 5</label>
                                <input type="file" class="form-control" id="editImage5" name="editImage5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editImage6">Image 6</label>
                                <input type="file" class="form-control" id="editImage6" name="editImage6">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPhone">Phone</label>
                                <input type="text" class="form-control" id="editPhone" name="editPhone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editAddress">Address</label>
                                <input type="text" class="form-control" id="editAddress" name="editAddress">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editLongitude">Longitude</label>
                                <input type="text" class="form-control" id="editLongitude" name="editLongitude">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editLatitude">Latitude</label>
                                <input type="text" class="form-control" id="editLatitude" name="editLatitude">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function(){
        $("#editResturantForm").submit(function(e){
            e.preventDefault();

            var id = $('#editResturantId').val();
            var formData = new FormData(this);

            var logoInput = $('#editLogo')[0];
        if (logoInput && logoInput.files.length > 0) {
            formData.append('Logo', logoInput.files[0]);
        }

        // Handle Images1 to Images6 fields
        var imageFields = ['editImage1', 'editImage2', 'editImage3', 'editImage4', 'editImage5', 'editImage6'];
        for (var i = 0; i < imageFields.length; i++) {
            var imageInput = $('#' + imageFields[i])[0];
            if (imageInput && imageInput.files.length > 0) {
                formData.append('Image_' + (i + 1), imageInput.files[0]);
            }
        }

            $.ajax({
                url : '/admin/resturant/update/' + id,
                type : "POST",
                data : formData,
                processData: false,
                contentType: false,
                success: function (response) {
                        //if returns true
                    if (response.success) {
                        $("#editResturantForm")[0].reset();
                        $("#editResturantModal").modal('hide');
                        $('#resturant-table').DataTable().ajax.reload();
                        $('#successMessage').html('Data Updated successfully.').show();
                        setTimeout(function () {
                        $('#successMessage').fadeOut('fast');
                        }, 5000);
                    } else {
                        alert('Updating failed. Please try again.');
                    }
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        alert('An error occurred while updatting record.');
                    }
            });
        });
    });
</script>
