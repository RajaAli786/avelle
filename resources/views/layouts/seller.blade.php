<!doctype html>
<html lang="en">

<head>
    @include('market.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Seller workspace · Avelle')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('seller-dashboard.css') }}?v=3">
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary seller-console"
    data-initial-page="{{ old('_section', 'seller-overview') }}">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand">
            <div class="container-fluid"><button class="nav-link seller-menu-toggle" data-lte-toggle="sidebar"
                    aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                <div class="seller-breadcrumb"><span>Workspace</span><i class="bi bi-chevron-right"></i><b
                        id="sellerBreadcrumb">Overview</b></div><label class="seller-search"><i
                        class="bi bi-search"></i><input id="sellerSearch" type="search"
                        placeholder="Search this section…"
                        aria-label="Search current seller section"><span>Search</span></label>
                <div class="seller-top-actions"><a href="{{ url('/') }}" class="seller-store-link" target="_blank"
                        rel="noopener"><i class="bi bi-box-arrow-up-right"></i><span>View store</span></a><a
                        class="seller-top-profile" href="#seller-account"><span
                            class="seller-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span><b>{{ auth()->user()->name }}</b><small>Seller
                                account</small></span><i class="bi bi-chevron-down"></i></a></div>
            </div>
        </nav>
        <aside id="navigation" tabindex="-1" class="app-sidebar seller-sidebar" data-bs-theme="dark">
            <div class="sidebar-brand"><a href="{{ url('/seller') }}" class="brand-link"><span
                        class="seller-brand-icon">a<span>.</span></span>
                    <div>avelle<span class="seller-brand-sub">SELLER CENTRAL</span></div>
                </a></div>
            <div class="sidebar-wrapper">
                <div class="seller-shop"><span class="seller-shop-symbol"><i class="bi bi-shop"></i></span>
                    <div><b>{{ $seller->shop_name }}</b><small><span
                                class="seller-status-dot {{ $seller->status === 'approved' ? 'approved' : '' }}"></span>{{ ucfirst($seller->status) }}
                            seller</small></div>
                </div><span class="seller-nav-label">MANAGE YOUR BUSINESS</span>
                <nav aria-label="Seller navigation">
                    <ul class="nav sidebar-menu flex-column" role="menu">
                        @foreach (['seller-overview' => ['bi-grid-1x2', 'Overview'], 'products' => ['bi-box-seam', 'Products'], 'seller-orders' => ['bi-bag-check', 'Orders'], 'seller-offers' => ['bi-ticket-perforated', 'Offers & coupons'], 'seller-earnings' => ['bi-wallet2', 'Earnings'], 'seller-business' => ['bi-building', 'Business details'], 'seller-account' => ['bi-person-gear', 'Account & security']] as $key => $nav)
                        <li class="nav-item"><a href="#{{ $key }}" class="nav-link"
                                data-seller-nav="{{ $key }}"><i
                                    class="nav-icon bi {{ $nav[0] }}"></i>
                                <p>{{ $nav[1] }}@if ($key === 'products')
                                    <span class="seller-nav-count">{{ $products->count() }}</span>
                                    @endif
                                </p>
                            </a></li>
                        @endforeach
                    </ul>
                </nav>
                <div class="seller-sidebar-note"><span><i class="bi bi-stars"></i> YOUR NEXT CHAPTER</span><strong>Make
                        your collection<br>someone’s favourite.</strong>
                    <p>Clear photos and thoughtful details help your products stand out.</p><a href="#products">Manage
                        catalogue <i class="bi bi-arrow-up-right"></i></a>
                </div>
                <div class="seller-sidebar-bottom"><a href="{{ url('/pages/contact') }}" target="_blank"
                        rel="noopener"><i class="bi bi-question-circle"></i> Help & support <i
                            class="bi bi-arrow-up-right ms-auto"></i></a><a href="#seller-account"><i
                            class="bi bi-shield-lock"></i> Account & password</a>
                    <form method="POST" action="{{ url('/account/logout') }}">@csrf<button><i
                                class="bi bi-box-arrow-left"></i> Sign out</button></form><small>AVELLE · SELLER
                        WORKSPACE</small>
                </div>
            </div>
        </aside>
        <main class="app-main" id="main" tabindex="-1">
            <div class="seller-page-header">
                <div><span class="seller-eyebrow">YOUR BUSINESS, ALL TOGETHER</span>
                    <h1 id="sellerPageTitle">Overview</h1>
                    <p id="sellerPageDescription">A clear view of your products, orders and next steps.</p>
                </div>
                <div class="seller-header-meta"><span class="seller-date"><i
                            class="bi bi-calendar4"></i>{{ now()->format('d M Y') }}</span>
                    @if ($seller->status === 'approved')
                    <button class="btn btn-primary" data-open-editor="product-new"><i class="bi bi-plus-lg"></i> Add
                        product</button>
                    @endif
                </div>
            </div>
            <div class="app-content seller-content">
                <div class="container-fluid">
                    @if (session('success'))
                    <div class="alert alert-success" role="status"><i
                            class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
                    @endif @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                        <p class="mb-1">{{ $error }}</p>
                        @endforeach
                    </div>@endif @if ($seller->admin_note)
                    <div class="alert alert-info"><b>Message from admin:</b> {{ $seller->admin_note }}
                    </div>
                    @endif @if ($seller->status !== 'approved')
                    <div class="alert alert-warning">Your seller profile is {{ $seller->status }}.
                        Product management and dispatch are available after approval. Contact store
                        support if you need help.</div>
                    @endif @yield('content')<p id="sellerSearchEmpty"
                        class="seller-search-empty" hidden>No matches in this section. Try another search.
                    </p><noscript>
                        <style>
                            .seller-page {
                                display: block
                            }
                        </style>
                        <div class="alert alert-info">Enable JavaScript for sidebar navigation. All
                            sections remain listed below.</div>
                    </noscript>
                </div>
            </div>
        </main>
        <footer class="app-footer seller-footer"><span>© {{ now()->year }} Avelle. Your store, your next
                chapter.</span><span>Seller Central <span class="footer-separator">·</span> Powered by AdminLTE
                4</span></footer>
    </div>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/adminlte/adminlte.min.js') }}"></script>
    <script src="{{ asset('admin.js') }}?v=6"></script>
    <script src="{{ asset('seller-dashboard.js') }}?v=5"></script>
</body>

</html>