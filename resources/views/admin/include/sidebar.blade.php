<nav class="pcoded-navbar" pcoded-header-position="relative">
	<div class="sidebar_toggle"><a href="{{ route('admin.dashboard') }}"><i class="icon-close icons"></i></a></div>
	<div class="pcoded-inner-navbar main-menu">
		<div class="" style="padding-top: 12px;"></div>

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
			<li class="pcoded-hasmenu @if (str_starts_with(\Request::route()->getName() ?? '', 'business-network.')) active pcoded-trigger @endif">
				<a href="javascript:void(0)">
					<span class="pcoded-micon"><i class="ti-briefcase"></i></span>
					<span class="pcoded-mtext">Business Network</span>
					<span class="pcoded-mcaret"></span>
				</a>
				<ul class="pcoded-submenu">
					<li class="@if (str_starts_with(\Request::route()->getName() ?? '', 'business-network.')) active @endif">
						<a href="{{ route('business-network.index') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manage Businesses</span>
						</a>
					</li>
				</ul>
			</li>
		</ul>

        <ul class="pcoded-item pcoded-left-item">
			<li class="@if (str_starts_with(\Request::route()->getName() ?? '', 'analytics.')) active pcoded-trigger @endif">
				<a href="{{ route('analytics.index') }}">
					<span class="pcoded-micon"><i class="ti-bar-chart"></i></span>
					<span class="pcoded-mtext">Analytics</span>
					<span class="pcoded-mcaret"></span>
				</a>
			</li>
		</ul>

		@if((int) (\Illuminate\Support\Facades\Auth::guard('admin')->user()?->role_id) === 1)
			<ul class="pcoded-item pcoded-left-item">
				<li class="@if (str_starts_with(\Request::route()->getName() ?? '', 'support.')) active pcoded-trigger @endif">
					<a href="{{ route('support.index') }}">
						<span class="pcoded-micon"><i class="ti-headphone-alt" aria-hidden="true"></i></span>
						<span class="pcoded-mtext">App Support</span>
						<span class="pcoded-mcaret"></span>
					</a>
				</li>
			</ul>
		@endif

		<ul class="pcoded-item pcoded-left-item">
			<li class="pcoded-hasmenu @if (in_array(\Request::route()->getName(), ['prioritisation.index', 'prioritisation.show', 'brands.index', 'brands.edit', 'outreach.index'])) active pcoded-trigger @endif">
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
					<li class="@if (\Request::route()->getName() === 'outreach.index') active @endif">
						<a href="{{ route('outreach.index') }}">
							<span class="pcoded-micon"><i class="ti-angle-right"></i></span>
							<span class="pcoded-mtext">Manufacturer Outreach</span>
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
							<span class="pcoded-mtext">Manage Memberships</span>
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
