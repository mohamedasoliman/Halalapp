@extends('admin.layouts.app')

@section('content')

    <div class="pcoded-main-container">

        @include('admin.include.sidebar')
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="page-body">
                            <div class="row">
                                <!-- Add a welcome message with styling -->
                                <div class="col-md-12">
                                    <div class="welcome-box text-center">
                                        <!-- Logo Image -->
                                        <img src="{{ asset('assets/images/logo.png') }}" alt="Logo" class="img-fluid mb-4">
                                        <div class="welcome-text">
                                            <h1>Welcome Back!</h1>
                                            <p class="lead">You are logged in as an Admin. Enjoy managing Halal Kiwi</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <!-- Sidebar for Buttons -->
                                {{-- <div class="col-12 col-md-3">
                                    <div class="list-group">
                                        <!-- Product Management with toggle functionality -->
                                        <a href="#productManagementSubLinks" class="list-group-item list-group-item-action btn-lg" data-bs-toggle="collapse">
                                            Product Management
                                        </a>
                                        <div class="collapse" id="productManagementSubLinks">
                                            <a href="{{ route('city.index') }}" class="list-group-item list-group-item-action ps-4">
                                                Products
                                            </a>
                                        </div>

                                        <!-- Masjid Management with toggle functionality -->
                                        <a href="#masjidManagementSubLinks" class="list-group-item list-group-item-action btn-lg" data-bs-toggle="collapse">
                                            Masjid Management
                                        </a>
                                        <div class="collapse" id="masjidManagementSubLinks">
                                            <a href="{{ route('masjid.index') }}" class="list-group-item list-group-item-action ps-4">
                                                Masjid List
                                            </a>
                                        </div>

                                        <!-- Create JSON Data with toggle functionality -->
                                        <a href="#jsonManagementSubLinks" class="list-group-item list-group-item-action btn-lg" data-bs-toggle="collapse">
                                            Create JSON Data
                                        </a>
                                        <div class="collapse" id="jsonManagementSubLinks">
                                            <a href="{{ route('json.index') }}" class="list-group-item list-group-item-action ps-4">
                                                Generate JSON
                                            </a>
                                        </div>

                                        <!-- Logout Button (No sublinks) -->
                                        <a href="{{ route('logout') }}" class="list-group-item list-group-item-action btn-lg text-danger"
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            Logout
                                        </a>
                                        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
                                            @csrf
                                        </form>
                                    </div>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- For otp -->

@endsection

@push('scripts')
    <!-- Additional scripts can be added here -->
@endpush

@push('styles')
    <style>
        .welcome-box {
            background: #004644;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .welcome-text h1 {
            color: #ffffff;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .welcome-text p {
            color: #ffffff;
            font-size: 1.25rem;
        }

        .btn-lg {
            padding: 10px 20px;
            font-size: 1.25rem;
        }

        .welcome-box img {
            max-width: 200px;
            height: auto;
        }
    </style>
@endpush
