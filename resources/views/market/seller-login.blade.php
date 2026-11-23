@extends('layouts.market') @section('title', 'Seller sign in · Avelle') @section('content')<section class="shell auth-page">
    <div class="auth-story"><span class="eyebrow">AVELLE SELLER SPACE</span>
        <h1>Your collection.<br>Your next chapter.</h1>
        <p>Manage your catalogue, shipments and earning records.</p>
    </div>
    <div class="auth-card">
        <h2>Seller sign in</h2>
        <form method="POST" action="{{ url('/seller/login') }}">@csrf<label>Email<input type="email" name="email"
                    required autocomplete="email" value="{{ old('email') }}"></label><label>Password<input
                    type="password" name="password" required autocomplete="current-password"></label><button
                class="pink-button full">Sign in to seller dashboard</button></form>
        <p><a href="{{ url('/forgot-password') }}">Forgot password?</a></p>
        <p>New here? <a href="{{ url('/seller/apply') }}">Become a seller</a></p>
    </div>
</section>@endsection
