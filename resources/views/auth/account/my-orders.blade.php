@extends('user.layouts.app')
@section('title', 'Profile')

@section('content')

<nav aria-label="breadcrumb" class="breadcrumb-nav">
    <div class="container">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('user.home') }}">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Account</li>
        </ol>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-lg-9 order-lg-last dashboard-content">
            <h2>My Orders</h2>
            @if (session('success'))
            <div class="alert alert-success">
               {!! session('success') !!}
           </div>
           @endif
       </div>
       @include('auth.account.sidebar')
   </div>
</div>
@stop

@push('scripts')

<script>
    
</script>

@endpush
