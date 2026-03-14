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
                                    <h4>Edit Brand: {{ $brand->name }}</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="{{ route('brands.index') }}">Brands</a></li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Edit</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                <div class="card">
                                    <div class="card-block">
                                        <form action="{{ route('brands.update', $brand->id) }}" method="POST">
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label>Brand Name</label>
                                                        <input type="text" name="name" class="form-control" value="{{ $brand->name }}" required>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label>Email</label>
                                                        <input type="text" name="email" class="form-control" value="{{ $brand->email }}">
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label>Contact Type</label>
                                                        <select name="contact_type" class="form-control">
                                                            <option value="email" {{ $brand->contact_type === 'email' ? 'selected' : '' }}>Email</option>
                                                            <option value="form" {{ $brand->contact_type === 'form' ? 'selected' : '' }}>Contact Form</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label>Response</label>
                                                        <select name="response" class="form-control">
                                                            <option value="" {{ !$brand->response ? 'selected' : '' }}>No Response</option>
                                                            <option value="halal" {{ $brand->response === 'halal' ? 'selected' : '' }}>Halal</option>
                                                            <option value="not_halal" {{ $brand->response === 'not_halal' ? 'selected' : '' }}>Not Halal</option>
                                                            <option value="partial" {{ $brand->response === 'partial' ? 'selected' : '' }}>Partial</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label>Response Scope</label>
                                                        <select name="response_scope" class="form-control">
                                                            <option value="" {{ !$brand->response_scope ? 'selected' : '' }}>N/A</option>
                                                            <option value="blanket" {{ $brand->response_scope === 'blanket' ? 'selected' : '' }}>Blanket (all products)</option>
                                                            <option value="partial" {{ $brand->response_scope === 'partial' ? 'selected' : '' }}>Partial (specific products)</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label>Notes</label>
                                                        <textarea name="notes" class="form-control" rows="3">{{ $brand->notes }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-muted">{{ $requestCount }} prioritisation request(s) linked to this brand.</p>
                                            <button type="submit" class="btn btn-primary">Save</button>
                                            <a href="{{ route('brands.index') }}" class="btn btn-secondary">Cancel</a>
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
