<!-- Modal for Create JSON Data -->
<div class="modal fade" id="createJsonModal" tabindex="-1" role="dialog" aria-labelledby="createJsonModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createJsonModalLabel">Create JSON Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createJsonForm" method="POST" action="{{ route('json.store') }}">
                    @csrf
                    <!-- Add your form fields here -->
                    <div class="form-group">
                        <label for="jsonData">JSON Data</label>
                        <textarea class="form-control" id="jsonData" name="jsonData" rows="5" required></textarea>
                    </div>
                    <!-- Add more fields as needed -->
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" class="form-control" id="description" name="description" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('createJsonForm').submit();">Save</button>
            </div>
        </div>
    </div>
</div>
