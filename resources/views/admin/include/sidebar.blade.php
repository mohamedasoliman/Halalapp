<nav class="pcoded-navbar" pcoded-header-position="relative">
	<div class="sidebar_toggle"><a href="{{ route('admin.dashboard') }}"><i class="icon-close icons"></i></a></div>
	<div class="pcoded-inner-navbar main-menu">
		<div class="">
			<div class="main-menu-header">
				<div style="text-align: center; margin-bottom: 12px;">
					<img src="{{ asset('assets/images/logo-white.png') }}" alt="Halal Kiwi" style="max-width: 160px; height: auto; opacity: 0.9;">
				</div>
				@if(!empty(auth()->user()->admin_image))
				@php
				$image = auth()->user()->admin_image;
				@endphp
				@else
				@php
				$image = 'user.png';
				@endphp
				@endif

				<img class="img-40" src="{{asset('assets/frontend/profiles/'.$image.'')}}" alt="User-Profile-Image">
				<div class="user-details">
					<span>{{  auth()->user()->name }}</span>
					<span id="more-details">{{ getLoginUserRoleName() }}<i class="ti-angle-down"></i></span>
				</div>
			</div>
			<div class="main-menu-content">
				<ul>
					<li class="more-details">
						<a href="{{ route('admin.adminProfile',auth()->user()->id) }}"><i class="ti-user"></i>View Profile</a>
						<a href="{{ route('admin.adminProfile',auth()->user()->id) }}"><i class="ti-settings"></i>Change Password</a>
						<a href="{{ route('logout') }}"
						onclick="event.preventDefault();
						document.getElementById('logout-form').submit();"><i class="ti-layout-sidebar-left"></i>Logout</a>
						<form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
							@csrf
						</form>
					</li>
				</ul>
			</div>
		</div>

		<ul class="pcoded-item pcoded-left-item">-->
		<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'admin.dashboard') active pcoded-trigger @endif">
		<a href="{{ route('admin.dashboard') }}">
					<span class="pcoded-micon"><i class="ti-home"></i></span>
					<span class="pcoded-mtext" data-i18n="nav.dash.main">Dashboard</span>
					<span class="pcoded-badge label label-danger">Default</span>
					<span class="pcoded-mcaret"></span>
				</a>
			</li>
		</ul>

		<!-- Prioritisation Menu Start -->
		<ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (in_array(\Request::route()->getName(), ['prioritisation.index', 'prioritisation.show', 'brands.index', 'brands.edit'])) active pcoded-trigger @endif">
				<a href="javascript:void(0)" data-i18n="nav.advance-components.main">
					<span class="pcoded-micon"><i class="ti-clipboard"></i></span>
					<span class="pcoded-mtext" data-i18n="nav.dash.main">Prioritisation</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (in_array(\Request::route()->getName(), ['prioritisation.index', 'prioritisation.show'])) active @endif">
						<a href="{{ route('prioritisation.index') }}" data-i18n="nav.advance-components.draggable">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Requests</span>
							<span class="pcoded-mcaret"></span>
						</a>
					</li>
					<li class="@if (in_array(\Request::route()->getName(), ['brands.index', 'brands.edit'])) active @endif">
						<a href="{{ route('brands.index') }}" data-i18n="nav.advance-components.draggable">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Brands</span>
							<span class="pcoded-mcaret"></span>
						</a>
					</li>
				</ul>
			</li>
		</ul>
		<!-- Prioritisation Menu End -->

		<!-- Products Management Menu Start -->
		<ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'product.index') active pcoded-trigger @endif">
				<a href="javascript:void(0)" data-i18n="nav.advance-components.main">
					<span class="pcoded-micon"><i class="ti-view-grid"></i></span>
					<span class="pcoded-mtext" data-i18n="nav.dash.main">Products Management</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'product.index') active @endif">
						<a href="{{ route('product.index') }}" data-i18n="nav.advance-components.draggable">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Products</span>
							<span class="pcoded-mcaret"></span>
						</a>
					</li>
				</ul>
			</li>
		</ul>

		<!-- Products Management Menu End -->

        {{-- Masjid Management Menu Start --}}
        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'masjid.index') active pcoded-trigger @endif">
				<a href="javascript:void(0)" data-i18n="nav.advance-components.main">
					<span class="pcoded-micon"><i class="ti-view-grid"></i></span>
					<span class="pcoded-mtext" data-i18n="nav.dash.main">Masjid Management</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'masjid.index') active @endif">
						<a href="{{ route('masjid.index') }}" data-i18n="nav.advance-components.draggable">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Masjid</span>
							<span class="pcoded-mcaret"></span>
						</a>
					</li>
				</ul>
			</li>
		</ul>
        {{-- Masjid Management Menu End --}}


        {{-- JSON Menu Start --}}
        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'json.index') active pcoded-trigger @endif">
				<a href="javascript:void(0)" data-i18n="nav.advance-components.main">
					<span class="pcoded-micon"><i class="ti-view-grid"></i></span>
					<span class="pcoded-mtext" data-i18n="nav.dash.main">Create JSON Data</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'json.index') active @endif">
						<a href="{{ route('json.index') }}" data-i18n="nav.advance-components.draggable">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Generate JSON</span>
							<span class="pcoded-mcaret"></span>
						</a>
					</li>
				</ul>
			</li>
		</ul>
        {{-- JSON Menu End --}}


        {{-- Restaurant Tiers Menu Start --}}
        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'restaurant.tiers') active pcoded-trigger @endif">
				<a href="javascript:void(0)" data-i18n="nav.advance-components.main">
					<span class="pcoded-micon"><i class="ti-view-grid"></i></span>
					<span class="pcoded-mtext" data-i18n="nav.dash.main">Restaurant Tiers</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'restaurant.tiers') active @endif">
						<a href="{{ route('restaurant.tiers') }}" data-i18n="nav.advance-components.draggable">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage Tiers</span>
							<span class="pcoded-mcaret"></span>
						</a>
					</li>
				</ul>
			</li>
		</ul>
        {{-- Restaurant Tiers Menu End --}}

        {{-- Notifications Menu Start --}}
        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'notification.manager') active pcoded-trigger @endif">
				<a href="javascript:void(0)" data-i18n="nav.advance-components.main">
					<span class="pcoded-micon"><i class="ti-bell"></i></span>
					<span class="pcoded-mtext" data-i18n="nav.dash.main">Notifications</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'notification.manager') active @endif">
						<a href="{{ route('notification.manager') }}" data-i18n="nav.advance-components.draggable">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage</span>
							<span class="pcoded-mcaret"></span>
						</a>
					</li>
				</ul>
			</li>
		</ul>
        {{-- Notifications Menu End --}}
</div>
</nav>
