@extends('layouts.market') @section('title', 'Become a seller · Avelle') @push('head')
<link rel="stylesheet" href="{{ asset('seller.css') }}?v=1">
@endpush @section('content')<section class="shell seller-onboarding">
    <div><span class="eyebrow">GROW WITH AVELLE</span>
        <h1>Your shop.<br>A new audience.</h1>
        <p>Bring your collection to a marketplace for women and girls.</p>
        <ol>
            <li>Create your seller account and business profile.</li>
            <li>Wait for admin approval.</li>
            <li>Add products for review, then fulfil your orders.</li>
        </ol>
        <p>Commission is shown in your seller dashboard. Online payouts are not connected yet; admin records payments
            processed separately.</p><a href="{{ url('/seller/login') }}">Already a seller? Sign in →</a>
    </div>
    <div class="seller-panel">
        <h2>Become a seller</h2>@guest<p>Already have a customer account? <a href="{{ url('/account/login') }}">Sign in
                first</a>, then return here to apply using the same account.</p>@endguest
        <form method="POST" action="{{ url('/seller/apply') }}">@csrf @guest<label>Your name<input name="name" required
                        maxlength="100" autocomplete="name" value="{{ old('name') }}"></label><label>Email<input
                        type="email" name="email" required autocomplete="email"
                        value="{{ old('email') }}"></label><label>Password<input type="password" name="password"
                        minlength="12" required autocomplete="new-password"></label><label>Confirm password<input
                        type="password" name="password_confirmation" minlength="12" required
                    autocomplete="new-password"></label>@endguest @include('market.seller-fields')<button
                class="pink-button full">Submit seller application</button></form>
        <p>Applications are reviewed by the store owner. Registration does not automatically make your products live.
        </p>
    </div>
</section>@endsection
