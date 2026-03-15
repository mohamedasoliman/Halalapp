<nav class="pcoded-navbar" pcoded-header-position="relative">
	<div class="sidebar_toggle"><a href="{{ route('admin.dashboard') }}"><i class="icon-close icons"></i></a></div>
	<div class="pcoded-inner-navbar main-menu">
		<div class="">
			<div class="main-menu-header" style="padding-top: 20px;">
				<div style="text-align: center; margin-bottom: 16px; padding: 0 20px;">
					<img src="{{ asset('assets/images/logo-white.png') }}" alt="Halal Kiwi" style="max-width: 150px; height: auto; opacity: 0.9;">
				</div>
				@php $image = !empty(auth()->user()->admin_image) ? auth()->user()->admin_image : 'user.png'; @endphp
				<div style="display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: rgba(255,255,255,0.06); border-radius: 10px; margin: 0 12px;">
					<img class="img-40" src="{{ asset('assets/frontend/profiles/'.$image) }}" alt="Profile" style="border-radius: 50%; border: 2px solid rgba(255,255,255,0.2);">
					<div class="user-details" style="flex: 1;">
						<span style="font-weight: 600; font-size: 14px;">{{ auth()->user()->name }}</span>
						<span id="more-details" style="font-size: 12px; opacity: 0.7;">{{ getLoginUserRoleName() }} <i class="ti-angle-down"></i></span>
					</div>
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

		<ul class="pcoded-item pcoded-left-item">
		<li class="@if (\Request::route()->getName() == 'admin.dashboard') active pcoded-trigger @endif">
		<a href="{{ route('admin.dashboard') }}">
					<span class="pcoded-micon"><i class="ti-home"></i></span>
					<span class="pcoded-mtext">Dashboard</span>
					<span class="pcoded-mcaret"></span>
				</a>
			</li>
		</ul>

		<ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (in_array(\Request::route()->getName(), ['prioritisation.index', 'prioritisation.show', 'brands.index', 'brands.edit'])) active pcoded-trigger @endif">
				<a href="javascript:void(0)">
					<span class="pcoded-micon"><i class="ti-clipboard"></i></span>
					<span class="pcoded-mtext">Prioritisation</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (in_array(\Request::route()->getName(), ['prioritisation.index', 'prioritisation.show'])) active @endif">
						<a href="{{ route('prioritisation.index') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Requests</span>
						</a>
					</li>
					<li class="@if (in_array(\Request::route()->getName(), ['brands.index', 'brands.edit'])) active @endif">
						<a href="{{ route('brands.index') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Brands</span>
						</a>
					</li>
				</ul>
			</li>
		</ul>

		<ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'product.index') active pcoded-trigger @endif">
				<a href="javascript:void(0)">
					<span class="pcoded-micon"><i class="ti-package"></i></span>
					<span class="pcoded-mtext">Products</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'product.index') active @endif">
						<a href="{{ route('product.index') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage Products</span>
						</a>
					</li>
				</ul>
			</li>
		</ul>

        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'masjid.index') active pcoded-trigger @endif">
				<a href="javascript:void(0)">
					<span class="pcoded-micon"><i class="ti-location-pin"></i></span>
					<span class="pcoded-mtext">Mosques</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'masjid.index') active @endif">
						<a href="{{ route('masjid.index') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage Mosques</span>
						</a>
					</li>
				</ul>
			</li>
		</ul>

        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'restaurant.tiers') active pcoded-trigger @endif">
				<a href="javascript:void(0)">
					<span class="pcoded-micon"><i class="ti-shopping-cart"></i></span>
					<span class="pcoded-mtext">Restaurants</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'restaurant.tiers') active @endif">
						<a href="{{ route('restaurant.tiers') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage Tiers</span>
						</a>
					</li>
				</ul>
			</li>
		</ul>

        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'json.index') active pcoded-trigger @endif">
				<a href="javascript:void(0)">
					<span class="pcoded-micon"><i class="ti-file"></i></span>
					<span class="pcoded-mtext">Directories</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'json.index') active @endif">
						<a href="{{ route('json.index') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage Data</span>
						</a>
					</li>
				</ul>
			</li>
		</ul>

        <ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (\Request::route()->getName() == 'notification.manager') active pcoded-trigger @endif">
				<a href="javascript:void(0)">
					<span class="pcoded-micon"><i class="ti-bell"></i></span>
					<span class="pcoded-mtext">Notifications</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (\Request::route()->getName() == 'notification.manager') active @endif">
						<a href="{{ route('notification.manager') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage</span>
						</a>
					</li>
				</ul>
			</li>
		</ul>
</div>
</nav>
