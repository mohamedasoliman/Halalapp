@if($status)
    <label class="label label-info status_list" data-id="{{$id}}">Active</label>
@else
    <label class="label label-danger status_list" data-id="{{$id}}">Deactive</label>
@endif

