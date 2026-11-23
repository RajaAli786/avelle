@extends('layouts.market') @section('title', 'Track your order · Avelle')
@section('content')<section class="shell narrow-page">
        <span class="eyebrow">YOUR ORDER, EVERY STEP OF THE WAY</span>
        <h1 class="page-title">Track an order</h1>
        <p class="muted">Use the full order reference and the email entered at checkout.</p>
        <form class="form-card" method="POST" action="{{ url('/track-order') }}">@csrf<label>Order number<input
                    name="reference" placeholder="AVL-00000023" value="{{ old('reference') }}" required
                    placeholder="Full order reference"></label><label>Email address<input name="email" type="email"
                    value="{{ old('email') }}" required autocomplete="email"></label><button class="pink-button full">Find
                my order →</button></form>
        @if ($result)
            <div class="tracking-result"><span class="order-badge">{{ ucfirst($result['status']) }}</span>
                <h2>Order {{ $result['reference'] }}</h2>
                <p>Placed {{ date('d M Y', strtotime($result['created_at'])) }}</p>
                @if ($result['tracking_number'])
                    <p>{{ $result['carrier'] }} · {{ $result['tracking_number'] }}</p>
                    @endif @if ($result['tracking_url'])
                        <a class="text-link" href="{{ $result['tracking_url'] }}" target="_blank"
                            rel="noopener noreferrer">Track with carrier ↗</a>
                    @endif
                    <a class="dark-button full" href="{{ url('/account/orders/' . $result['reference']) }}">
                        Sign in for full order details →</a>
            </div>
        @endif
</section>@endsection
