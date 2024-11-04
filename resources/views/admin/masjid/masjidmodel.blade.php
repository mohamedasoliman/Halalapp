<!-- Modal for adding a new Masjid -->
<div class="modal fade" id="addMasjidModal" tabindex="-1" role="dialog" aria-labelledby="addMasjidModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMasjidModalLabel">Add New Masjid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addMasjidForm" >
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="masjidName">Masjid Name</label>
                                <input type="text" class="form-control" id="masjidName" name="masjidName">
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
                                <label for="areaid">Area id</label>
                                <input type="text" class="form-control" id="areaid" name="areaid">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="areaname">Area Name</label>
                                <input type="text" class="form-control" id="areaname" name="areaname">
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
                                <label for="fajar">Fajar</label>
                                <input type="text" class="form-control" id="fajar" name="fajar">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="duhur">Duhur</label>
                                <input type="text" class="form-control" id="duhur" name="duhur">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="asr">Asr</label>
                                <input type="text" class="form-control" id="asr" name="asr">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="maghrib">Maghrib</label>
                                <input type="text" class="form-control" id="maghrib" name="maghrib">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ishaa">Ishaa</label>
                                <input type="text" class="form-control" id="ishaa" name="ishaa">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jumaa">Jumaa</label>
                                <input type="text" class="form-control" id="jumaa" name="jumaa">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="logitude">Longitude</label>
                                <input type="text" class="form-control" id="logitude" name="logitude">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="latitude">Latitude</label>
                                <input type="text" class="form-control" id="latitude" name="latitude">
                            </div>
                        </div>
                    </div>
                    <!-- Submit button -->
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>



{{-- insert the records --}}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function(){
        $("#addMasjidForm").submit(function(e){
            e.preventDefault();

            var forData = $(this).serialize();

            $.ajax({
                url : "{{route('masjid.store')}}",
                type : "POST",
                data : forData,
                success: function (response) {
                        //if returns true
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
                        alert('An error occurred while inserting record.');
                    }
            });
        });
    });
</script>




<!-- Modal for upating a Masjid Record -->
<div class="modal fade" id="editMasjidModal" tabindex="-1" role="dialog" aria-labelledby="editMasjidModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMasjidModalLabel">Edit Masjid Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editMasjidForm" >
                    @csrf
                    <input type="hidden" id="editMasjidId" name="editMasjidId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editMasjidName">Masjid Name</label>
                                <input type="text" class="form-control" id="editMasjidName" name="editMasjidName">
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
                                <label for="editAreaId">Area id</label>
                                <input type="text" class="form-control" id="editAreaId" name="editAreaId">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editAreaName">Area Name</label>
                                <input type="text" class="form-control" id="editAreaName" name="editAreaName">
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
                                <label for="editFajar">Fajar</label>
                                <input type="text" class="form-control" id="editFajar" name="editFajar">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editDuhur">Duhur</label>
                                <input type="text" class="form-control" id="editDuhur" name="editDuhur">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editAsr">Asr</label>
                                <input type="text" class="form-control" id="editAsr" name="editAsr">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editMaghrib">Maghrib</label>
                                <input type="text" class="form-control" id="editMaghrib" name="editMaghrib">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editIshaa">Ishaa</label>
                                <input type="text" class="form-control" id="editIshaa" name="editIshaa">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editJumaa">Jumaa</label>
                                <input type="text" class="form-control" id="editJumaa" name="editJumaa">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editLogitude">Longitude</label>
                                <input type="text" class="form-control" id="editLongitude" name="editLongitude">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editLatitude">Latitude</label>
                                <input type="text" class="form-control" id="editLatitude" name="editLatitude">
                            </div>
                        </div>
                    </div>
                    <!-- Update button -->
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>

    {{-- ajax fuction to update record --}}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function(){
        $("#editMasjidForm").submit(function(e){
            e.preventDefault();

            var id = $('#editMasjidId').val();
            var formData = $(this).serialize();

            $.ajax({
                url : '/admin/masjid/update/' + id,
                type : "POST",
                data : formData,
                success: function (response) {
                        //if returns true
                    if (response.success) {
                        $("#editMasjidForm")[0].reset();
                        $("#editMasjidModal").modal('hide');
                        $('#masjid-table').DataTable().ajax.reload();
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
