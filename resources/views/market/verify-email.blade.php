@extends('layouts.market')
@section('title', 'Verify your email · Avelle')
@section('content')
<section class="shell verification-page">
    <div class="form-card verification-card">
        <span class="eyebrow">ONE LITTLE STEP</span>
        <div class="verification-icon" aria-hidden="true">✉</div>
        <h1>Make it officially yours.</h1>
        <p>Open the verification link sent to <strong>{{ auth()->user()->email }}</strong>.</p>
        <p class="muted">You can keep exploring. Verify your email before placing an order or activating your referral rewards. Links expire in 60 minutes.</p>
        <form method="POST" action="{{ url('/email/verification-notification') }}">@csrf
            <button class="pink-button">Send verification link</button>
        </form>
        <p class="muted">Check your spam folder too. Please allow a minute between requests.</p>
        <a href="{{ url('/shop') }}">Continue exploring →</a>
    </div>
</section>
@endsection
