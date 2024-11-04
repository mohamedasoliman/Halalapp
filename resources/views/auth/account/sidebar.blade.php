<aside class="sidebar col-lg-3">
    <div class="widget widget-dashboard">
        <h3 class="widget-title">My Account</h3>

        <ul class="list">
            <li class="{{ Request::route()->getName() == 'account' ? 'active' : '' }}"><a href="{{ route('account') }}">Account Dashboard</a></li>
            <li class="{{ Request::route()->getName() == 'user-change-password' ? 'active' : '' }}"><a href="{{ route('user-change-password') }}">Change Password</a></li>
             <li class="{{ Request::route()->getName() == 'my-orders' ? 'active' : '' }}"><a href="{{ route('my-orders') }}">My orders</a></li>
            {{--<li><a href="#">Address Book</a></li>
            <li><a href="#">My Orders</a></li>
            <li><a href="#">Billing Agreements</a></li>
            <li><a href="#">Recurring Profiles</a></li>
            <li><a href="#">My Product Reviews</a></li>
            <li><a href="#">My Tags</a></li>
            <li><a href="#">My Wishlist</a></li>
            <li><a href="#">My Applications</a></li>
            <li><a href="#">Newsletter Subscriptions</a></li>
            <li><a href="#">My Downloadable Products</a></li>--}}
        </ul>
    </div>
</aside>
