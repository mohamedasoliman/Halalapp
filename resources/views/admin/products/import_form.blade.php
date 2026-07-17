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
                                    <h4>Import Products</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item">Import Products</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                <div class="card">
                                    <div class="card-header">
                                        <h5>Upload Product CSV</h5>
                                    </div>
                                    <div class="card-block">
                                        <form action="{{ route('import.process') }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <div class="form-group">
                                                <label for="csv_file">CSV file</label>
                                                <input id="csv_file" type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Import Products</button>
                                            <a href="{{ route('product.index') }}" class="btn btn-secondary">Cancel</a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
