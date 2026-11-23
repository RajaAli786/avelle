@extends('layouts.market') @section('title', 'Checkout · Avelle')
@section('content')<section class="shell checkout-page">
        <div class="checkout-progress"><a href="{{ url('/cart') }}">01 Bag</a><span>────</span><b class="active">02 Address &
                checkout</b><span>────</span><b>03 Your order</b></div>
        <h1 class="page-title">Make it yours.</h1>
        <div class="checkout-layout">
            <div>
                @if (!auth()->check())
                    <div class="checkout-signin">Already have an account? <a
                            href="{{ url('/account/login') }}?checkout=1">Sign in before checkout →</a></div>
                    @endif @if ($addresses->count())
                        <section class="form-card">
                            <h2>Saved delivery addresses</h2>
                            <div class="address-choice">
                                @foreach ($addresses as $a)
                                    <button data-fill-address="{{ $a->id }}"><b>{{ $a->label }} ·
                                            {{ $a->name }}</b><span>{{ $a->address }}, {{ $a->city }}
                                            {{ $a->pincode }}</span></button>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    <form id="checkoutForm" class="form-card">
                        <h2>Delivery details</h2>
                        <div class="two-fields"><label>Full name<input name="name" required maxlength="100"
                                    autocomplete="name" value="{{ auth()->user()?->name }}"></label><label>Mobile
                                number<input name="phone" required pattern="[6-9][0-9]{9}"
                                    title="10-digit Indian mobile number" autocomplete="tel"
                                    value="{{ auth()->user()?->phone }}"></label></div><label>Email address<input
                                name="email" type="email" required maxlength="200" autocomplete="email"
                                value="{{ auth()->user()?->email }}" @auth readonly @endauth></label><label>House / street
                            / area
                            <textarea name="address" required maxlength="500" autocomplete="street-address">{{ auth()->user()?->address }}</textarea>
                        </label>
                        <div class="two-fields"><label>City<input name="city" required maxlength="100"
                                    autocomplete="address-level2"
                                    value="{{ auth()->user()?->city }}"></label><label>State<input name="state" required
                                    maxlength="100" autocomplete="address-level1"></label></div><label>PIN code<input
                                name="pincode" required pattern="[1-9][0-9]{5}" title="6-digit Indian PIN code"
                                autocomplete="postal-code" value="{{ auth()->user()?->pincode }}"></label>
                        @guest<div class="checkout-account-fields">
                                <h3>Create your Avelle profile</h3>
                                <p>Your order and delivery details will be saved in your own account.</p>
                                <div class="two-fields"><label>Create password<input name="password" type="password"
                                            minlength="10" required autocomplete="new-password"><small>Minimum 10
                                            characters</small></label><label>Confirm password<input name="password_confirmation"
                                            type="password" minlength="10" required autocomplete="new-password"></label></div>
                        </div>@endguest
                        <div class="payment-choice"><span>◉</span>
                            <div><b>Cash on delivery</b>
                                <p>Pay when your order arrives.</p>
                            </div>
                        </div>
                        <p class="checkout-terms">By placing an order, you agree to our <a
                                href="{{ url('/pages/terms') }}">terms</a> and <a
                                href="{{ url('/pages/returns') }}">return policy</a>.</p>
                        <p id="checkoutError" class="inline-error" role="alert"></p><button class="pink-button full"
                            id="placeOrderButton" @if (!$settings['cod_enabled']) disabled @endif>Place order →</button>
                        @if (!$settings['cod_enabled'])
                            <p class="inline-error">Cash on delivery is currently unavailable. Please contact support.</p>
                        @endif
                    </form>
            </div>
            <aside class="order-summary checkout-summary" id="checkoutSummary">
                <div class="loading-state">Checking your bag…</div>
            </aside>
        </div>
    </section>
<script id="savedAddresses" type="application/json">@json($addresses)</script>@endsection
