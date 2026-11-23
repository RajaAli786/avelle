@extends('layouts.market') @section('title', 'Your shopping bag · Avelle')
@section('content')<div class="shell breadcrumbs"><a href="{{ url('/') }}">Home</a><span>/</span><span>Shopping
            bag</span></div>
    <section class="shell bag-page">
        <div class="checkout-progress"><b class="active">01 Bag</b><span>────</span><b>02 Address &
                checkout</b><span>────</span><b>03 Your order</b></div>
        <div class="section-title">
            <div>
                <h1 class="page-title">Your shopping bag</h1>
                <p class="muted">A little room for your next favourites.</p>
            </div><a href="{{ url('/shop') }}">Continue shopping ↗</a>
        </div>
        <div id="cartPage" class="cart-layout">
            <div id="cartLines" class="cart-lines">
                <div class="loading-state">Loading your bag…</div>
            </div>
            <aside class="order-summary" id="cartSummary"></aside>
        </div>
</section>@endsection
