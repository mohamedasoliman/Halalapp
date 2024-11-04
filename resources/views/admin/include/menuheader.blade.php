<div id="loading">Loading&#8230;</div>

{{-- Menu header start --}}
{{-- Removed the entire header section --}}
<div id="pcoded" class="pcoded">
    <div class="pcoded-overlay-box"></div>
    <div class="pcoded-container navbar-wrapper">
        <nav class="navbar header-navbar pcoded-header" header-theme="theme4">
            <div class="navbar-wrapper">
                <div class="navbar-logo">
                    <a class="mobile-menu" id="mobile-collapse" href="#!">
                        <i class="ti-menu"></i>
                    </a>
                    <a href="{{ route('admin.dashboard') }}"
                        style="display: inline-block; margin-inline: 50px; text-align: left;">
                        <img class="img-fluid" src="{{ asset('assets/images/logo.png') }}" alt="Theme-Logo" />
                    </a>
                    <a class="mobile-options">
                        <i class="ti-more"></i>
                    </a>
                </div>
                <div class="navbar-container container-fluid">
                    <div>
                        <ul class="nav-left">
                            <li>
                                <div class="sidebar_toggle"><a href="javascript:void(0)"><i class="ti-menu"></i></a>
                                </div>
                            </li>

                            <li>
                                <a href="#!" onclick="javascript:toggleFullScreen()">
                                    <i class="ti-fullscreen"></i>
                                </a>
                            </li>
                        </ul>
                        <ul class="nav-right">

                            <li class="user-profile header-notification">
                                <a href="#!">
                                    @if (!empty(auth()->user()->admin_image))
                                        @php
                                            $image = auth()->user()->admin_image;
                                        @endphp
                                    @else
                                        @php
                                            $image = 'user.png';
                                        @endphp
                                    @endif

                                    <img src="{{ asset('assets/frontend/profiles/' . $image . '') }}"
                                        alt="User-Profile-Image">
                                    <span>{{ auth()->user()->name }}</span>
                                    <i class="ti-angle-down"></i>
                                </a>
                                <ul class="show-notification profile-notification">
                                    <li>
                                        <a href="{{ route('admin.adminProfile', auth()->user()->id) }}">
                                            <i class="ti-user"></i>View Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.adminProfile', auth()->user()->id) }}">
                                            <i class="ti-settings"></i> Change Password
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('logout') }}"
                                            onclick="event.preventDefault();
                                        document.getElementById('logout-form').submit();">
                                            <i class="ti-layout-sidebar-left"></i>{{ __('Logout') }}</a>

                                        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST"
                                            style="display: none;">
                                            @csrf
                                        </form>

                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        {{-- <-- Sidebar chat start --> --}}

        @yield('content')

    </div>
</div>
