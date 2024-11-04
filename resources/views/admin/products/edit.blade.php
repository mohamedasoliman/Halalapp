<div class="form-group row">
    	<label class="col-sm-2 col-form-label">Product Name</label>
    	<input name="update_id" id="updateId" type="hidden" class="form-control" value="{{ $Product->id }}">
        <div class="col-sm-10">
            <input name="product_name" id="editCityName" type="text" class="form-control" value="{{ $Product->product_name }}">
            @if ($errors->has('product_name'))
            <span style="color:red;">{{ $errors->first('product_name') }}</span>
             @endif
        </div>
</div>
<div class="form-group row">
    <label class="col-sm-2 col-form-label">Product image</label>
    <div class="col-sm-10">
    	<input type="file" name="product_image" id="editFileUploader" class="form-control">
    </div>
    <div class="col-sm-10">
        <img style="height: 100px; width: 100px;" id="edit_display_image" class="img-thumbnail" src="{{ $mode == 'Edit' ? asset("public/upload/product_images/".$Product->product_image) : old('product_image') }}">
    </div>
</div>
<div class="form-group row">
	<label class="col-sm-2 col-form-label">Halal Status</label>
	<div class="col-sm-10">
		<div class="radio radio-inline">
			<input type="radio" name="halal_status" value="0" @if($Product->halal_status == 0) checked @endif>
			Halal
		</div>
		<div class="radio radio-inline">
			<input type="radio" name="halal_status" value="1" @if($Product->halal_status == 1) checked @endif>
			Not Halal
		</div>
		<div class="radio radio-inline">
			<input type="radio" name="halal_status" value="2" @if($Product->halal_status == 2) checked @endif>
			Not Sure
		</div>
		@if ($errors->has('halal_status'))
			<span style="color:red;">{{$errors->first('halal_status')}}</span>
		@endif
	</div>
</div>
<div class="form-group row">
		<label class="col-sm-2 col-form-label">Status</label>
		<div class="col-sm-10">
			<div class="radio radio-inline">
				<input type="radio" name="status" value="1" @if($Product->status == 1) checked @endif>
				Active
			</div>
			<div class="radio radio-inline">
				<input type="radio" name="status" value="0" @if($Product->status == 0) checked @endif>
				Deactive
			</div>
			@if ($errors->has('status'))
				<span style="color:red;">{{$errors->first('status')}}</span>
			@endif
		</div>
</div>
<div class="form-group row">
    	<label class="col-sm-2 col-form-label">Barcode</label>
        <div class="col-sm-10">
            <input name="Barcode" id="editBarcode" type="text" class="form-control" value="{{ $Product->Barcode }}">
            @if ($errors->has('Barcode'))
            <span style="color:red;">{{ $errors->first('Barcode') }}</span>
             @endif
        </div>
</div>
<div class="form-group row">
    	<label class="col-sm-2 col-form-label">Certification Status</label>
        <div class="col-sm-10">
            <input name="Certification_Status" id="editCertification_Status" type="text" class="form-control" value="{{ $Product->Certification_Status }}">
            @if ($errors->has('Certification_Status'))
            <span style="color:red;">{{ $errors->first('Certification_Status') }}</span>
             @endif
        </div>
</div>
<div class="form-group row">
    	<label class="col-sm-2 col-form-label">Category</label>
        <div class="col-sm-10">
            <input name="category" id="editCategory" type="text" class="form-control" value="{{ $Product->category }}">
            @if ($errors->has('category'))
            <span style="color:red;">{{ $errors->first('category') }}</span>
             @endif
        </div>
</div>
<div class="form-group row">
    	<label class="col-sm-2 col-form-label">Notes</label>
        <div class="col-sm-10">
            <input name="notes" id="editNotes" type="text" class="form-control" value="{{ $Product->notes }}">
            @if ($errors->has('notes'))
            <span style="color:red;">{{ $errors->first('notes') }}</span>
             @endif
        </div>
</div>
<div class="form-group row">
    	<label class="col-sm-2 col-form-label">Ingredients</label>
        <div class="col-sm-10">
            <input name="ingredient" id="editIngredient" type="text" class="form-control" value="{{ $Product->ingredient }}">
            @if ($errors->has('ingredient'))
            <span style="color:red;">{{ $errors->first('ingredient') }}</span>
             @endif
        </div>
</div>
