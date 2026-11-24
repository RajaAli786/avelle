@extends('layouts.market') @section('title', 'My account · Avelle') @push('head')
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('account.css') }}?v=1">
@endpush
@section('content')<div class="shell breadcrumbs"><a href="{{ url('/') }}">Home</a><span>/</span><span>My
        account</span></div>
<section class="shell customer-layout">
    <aside class="customer-menu">
        <div class="customer-identity"><span>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div><b>{{ $user->name }}</b><small>{{ $user->email }}</small></div>
        </div><span class="account-menu-caption">YOUR AVELLE</span>
        @foreach (['overview' => ['bi-grid', 'Overview'], 'orders' => ['bi-bag-check', 'My orders'], 'reviews' => ['bi-chat-square-heart', 'My reviews'], 'returns' => ['bi-arrow-return-left', 'My returns'], 'addresses' => ['bi-geo-alt', 'Address book'], 'profile' => ['bi-person', 'My profile'], 'security' => ['bi-shield-lock', 'Password & security']] as $key => $nav)
            <a class="{{ $section === $key ? 'active' : '' }}"
                href="{{ $key === 'overview' ? url('/account') : url('/account/' . $key) }}"
                @if ($section === $key) aria-current="page" @endif><i class="bi {{ $nav[0] }}"
                    aria-hidden="true"></i><span>{{ $nav[1] }}</span><span class="account-nav-arrow">›</span></a>
        @endforeach
        @if ($walletVisible)
        <a class="{{ $section === 'wallet' ? 'active' : '' }}" href="{{ url('/account/wallet') }}"><i class="bi bi-wallet2" aria-hidden="true"></i><span>Refer & Earn / Wallet</span><span class="account-nav-arrow">›</span></a>
        @endif
        <a href="{{ url('/wishlist') }}">
            <i class="bi bi-heart" aria-hidden="true"></i><span>My wishlist</span><span
                class="account-nav-arrow">›</span></a>
        @if ($user->role === 'admin')
            <a href="{{ url('/admin') }}"><i class="bi bi-shop" aria-hidden="true"></i><span>Admin
                    dashboard</span><span class="account-nav-arrow">↗</span></a>
        @elseif($user->role === 'seller')
            <a href="{{ url('/seller') }}"><i class="bi bi-shop" aria-hidden="true"></i><span>Seller
                    dashboard</span><span class="account-nav-arrow">↗</span></a>
        @endif
        <form method="POST" action="{{ url('/account/logout') }}">
            @csrf<button><i class="bi bi-box-arrow-left" aria-hidden="true"></i> Sign out</button></form>
        <div class="account-sidebar-note"><i class="bi bi-heart" aria-hidden="true"></i><b>A little more you.</b>
            <p>Your favourites and everyday finds belong here.</p><a href="{{ url('/shop') }}">Keep exploring →</a>
        </div>
    </aside>
    <div class="customer-content">
        @if ($section === 'wallet')
            @include('market.account-wallet')
        @elseif ($section === 'overview')
            <section class="customer-welcome">
                <div><span class="eyebrow">YOUR OWN LITTLE CORNER</span>
                    <h1>Hello, {{ explode(' ', $user->name)[0] }}<span>✦</span></h1>
                    <p>Your orders, delivery details and favourite finds, together.</p><a
                        href="{{ url('/shop') }}">Discover your next favourite <i class="bi bi-arrow-up-right"
                            aria-hidden="true"></i></a>
                </div>
                <div class="customer-welcome-art" aria-hidden="true"><i class="bi bi-bag-heart"></i><span>made for
                        you</span></div>
            </section>
            <div class="account-stat-grid"><a href="{{ url('/account/orders') }}"><i class="bi bi-bag-check"
                        aria-hidden="true"></i><b>{{ $accountStats['orders'] }}</b><span>Total orders</span></a><a
                    href="{{ url('/account/orders') }}?status=in_progress"><i class="bi bi-truck"
                        aria-hidden="true"></i><b>{{ $accountStats['in_progress'] }}</b><span>Orders in
                        progress</span></a><a href="{{ url('/wishlist') }}"><i class="bi bi-heart"
                        aria-hidden="true"></i><b>{{ $accountStats['wishlist'] }}</b><span>Saved favourites</span></a>
            </div>
            <section class="form-card">
                <div class="section-title">
                    <div><span class="eyebrow">FROM YOUR BAG TO YOUR DOOR</span>
                        <h2>Recent orders</h2>
                    </div><a href="{{ url('/account/orders') }}">View all →</a>
                </div>
                @forelse($orders->getCollection()->take(3) as $o)
                    @include('market.order-card', ['o' => $o])@empty
                    <div class="account-empty"><i class="bi bi-bag-heart" aria-hidden="true"></i>
                        <h3>Your next favourite is waiting.</h3>
                        <p>Your orders will appear here once you find something you love.</p><a class="pink-button"
                            href="{{ url('/shop') }}">Explore the collection ↗</a>
                    </div>
                @endforelse
            </section>
            <div class="account-quick-grid"><a href="{{ url('/account/reviews') }}"><i class="bi bi-chat-heart"
                        aria-hidden="true"></i>
                    <div>
                        <h3>Your voice matters.</h3>
                        <p>Write a review after delivery and follow its approval status.</p><b>My reviews →</b>
                    </div>
                </a><a href="{{ url('/account/addresses') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <div>
                        <h3>A little closer to home.</h3>
                        <p>{{ $addresses->count() }} saved delivery addresses. Keep your next checkout easy.</p>
                        <b>Address book →</b>
                    </div>
                </a></div>
        @elseif($section === 'orders')
            <div class="account-section-heading"><span class="eyebrow">YOUR FINDS, ON THEIR WAY</span>
                <h1>My orders</h1>
                <p>Follow every order from your bag to your door.</p>
            </div>
            <form method="GET" action="{{ url('/account/orders') }}" class="account-order-filters"><label>Search your
                    orders<input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Order reference or product name"></label><label>Order status<select name="status">
                        @foreach (['all' => 'All orders', 'in_progress' => 'In progress', 'placed' => 'Placed', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? 'all') === $key)>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label><button class="pink-button">Apply filters</button>
                @if (request('q') || request('status'))
                    <a href="{{ url('/account/orders') }}">Clear</a>
                @endif
            </form>
            @forelse($orders as $o)
                @include('market.order-card', ['o' => $o])@empty
                <div class="form-card account-empty"><i class="bi bi-search-heart" aria-hidden="true"></i>
                    <h2>{{ $accountStats['orders'] ?? 0 ? 'No matching orders.' : 'No orders yet.' }}</h2>
                    <p>{{ $accountStats['orders'] ?? 0 ? 'Try another product name or status.' : 'Find something you love and we’ll keep its journey here.' }}
                    </p><a href="{{ $accountStats['orders'] ?? 0 ? url('/account/orders') : url('/shop') }}"
                        class="pink-button">{{ $accountStats['orders'] ?? 0 ? 'View all orders' : 'Find your first favourite ↗' }}</a>
                </div>
            @endforelse @include('market.account-pagination', ['paginator' => $orders])
        @elseif($section === 'reviews')
            @include('market.account-reviews')
        @elseif($section === 'returns')
            @include('market.account-returns')
        @elseif($section === 'profile')
            <section class="form-card">
                <div class="account-section-heading"><span class="eyebrow">THE DETAILS THAT MAKE IT YOURS</span>
                    <h1>My profile</h1>
                    <p>Your login email: {{ $user->email }}</p>
                </div>
                <form method="POST" action="{{ url('/account/profile') }}">@csrf<div class="two-fields"><label>Full
                            name<input name="name" value="{{ old('name', $user->name) }}" required maxlength="100"
                                autocomplete="name"></label><label>Mobile number<input name="phone"
                                value="{{ old('phone', $user->phone) }}" pattern="[6-9][0-9]{9}"
                                autocomplete="tel"></label></div><label>Preferred delivery address
                        <textarea name="address" maxlength="500" autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                    </label>
                    <div class="two-fields"><label>City<input name="city" value="{{ old('city', $user->city) }}"
                                maxlength="100" autocomplete="address-level2"></label><label>PIN code<input
                                name="pincode" value="{{ old('pincode', $user->pincode) }}" pattern="[1-9][0-9]{5}"
                                autocomplete="postal-code"></label></div><button class="pink-button">Save profile
                        →</button>
                </form>
            </section>
        @elseif($section === 'security')
            <section class="form-card">
                <div class="account-section-heading"><span class="eyebrow">KEEP YOUR ACCOUNT YOURS</span>
                    <h1>Password & security</h1>
                    <p>Use at least {{ $user->role === 'customer' ? 10 : 12 }} characters. Changing your password signs out
                        your other sessions.</p>
                </div>
                <form method="POST" action="{{ url('/account/password') }}">@csrf<label>Current password<input
                            name="current_password" type="password" required autocomplete="current-password"></label>
                    <div class="two-fields"><label>New password<input name="password" type="password" required
                                minlength="{{ $user->role === 'customer' ? 10 : 12 }}"
                                autocomplete="new-password"></label><label>Confirm new password<input
                                name="password_confirmation" type="password" required
                                autocomplete="new-password"></label></div><button class="pink-button">Update
                        password</button>
                </form>
            </section>
        @elseif($section === 'addresses')
            <div class="section-title">
                <div class="account-section-heading"><span class="eyebrow">YOUR EVERYDAY DESTINATIONS</span>
                    <h1>Address book</h1>
                    <p>Save delivery addresses for your next order.</p>
                </div><a href="#newAddress">+ Add address</a>
            </div>
            <div class="saved-address-grid">
                @forelse($addresses as $a)
                    <article class="saved-address"><span class="address-label">{{ $a->label }} @if ($a->is_default)
                                · Default
                            @endif
                        </span>
                        <h3>{{ $a->name }}</h3>
                        <p>{{ $a->address }}<br>{{ $a->city }}, {{ $a->state }}
                            {{ $a->pincode }}<br>{{ $a->phone }}</p>
                        <details>
                            <summary>Edit address</summary>@include('market.address-form', ['a' => $a])
                        </details>
                        <form method="POST" action="{{ url('/account/addresses/' . $a->id . '/delete') }}">@csrf<button
                                class="remove-link">Remove address</button></form>
                </article>@empty<div class="form-card account-address-empty"><i class="bi bi-geo-alt"
                            aria-hidden="true"></i>
                        <h3>Where should your next favourite arrive?</h3>
                        <p>Add your first address below.</p>
                    </div>
                @endforelse
            </div>
            <section class="form-card" id="newAddress">
                <h2>Add a delivery address</h2>@include('market.address-form', ['a' => null])
            </section>
        @endif
    </div>
</section>@endsection
