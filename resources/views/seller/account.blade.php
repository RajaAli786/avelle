<div class="seller-section-intro">
    <div>
        <h2>Your account. In your control.</h2>
        <p>Manage the login details for your seller account.</p>
    </div><span class="seller-pill"><i class="bi bi-shield-check"></i> Account & security</span>
</div>
<div class="business-grid">
    <div>
        <section class="seller-card p-4 mb-4">
            <h3 class="mb-2">Account details</h3>
            <p class="seller-account-description">Your name and email are used to sign in to Seller Central.</p>
            <form method="POST" action="{{ url('/seller/account') }}">@csrf<input type="hidden" name="_section"
                    value="seller-account">
                <div class="seller-fields"><label>Full name<input class="form-control" name="name"
                            value="{{ old('name', auth()->user()->name) }}" autocomplete="name" maxlength="100"
                            required></label><label>Login email<input class="form-control" name="email" type="email"
                            value="{{ old('email', auth()->user()->email) }}" autocomplete="email" maxlength="200"
                            required></label></div><label class="d-block my-3">Current password (required to change
                    email)<input class="form-control mt-2" name="current_password" type="password"
                        autocomplete="current-password"></label><button type="submit" class="btn btn-primary"><i
                        class="bi bi-check2"></i> Save account details</button>
            </form>
        </section>
        <section class="seller-card p-4">
            <h3 class="mb-2">Change password</h3>
            <p class="seller-account-description">Use at least 12 characters. Choose a password you do not use
                elsewhere.</p>
            <form method="POST" action="{{ url('/seller/password') }}">@csrf<input type="hidden" name="_section"
                    value="seller-account"><label class="d-block my-3">Current password<input class="form-control mt-2"
                        name="current_password" type="password" required autocomplete="current-password"></label>
                <div class="seller-fields"><label class="my-3">New password<input class="form-control" name="password"
                            type="password" minlength="12" required autocomplete="new-password"></label><label>Confirm
                        new password<input class="form-control" name="password_confirmation" type="password"
                            minlength="12" required autocomplete="new-password"></label></div><button type="submit"
                    class="btn btn-primary"><i class="bi bi-key"></i> Update password</button>
            </form>
        </section>
    </div>
    <aside>
        <section class="seller-card business-identity"><span class="business-avatar"><i
                    class="bi bi-person-lock"></i></span>
            <h3>{{ auth()->user()->name }}</h3>
            <p>{{ auth()->user()->email }}</p><span
                class="seller-status {{ $seller->status }}">{{ ucfirst($seller->status) }} seller</span>
            <hr>
            <p class="text-start">Changing your email or password signs out your other sessions. This session stays
                active.</p><a class="btn btn-light w-100" href="#seller-business"><i class="bi bi-building"></i>
                Business details</a>
        </section>
        <div class="business-help"><i class="bi bi-lock"></i>
            <h4>Keep your account yours.</h4>
            <p>Never share passwords or reset links. Forgot your current password?</p><a
                href="{{ url('/forgot-password') }}" target="_blank" rel="noopener">Request a reset link <i
                    class="bi bi-arrow-up-right"></i></a>
        </div>
    </aside>
</div>
