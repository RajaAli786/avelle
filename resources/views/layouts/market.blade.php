@php
$settings = $settings ?? \App\Support\Shop::settings();
$navCategories =
$navCategories ?? \App\Support\Shop::categories()->whereIn('id', \App\Support\Shop::activeCategoryIds());
$liveOffers = $liveOffers ?? \App\Support\StoreOffers::visible();
$context = [
'base' => url('/'),
'referral_code' => session('referral_code'),
'csrf' => csrf_token(),
'user' => auth()
->user()
?->only(['id', 'name', 'email', 'phone', 'address', 'city', 'pincode']),
'settings' => $settings,
'offers' => $liveOffers,
'wishlist' => auth()->check()
? \Illuminate\Support\Facades\DB::table('wishlists')->where('user_id', auth()->id())->pluck('product_id')
: [],
];
$pageProducts = isset($product)
? [$product]
: (isset($featured)
? $featured
: (isset($products) && method_exists($products, 'getCollection')
? $products->getCollection()
: []));
@endphp
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">@include('market.seo-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('market.css') }}?v=11"><link rel="stylesheet" href="{{ asset('engagement.css') }}?v=1">@stack('head')
</head>

<body>
    <div class="topline">
        <div class="shell"><span>Made for women. Loved by every version of you.</span>
            <div><a href="{{ url('/seller/apply') }}">Sell on Avelle</a><a href="{{ url('/track-order') }}">Track
                    order</a><a href="{{ url('/pages/contact') }}">Help & support</a><span>India · INR ₹</span></div>
        </div>
    </div>
    <header class="market-header">
        <div class="header-main shell"><a class="market-logo" href="{{ url('/') }}" aria-label="Avelle home"><span
                    class="logo-flower">✳</span>avelle<span class="logo-dot">.</span></a>
            <form class="global-search" action="{{ url('/shop') }}" method="GET"><svg viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <circle cx="10.5" cy="10.5" r="6.5" />
                    <path d="m16 16 5 5" />
                </svg><input type="search" name="q" value="{{ request('q') }}"
                    placeholder="Try “kurta”, “dress” or “everyday bag”" aria-label="Search products"><button
                    type="submit">Search</button></form>
            <div class="market-actions"><a href="{{ url('/account') }}"><svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <circle cx="12" cy="7" r="4" />
                        <path d="M4 22v-3a8 8 0 0 1 16 0v3" />
                    </svg><span>{{ auth()->check() ? (preg_split('/\s+/u', trim(auth()->user()->name))[0] ?: 'My account') : 'Sign in' }}</span></a><a
                    href="{{ url('/wishlist') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.6" aria-hidden="true">
                        <path d="M20 4c-3-2-6 0-8 2-2-2-5-4-8-2-5 4 0 10 8 17 8-7 13-13 8-17Z" />
                    </svg><span>Wishlist</span><small data-wish-count hidden>0</small></a><a
                    href="{{ url('/cart') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.6" aria-hidden="true">
                        <path d="M4 7h16l1 15H3L4 7Z" />
                        <path d="M8 8V5a4 4 0 0 1 8 0v3" />
                    </svg><span>Bag</span><small data-cart-count hidden>0</small></a></div>
        </div>
        <nav class="category-nav shell" aria-label="Shopping categories"><a
                class="{{ request()->is('shop') && !request('audience') ? 'selected' : '' }}" href="{{ url('/shop') }}">All
                products</a><a href="{{ url('/shop') }}?audience=women">Women</a><a
                href="{{ url('/shop') }}?audience=girls">Girls</a>
            @foreach ($navCategories->whereNull('parent_id')->take(7) as $nav)
            <a href="{{ url('/category/' . $nav->slug) }}">{{ $nav->name }}</a>
            @endforeach
            <a class="sale-link" href="{{ url('/offers') }}">
                <span class="{{ count($liveOffers) ? 'status-light' : '' }}"></span>Offers</a>
        </nav>
    </header>
    <div class="live-offer-bar" id="liveOfferBar" @if (!count($liveOffers)) hidden @endif><span
            class="offer-bar-label"><span class="status-light"></span> LIVE OFFERS</span>
        <div class="offer-bar-window">
            <div id="offerBarTrack" class="offer-bar-track"></div>
        </div><button type="button" id="offerAnimationToggle" aria-label="Pause offer animation"
            aria-pressed="false">Ⅱ</button>
    </div>
    <main id="main">
        @if (session('success'))
        <div class="shell alert success" role="status">{{ session('success') }}</div>
        @endif @if ($errors->any())
        <div class="shell alert error" role="alert">
            @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif
        @auth
            @if (!auth()->user()->hasVerifiedEmail() && !request()->is('email/*'))
                <div class="verification-banner">Confirm your email before placing your first order.<a href="{{ url('/email/verify') }}">Verify email →</a></div>
            @endif
        @endauth
        @yield('content')
    </main>
    <footer class="market-footer">
        <div class="shell footer-grid">
            <div><a class="market-logo" href="{{ url('/') }}"><span class="logo-flower">✳</span>avelle<span
                        class="logo-dot">.</span></a>
                <p>Your style. Your everyday.<br>A world of finds for women & girls.</p><span
                    class="footer-country">INDIA / INR ₹</span>
            </div>
            <div>
                <h3>Shop with us</h3><a href="{{ url('/shop') }}?audience=women">Women’s collection</a><a
                    href="{{ url('/shop') }}?audience=girls">Girls’ collection</a><a
                    href="{{ url('/categories') }}">All categories</a><a href="{{ url('/offers') }}">Offers &
                    coupons</a>
            </div>
            <div>
                <h3>Your Avelle</h3><a href="{{ url('/seller/apply') }}">Become a seller</a><a
                    href="{{ url('/seller') }}">Seller dashboard</a><a href="{{ url('/account') }}">My account &
                    orders</a><a href="{{ url('/wishlist') }}">My wishlist</a><a href="{{ url('/cart') }}">Shopping
                    bag</a><a href="{{ url('/track-order') }}">Track an order</a>
            </div>
            <div>
                <h3>Here to help</h3>
                @foreach (\Illuminate\Support\Facades\DB::table('pages')->where('active', true)->get() as $footerPage)
                <a href="{{ url('/pages/' . $footerPage->slug) }}">{{ $footerPage->title }}</a>
                @endforeach
            </div>
            <div>
                <h3>Let’s stay in touch</h3>
                <p>Questions about a product or your order?<br>Our support form is always open.</p><a
                    class="footer-contact" href="{{ url('/pages/contact') }}">Contact us ↗</a>
                @if ($settings['support_email'])
                <a href="mailto:{{ $settings['support_email'] }}">{{ $settings['support_email'] }}</a>
                @endif @if ($settings['support_phone'])
                <span>{{ $settings['support_phone'] }}</span>
                @endif
            </div>
        </div>
        <div class="shell footer-bottom"><span>© {{ now()->year }} {{ $settings['store_name'] }}. All rights
                reserved.</span><span>Cash on delivery · Account-protected shopping</span></div>
    </footer>
    <dialog id="galleryZoom" class="zoom-dialog"><button type="button" data-close-dialog
            aria-label="Close image">×</button><img id="zoomImage" alt="Enlarged product image"></dialog>
    <div class="market-toast" id="marketToast" role="status"></div>
    <script type="application/json" id="shopContext">
        @json($context)
    </script>
    <script type="application/json" id="pageProducts">
        @json($pageProducts)
    </script>
    <script src="{{ asset('market.js') }}?v=10"></script><script src="{{ asset('auth-return.js') }}?v=1"></script>@stack('scripts')
</body>

</html>