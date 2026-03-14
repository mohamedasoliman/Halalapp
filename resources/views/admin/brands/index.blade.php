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
                                    <h4>Brands Management</h4>
                                </div>
                                <div class="page-header-breadcrumb">
                                    <ul class="breadcrumb-title">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}"><i class="icofont icofont-home"></i></a>
                                        </li>
                                        <li class="breadcrumb-item"><a href="javascript:;">Brands</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="page-body">
                                @include('admin.messages')

                                {{-- Add brand form --}}
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Add Brand</h5>
                                    </div>
                                    <div class="card-block">
                                        <form action="{{ route('brands.store') }}" method="POST" class="row g-3 align-items-end">
                                            @csrf
                                            <div class="col-md-3">
                                                <input type="text" name="name" class="form-control" placeholder="Brand name" required>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="text" name="email" class="form-control" placeholder="Email">
                                            </div>
                                            <div class="col-md-2">
                                                <select name="contact_type" class="form-control">
                                                    <option value="email">Email</option>
                                                    <option value="form">Contact Form</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <select name="response" class="form-control">
                                                    <option value="">No Response</option>
                                                    <option value="halal">Halal</option>
                                                    <option value="not_halal">Not Halal</option>
                                                    <option value="partial">Partial</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary">Add</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                {{-- Brands table --}}
                                <div class="card">
                                    <div class="card-block">
                                        <div class="dt-responsive table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Brand</th>
                                                        <th>Email</th>
                                                        <th>Type</th>
                                                        <th>Response</th>
                                                        <th>Scope</th>
                                                        <th>Last Contacted</th>
                                                        <th>Comms</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($brands as $brand)
                                                        <tr>
                                                            <td>{{ $brand->name }}</td>
                                                            <td>{{ $brand->email ?? '-' }}</td>
                                                            <td>{{ $brand->contact_type }}</td>
                                                            <td>
                                                                @if($brand->response)
                                                                    @php
                                                                        $rBadge = match($brand->response) {
                                                                            'halal' => 'badge-success',
                                                                            'not_halal' => 'badge-danger',
                                                                            'partial' => 'badge-warning',
                                                                            default => 'badge-light',
                                                                        };
                                                                    @endphp
                                                                    <span class="badge {{ $rBadge }}">{{ ucfirst(str_replace('_', ' ', $brand->response)) }}</span>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td>{{ $brand->response_scope ? ucfirst($brand->response_scope) : '-' }}</td>
                                                            <td>{{ $brand->last_contacted_at?->format('Y-m-d') ?? '-' }}</td>
                                                            <td>{{ $brand->communications_count }}</td>
                                                            <td>
                                                                <a href="{{ route('brands.edit', $brand->id) }}" class="btn btn-sm btn-info">Edit</a>
                                                                <form action="{{ route('brands.destroy', $brand->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this brand?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="8" class="text-center">No brands yet.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        {{ $brands->links() }}
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

@push('styles')
<style>
    .badge-warning { background: #f39c12; color: #fff; }
    .badge-danger { background: #e74c3c; color: #fff; }
    .badge-success { background: #27ae60; color: #fff; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; }
    .g-3 { gap: 10px; }
</style>
@endpush
