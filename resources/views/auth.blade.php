@extends('layouts.market') @section('title', ($mode === 'register' ? 'Create account' : 'Sign in') . ' · Avelle')
@section('content')<section class="shell auth-page">
    <div class="auth-story"><span class="eyebrow">YOUR AVELLE STARTS HERE</span>
        <h1>A world of style.<br>Your own little corner.</h1>
        <p>Keep your orders, saved finds and delivery details together.</p><img
            src="{{ asset('images/photo-1515372039744-b8f02a3ae446.jpg') }}" alt="Avelle fashion">
    </div>
    <div class="auth-card"><span class="eyebrow">{{ $mode === 'register' ? 'JOIN THE AVELLE CLUB' : 'WELCOME BACK' }}</span>
        <h2>{{ $mode === 'register' ? 'Make yourself at home.' : 'Your favourites missed you.' }}</h2>
        <p class="muted">
            {{ $mode === 'register' ? 'Create your account to start your Avelle story.' : 'Sign in to your orders and everyday favourites.' }}
        </p>
        <form method="POST" action="{{ url('/account/' . ($mode === 'register' ? 'register' : 'login')) }}">@csrf<input
                type="hidden" name="checkout" value="{{ $checkout ? 1 : 0 }}">
            @if ($mode === 'register')
            <label>Full name<input name="name" required value="{{ old('name') }}" maxlength="100"
                    autocomplete="name"></label>
            @endif
            <label>
                Email address<input name="email" type="email" required value="{{ old('email') }}" maxlength="200"
                    autocomplete="email"></label><label>Password<input name="password" type="password" required
                    @if ($mode==='register' ) minlength="10" autocomplete="new-password" @else autocomplete="current-password" @endif></label>
            @if ($mode === 'register')
            <label>Confirm password<input name="password_confirmation" type="password" required minlength="10"
                    autocomplete="new-password"></label><small class="muted">Use at least 10 characters.</small>
            @else
            <a class="forgot-link" href="{{ url('/forgot-password') }}">Forgot password?</a>
            @endif
            <button class="pink-button full">
                {{ $mode === 'register' ? 'Create my account' : 'Sign in' }} →</button>
        </form>
        <p class="auth-switch">
            @if ($mode === 'register')
            Already have an account?
            <a href="{{ url('/account/login') }}{{ $checkout ? '?checkout=1' : '' }}">Sign in</a>
            @else
            New to Avelle? <a href="{{ url('/account/register') }}{{ $checkout ? '?checkout=1' : '' }}">Create an
                account</a>
            @endif
        </p>
    </div>
</section>@endsection