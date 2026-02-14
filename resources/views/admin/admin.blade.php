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
                                <div class="col-md-12">
                                    <div class="welcome-box text-center">
                                        <img src="{{ asset('assets/images/logo.png') }}" alt="Logo" class="img-fluid mb-4">
                                        <div class="welcome-text">
                                            <h1>Welcome Back!</h1>
                                            <p class="lead">Welcome to the Halal Kiwi Admin Dashboard. Let's make a difference together!</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($stats))
                            <div class="row mt-4">
                                <div class="col-md-3 col-sm-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: #2ecc71;">
                                            <i class="icofont icofont-food-basket"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3>{{ number_format($stats['total_products'] ?? 0) }}</h3>
                                            <p>Total Products</p>
                                            <small>{{ $stats['active_products'] ?? 0 }} active</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: #004644;">
                                            <i class="icofont icofont-check-circled"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3>{{ number_format($stats['halal_products'] ?? 0) }}</h3>
                                            <p>Halal</p>
                                            <small>{{ $stats['not_halal_products'] ?? 0 }} not halal &middot; {{ $stats['not_sure_products'] ?? 0 }} unsure</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: #3498db;">
                                            <i class="icofont icofont-mosque"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3>{{ $stats['total_mosques'] ?? 0 }}</h3>
                                            <p>Mosques</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: #e67e22;">
                                            <i class="icofont icofont-restaurant"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3>{{ $stats['total_restaurants'] ?? 0 }}</h3>
                                            <p>Restaurants</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        .welcome-box img {
            max-width: 200px;
            height: auto;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon i {
            font-size: 24px;
            color: #ffffff;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            color: #333;
        }

        .stat-info p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }

        .stat-info small {
            color: #999;
            font-size: 0.75rem;
        }
    </style>
@endpush
