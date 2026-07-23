@extends('admin.layouts.app')

@section('content')

    <div class="pcoded-main-container">

        @include('admin.include.sidebar')
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="page-body">
                            {{-- Welcome Banner - Horizontal --}}
                            <div class="welcome-banner">
                                <img src="{{ asset('assets/images/logo-white.png') }}" alt="Halal Kiwi" class="welcome-logo">
                                <div class="welcome-text">
                                    <h1>Welcome Back!</h1>
                                    <p>Halal Kiwi Admin Dashboard</p>
                                </div>
                            </div>

                            @if(!empty($stats))
                            {{-- Stats Grid --}}
                            <div class="stats-grid">
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
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: #004644;">
                                        <i class="icofont icofont-check-circled"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ number_format($stats['halal_products'] ?? 0) }}</h3>
                                        <p>Halal</p>
                                        <small>{{ $stats['not_halal_products'] ?? 0 }} not halal</small><br>
                                        <small>{{ $stats['mashbooh_products'] ?? 0 }} Mashbooh · {{ $stats['not_sure_products'] ?? 0 }} unreviewed</small>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: #3498db;">
                                        <i class="ti-location-pin"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ $stats['total_mosques'] ?? 0 }}</h3>
                                        <p>Mosques</p>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: #e67e22;">
                                        <i class="ti-shopping-cart"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ $stats['total_restaurants'] ?? 0 }}</h3>
                                        <p>Restaurants</p>
                                    </div>
                                </div>
                                <a href="{{ route('prioritisation.index') }}" class="stat-card stat-card-link">
                                    <div class="stat-icon" style="background: #8e44ad;">
                                        <i class="ti-clipboard"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ $stats['pending_requests'] ?? 0 }}</h3>
                                        <p>Active Requests</p>
                                        <small>{{ $stats['review_requests'] ?? 0 }} ready for review</small>
                                    </div>
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #004644 0%, #006b5a 100%);
            border-radius: 16px;
            padding: 28px 36px;
            margin-top: 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .welcome-logo {
            height: 60px;
            width: auto;
            opacity: 0.92;
        }

        .welcome-text h1 {
            color: #ffffff;
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .welcome-text p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.95rem;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .stat-card-link {
            text-decoration: none !important;
            color: inherit !important;
            cursor: pointer;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon i {
            font-size: 22px;
            color: #ffffff;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-info p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .stat-info small {
            color: #94a3b8;
            font-size: 0.75rem;
        }

        @media (max-width: 768px) {
            .welcome-banner {
                flex-direction: column;
                text-align: center;
                padding: 24px;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush
