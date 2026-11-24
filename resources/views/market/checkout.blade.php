@extends('layouts.market') @section('title', 'Checkout · Avelle')
@push('head')
<link rel="stylesheet" href="{{ asset('checkout-address.css') }}?v=2">
@endpush
@php($selectedAddress = $addresses->firstWhere('is_default', true) ?? $addresses->first())
@section('content')<section class="shell checkout-page">
        <div class="checkout-progress"><a href="{{ url('/cart') }}">01 Bag</a><span>────</span><b class="active">02 Address &
                checkout</b><span>────</span><b>03 Your order</b></div>
        <h1 class="page-title">Make it yours.</h1>
        <div class="checkout-layout">
            <div>
                    @auth
                        <section class="form-card saved-address-panel">
                            <div class="saved-address-heading"><h2>Choose a delivery address</h2><button type="button" class="outline-button" data-new-address>+ Add new address</button></div>
                            <div class="address-choice" id="checkoutAddressChoices">
                                @foreach ($addresses as $a)
                                    <label class="checkout-address-card" data-address-card="{{ $a->id }}">
                                        <input type="radio" name="delivery_address_choice" value="{{ $a->id }}" @checked($selectedAddress?->id === $a->id)>
                                        <span class="checkout-address-text"><b>{{ $a->label }} · {{ $a->name }}</b><span>{{ $a->address }}, {{ $a->city }}, {{ $a->state }} {{ $a->pincode }}</span><small>{{ $a->phone }}</small></span>
                                        <button type="button" data-edit-address="{{ $a->id }}">Edit</button>
                                        <button type="button" class="deliver-address-button" data-deliver-address="{{ $a->id }}">Deliver to this address</button>
                                    </label>
                                @endforeach
                            </div>
                            <p id="addressSaveStatus" role="status" class="muted"></p>
                        </section>
                    @endauth
                    <form id="checkoutForm" class="form-card">
                        <h2>Delivery details</h2>
                        <input type="hidden" name="saved_address_id" value="{{ $selectedAddress?->id }}">
                        <input type="hidden" name="address_label" value="{{ $selectedAddress?->label ?? 'Home' }}">
                        <p class="muted">Your delivery address is saved for your next order.</p>
                        <div class="two-fields"><label>Full name<input name="name" required maxlength="100"
                                    autocomplete="name" value="{{ $selectedAddress?->name ?? auth()->user()?->name }}"></label><label>Mobile
                                number<input name="phone" required pattern="[6-9][0-9]{9}"
                                    title="10-digit Indian mobile number" autocomplete="tel"
                                    value="{{ $selectedAddress?->phone ?? auth()->user()?->phone }}"></label></div><label>Email address<input
                                name="email" type="email" required maxlength="200" autocomplete="email"
                                value="{{ auth()->user()?->email }}" @auth readonly @endauth></label><label>House / street
                            / area
                            <textarea name="address" required maxlength="500" autocomplete="street-address">{{ $selectedAddress?->address ?? auth()->user()?->address }}</textarea>
                        </label>
                        <div class="two-fields"><label>City<input name="city" required maxlength="100"
                                    autocomplete="address-level2"
                                    value="{{ $selectedAddress?->city ?? auth()->user()?->city }}"></label><label>State<input name="state" value="{{ $selectedAddress?->state }}" required
                                    maxlength="100" autocomplete="address-level1"></label></div><label>PIN code<input
                                name="pincode" required pattern="[1-9][0-9]{5}" title="6-digit Indian PIN code"
                                autocomplete="postal-code" value="{{ $selectedAddress?->pincode ?? auth()->user()?->pincode }}"></label>
                        @if ($walletEnabled && $walletBalance > 0)
                            <label class="wallet-choice"><input type="checkbox" name="use_wallet" id="useWallet" value="1"><span><b>Use my wallet · ₹{{ number_format($walletBalance) }} available</b><p>Shopping credit applies to merchandise after coupons. Delivery is paid separately.</p></span></label>
                        @endif
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
@auth @include('market.checkout-address-editor') @endauth
@push('scripts')<script src="{{ asset('checkout-address.js') }}?v=2"></script>@endpush
<script id="savedAddresses"  type="application/json">@json($addresses)</script>@endsection
