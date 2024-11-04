<div class="modal fade bd-example-modal-lg" id="editModal" tabindex="-1" role="dialog"
aria-labelledby="addServiceTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="deleteModalLongTitle">Edit Product</h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <form  method="post" role="form" id="editForm" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="register-form-errors"></div>
        {{csrf_field()}}
        <div id="edi_data_wrap"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-white close_btn" data-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
</div>



{{-- delete modal --}}
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
        <p>Are you sure you want to delete this Product?</p>
        <div class="text-right">
          <form method="post" role="form" id="deleteForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" id="deleteid">
            <button data-dismiss="modal" class="btn btn-default btn-sm close_btn" type="button" id="modal_close">Close</button>
            <button type="button" class="btn btn-danger btn-sm delete_btn">Delete</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- entry modal --}}
<div id="main-category-modal" class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Product</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="post" role="form" id="add-main-category-form" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">
          <div class="register-form-errors"></div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Product Name</label>
            <div class="col-sm-10">
              <input name="product_name" id="CityName" type="text" class="form-control" value="{{ old('product_name') }}">
              @if ($errors->has('product_name'))
              <span style="color:red;">{{ $errors->first('product_name') }}</span>
              @endif
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Product image</label>
            <div class="col-sm-10">
              <input type="file" name="product_image" id="fileUploader" class="form-control">
            </div>
            <div class="col-sm-10">
              <img style="height: 100px; width: 100px; display: none;" id="display_image" class="img-thumbnail" src="">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Product Status</label>
            <div class="col-sm-10">
              <div class="radio radio-inline">
                <input type="radio" id="halal_status1" name="halal_status" value="0">
                Halal
              </div>
              <div class="radio radio-inline">
                <input type="radio" id="halal_status2" name="halal_status" value="1">
                Not Halal
              </div>
              <div class="radio radio-inline">
                <input type="radio" id="halal_status3" name="halal_status" value="2">
                Not Sure
              </div>
              <span id="radio-error"></span>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-2 col-form-label">Barcode</label>
            <div class="col-sm-10">
              <input name="Barcode" id="Barcode" type="text" class="form-control" value="{{ old('Barcode') }}">
              @if ($errors->has('Barcode'))
              <span style="color:red;">{{ $errors->first('Barcode') }}</span>
              @endif
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-2 col-form-label">Certification Status</label>
            <div class="col-sm-10">
              <input name="Certification_Status" id="Certification_Status" type="text" class="form-control" value="{{ old('Certification_Status') }}">
              @if ($errors->has('Certification_Status'))
              <span style="color:red;">{{ $errors->first('Certification_Status') }}</span>
              @endif
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-2 col-form-label">Category</label>
            <div class="col-sm-10">
              <input name="category" id="category" type="text" class="form-control" value="{{ old('category') }}">
              @if ($errors->has('category'))
              <span style="color:red;">{{ $errors->first('category') }}</span>
              @endif
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-2 col-form-label">Notes</label>
            <div class="col-sm-10">
              <input name="notes" id="notes" type="text" class="form-control" value="{{ old('notes') }}">
              @if ($errors->has('notes'))
              <span style="color:red;">{{ $errors->first('notes') }}</span>
              @endif
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-2 col-form-label">Ingredients</label>
            <div class="col-sm-10">
              <input name="ingredient" id="ingredient" type="text" class="form-control" value="{{ old('ingredient') }}">
              @if ($errors->has('ingredient'))
              <span style="color:red;">{{ $errors->first('ingredient') }}</span>
              @endif
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Submit</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>
