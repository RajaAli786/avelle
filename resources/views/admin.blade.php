<!doctype html>
<html lang="en">

<head>
    @include('market.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>AVELLE · Admin dashboard</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin.css') }}?v=7">
    <link rel="stylesheet" href="{{ asset('admin-workspace.css') }}?v=5">
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary admin-workspace" data-initial-page="{{ old('_section', 'dashboard') }}">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item"><button class="nav-link" data-lte-toggle="sidebar"
                            aria-label="Toggle sidebar"><i class="bi bi-list fs-4"></i></button></li>
                    <li class="nav-item d-none d-sm-block"><a class="nav-link" href="#dashboard">Dashboard</a></li>
                    <li class="nav-item d-none d-sm-block"><a class="nav-link" href="{{ url('/') }}"
                            target="_blank" rel="noopener">Visit store <i class="bi bi-arrow-up-right"></i></a></li>
                </ul>
                <div class="admin-search d-none d-md-flex"><i class="bi bi-search"></i><input id="adminSearch"
                        type="search" placeholder="Search this section…" aria-label="Search current admin section">
                </div>
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3"><span class="store-online"><span></span> Store online</span></li>
                    <li class="nav-item"><span
                            class="admin-user"><span>{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span
                                class="d-none d-sm-inline">{{ auth()->user()->name }}</span></span></li>
                </ul>
            </div>
        </nav>
        <aside id="navigation" tabindex="-1" class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
            <div class="sidebar-brand"><a href="{{ url('/admin') }}" class="brand-link"><span
                        class="brand-mark">A</span><span class="brand-text">AVELLE <small>ADMIN</small></span></a></div>
            <div class="sidebar-wrapper">
                <div class="sidebar-owner"><span class="owner-avatar"><i class="bi bi-person-fill"></i></span>
                    <div><b>{{ auth()->user()->name }}</b><small><span></span> Store administrator</small></div>
                </div>
                <div class="sidebar-label">WORKSPACE</div>
                <nav aria-label="Admin navigation">
                    <ul class="nav sidebar-menu flex-column" role="menu">
                        <li class="nav-item"><a href="#dashboard" class="nav-link active" data-admin-nav="dashboard"><i
                                    class="nav-icon bi bi-grid-1x2-fill"></i>
                                <p>Dashboard</p>
                            </a></li>
                        <li class="nav-item"><a href="#products" class="nav-link" data-admin-nav="products"><i
                                    class="nav-icon bi bi-box-seam"></i>
                                <p>Products <span
                                        class="nav-badge badge text-bg-secondary">{{ $products->count() }}</span></p>
                            </a></li>
                        <li class="nav-item"><a href="#orders" class="nav-link" data-admin-nav="orders"><i
                                    class="nav-icon bi bi-bag-check"></i>
                                <p>Orders <span class="nav-badge badge text-bg-primary">{{ $count }}</span></p>
                            </a></li>
                        <li class="nav-item"><a href="#customers" class="nav-link" data-admin-nav="customers"><i
                                    class="nav-icon bi bi-people"></i>
                                <p>Users & customers</p>
                            </a></li>
                        <li class="nav-item"><a href="#offers" class="nav-link" data-admin-nav="offers"><i
                                    class="nav-icon bi bi-ticket-perforated"></i>
                                <p>Coupons & referrals @if ($activeOffers)
                                    <span class="offer-nav-light"></span>
                                    @endif
                                </p>
                            </a></li>
                        <li class="nav-item"><a href="#referral-wallet" class="nav-link" data-admin-nav="referral-wallet"><i class="nav-icon bi bi-wallet2"></i><p>Referral wallet</p></a></li>
                        <li class="nav-item"><a href="#email-delivery" class="nav-link" data-admin-nav="email-delivery"><i class="nav-icon bi bi-envelope-check"></i><p>Email delivery</p></a></li>
                        <li class="nav-item"><a href="#brands" class="nav-link" data-admin-nav="brands"><i class="nav-icon bi bi-award"></i><p>Brands</p></a></li>
                        <li class="nav-item"><a href="#categories" class="nav-link" data-admin-nav="categories"><i
                                    class="nav-icon bi bi-grid"></i>
                                <p>Categories</p>
                            </a></li>
                        <li class="nav-item"><a href="#reviews" class="nav-link" data-admin-nav="reviews"><i
                                    class="nav-icon bi bi-chat-square-heart"></i>
                                <p>Reviews @if ($actionCounts['reviews'])
                                    <span
                                        class="nav-badge badge text-bg-warning">{{ $actionCounts['reviews'] }}</span>
                                    @endif
                                </p>
                            </a></li>
                        <li class="nav-item"><a href="#returns" class="nav-link" data-admin-nav="returns"><i
                                    class="nav-icon bi bi-gear"></i>
                                <p>Returns</p>
                            </a></li>
                        <li class="nav-item"><a href="#pages" class="nav-link" data-admin-nav="pages"><i
                                    class="nav-icon bi bi-gear"></i>
                                <p>Content pages</p>
                            </a></li>
                        <li class="nav-item"><a href="#support" class="nav-link" data-admin-nav="support"><i
                                    class="nav-icon bi bi-gear"></i>
                                <p>Support</p>
                            </a></li>
                        <li class="nav-item"><a href="#store-settings" class="nav-link"
                                data-admin-nav="store-settings"><i class="nav-icon bi bi-gear"></i>
                                <p>Store settings</p>
                            </a></li>
                        <li class="nav-item"><a class="nav-link" href="#sellers" data-admin-nav="sellers"><i
                                    class="nav-icon bi bi-shop"></i>
                                <p>Sellers</p>
                            </a></li>
                        <li class="nav-item"><a class="nav-link" href="#seller-orders"
                                data-admin-nav="seller-orders"><i class="nav-icon bi bi-truck"></i>
                                <p>Seller orders & payouts</p>
                            </a></li>
                    </ul>
                </nav>
                <div class="sidebar-label mt-4">QUICK ACCESS</div><a class="sidebar-quick"
                    href="{{ url('/') }}" target="_blank" rel="noopener"><i class="bi bi-shop-window"></i>
                    Open storefront <i class="bi bi-box-arrow-up-right ms-auto"></i></a><a class="sidebar-quick"
                    href="#settings" data-admin-nav="settings"><i class="bi bi-person-gear"></i> My admin account</a>
                <form method="POST" action="{{ url('/admin/logout') }}">@csrf<button class="sidebar-quick"><i
                            class="bi bi-box-arrow-left"></i> Sign out</button></form>
                <div class="sidebar-bottom"><span>AVELLE STORE MANAGER</span><small>Powered by AdminLTE 4</small></div>
            </div>
        </aside>
        <main class="app-main" id="main" tabindex="-1">
            <div class="app-content-header">
                <div class="container-fluid d-flex align-items-center justify-content-between">
                    <div><span class="admin-eyebrow">YOUR STORE AT A GLANCE</span>
                        <h1 id="adminPageTitle">Dashboard</h1>
                    </div>
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item">Home</li>
                        <li class="breadcrumb-item active" id="adminBreadcrumb">Dashboard</li>
                    </ol>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="status"><i
                            class="bi bi-check-circle me-2"></i>{{ session('success') }}<button class="btn-close"
                            data-bs-dismiss="alert" aria-label="Dismiss"></button></div>
                    @endif @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                        @endforeach
                    </div>
                    @endif
                    @include('admin.engagement')
                    @include('admin.brands')
                    <section class="admin-page" id="dashboard">
                        <div class="dashboard-welcome">
                            <div>
                                <h2>Welcome back, {{ explode(' ', auth()->user()->name)[0] }} <span>✦</span></h2>
                                <p>Here’s what’s happening with your store.</p>
                            </div><span class="dashboard-date"><i class="bi bi-calendar3"></i>
                                {{ now()->format('d M Y') }}</span>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-xl-3">
                                <div class="small-box text-bg-primary">
                                    <div class="inner">
                                        <h3>{{ $count }}</h3>
                                        <p>Total orders</p>
                                    </div><i class="small-box-icon bi bi-bag-check"></i><a href="#orders"
                                        class="small-box-footer">View orders <i
                                            class="bi bi-arrow-right-circle"></i></a>
                                </div>
                            </div>
                            <div class="col-6 col-xl-3">
                                <div class="small-box text-bg-success">
                                    <div class="inner">
                                        <h3>₹{{ number_format($revenue) }}</h3>
                                        <p>Order value · excl. cancelled</p>
                                    </div><i class="small-box-icon bi bi-currency-rupee"></i><a href="#orders"
                                        class="small-box-footer">Order details <i
                                            class="bi bi-arrow-right-circle"></i></a>
                                </div>
                            </div>
                            <div class="col-6 col-xl-3">
                                <div class="small-box text-bg-warning">
                                    <div class="inner">
                                        <h3>{{ $customerCount }}</h3>
                                        <p>Customer accounts</p>
                                    </div><i class="small-box-icon bi bi-people"></i><a href="#customers"
                                        class="small-box-footer">View customers <i
                                            class="bi bi-arrow-right-circle"></i></a>
                                </div>
                            </div>
                            <div class="col-6 col-xl-3">
                                <div class="small-box text-bg-danger">
                                    <div class="inner">
                                        <h3>{{ $activeOffers }}</h3>
                                        <p>Active coupons & referrals</p>
                                    </div><i class="small-box-icon bi bi-ticket-perforated"></i><a href="#offers"
                                        class="small-box-footer">Manage offers <i
                                            class="bi bi-arrow-right-circle"></i></a>
                                </div>
                            </div>
                        </div>
                        @include('admin.action-centre')<div class="row g-4">
                            <div class="col-xl-8">
                                <div class="card">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <h3 class="card-title">Recent orders</h3><a href="#orders"
                                            class="btn btn-sm btn-light">View all <i
                                                class="bi bi-arrow-right"></i></a>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Order</th>
                                                    <th>Customer</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($orders->take(6) as $o)
                                                <tr data-search-row>
                                                    <td><strong><a href="{{ url('/admin') }}?record=admin-order-{{ $o->id }}#dashboard" data-open-record="admin-order-{{ $o->id }}">{{ \App\Support\OrderDisplay::reference($o) }}</a></strong><small
                                                            class="d-block text-secondary">{{ date('d M Y', strtotime($o->created_at)) }}</small>
                                                    </td>
                                                    <td>{{ $o->name }}</td>
                                                    <td>₹{{ number_format($o->total) }}</td>
                                                    <td><span
                                                            class="order-status status-{{ $o->status }}">{{ $o->status }}</span>
                                                    </td>
                                                </tr>@empty<tr>
                                                    <td colspan="4">
                                                        <div class="dashboard-empty"><i
                                                                class="bi bi-bag-heart"></i>
                                                            <h4>Your next chapter starts with an order.</h4>
                                                            <p>New customer orders will appear here.</p><a
                                                                href="{{ url('/') }}"
                                                                class="btn btn-sm btn-outline-primary">Visit
                                                                storefront</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Inventory snapshot</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="inventory-total"><span>{{ $products->count() }}</span>
                                            <div>Total products<small>{{ $products->sum('stock') }} units
                                                    available</small></div>
                                        </div>
                                        @foreach ($products->groupBy('category') as $name => $group)
                                        <div class="inventory-category">
                                            <span>{{ $name }}</span><b>{{ $group->count() }}</b>
                                            <div class="progress" style="height:5px">
                                                <div class="progress-bar"
                                                    style="width:{{ $products->count() ? round(($group->count() / $products->count()) * 100) : 0 }}%">
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="card-footer d-flex justify-content-between"><span
                                            class="small text-secondary">{{ $products->where('stock', '<=', 5)->count() }}
                                            products low or out of stock</span><a href="#products"
                                            class="small">Manage</a></div>
                                </div>
                            </div>
                        </div>
                        <div class="card offer-dashboard-card mt-4">
                            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                                <div class="offer-big-icon"><i class="bi bi-megaphone"></i></div>
                                <div class="flex-grow-1">
                                    <h3>Make your next offer impossible to miss.</h3>
                                    <p>Enable storefront visibility to show a live marquee, offer cards and subtle
                                        product highlights.</p>
                                </div><a href="#offers" class="btn btn-primary">Manage storefront offers <i
                                        class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </section>
                    @include('admin.catalogue')
                    @include('admin.categories')
                    @include('admin.sellers')
                    @include('admin.commerce')
                    <section class="admin-page" id="offers">
                        <div class="page-section-heading">
                            <div>
                                <h2>Good offers, your rules.</h2>
                                <p>Coupons, referrals and storefront visibility.</p>
                            </div><button class="btn btn-primary" data-open-editor="offer-new"><i
                                    class="bi bi-plus-lg"></i> Create offer</button>
                        </div>
                        <p>One coupon or referral per order. Product IDs appear in the wardrobe above. Category
                            names must match exactly. Referral codes are first-order only and cannot be used by
                            their owner's email.</p>
                        <div data-admin-list="offers"></div>
@foreach ($promotions->concat([null]) as $p)
                        <details class="editor-card" id="offer-{{ $p?->id ?? 'new' }}" data-search-row data-admin-record='{{ json_encode(['group' => 'offers', 'title' => $p?->code ?? 'Create coupon or referral', 'create' => !$p, 'code' => $p?->code, 'kind' => $p?->kind ? ucfirst($p->kind) : '', 'value' => $p ? ($p->type === 'percent' ? $p->value.'%' : '₹'.$p->value) : '', 'scope' => $p?->scope ? ucfirst($p->scope) : '', 'used' => $p?->used, 'status' => $p ? ($p->admin_locked ? 'Locked' : ($p->active ? 'Enabled' : 'Paused')) : '']) }}'>
                            <summary class="editor-summary">
                                @if ($p)
                                @php($isLive = $p->active && !$p->admin_locked && \App\Support\StoreOffers::sellerAvailable($p) && (!$p->expires_at || now()->lt($p->expires_at)) && ($p->usage_limit === null || $p->used < $p->usage_limit))
                                    <span
                                        class="badge {{ $isLive ? 'text-bg-success' : 'text-bg-secondary' }} me-2">{{ $isLive ? 'Live' : 'Inactive' }}</span>
                                    @endif
                                    {{ $p ? $p->code . ' · ' . $p->kind . ' · ' . ($p->active ? 'Enabled' : 'Paused') . ' · Used ' . $p->used : '+ Create coupon or referral' }}
                            </summary>
                            @if ($p?->seller_id)
                            <div class="alert alert-light border small">Seller offer:
                                <b>{{ ($sellers ?? collect())->firstWhere('id', $p->seller_id)?->shop_name ?? 'Seller #' . $p->seller_id }}</b>.
                                This coupon applies only to that seller’s eligible products.
                                {{ $p->admin_locked ? 'Locked by admin; seller cannot reactivate it.' : '' }}
                            </div>
                            @endif
                            <form method="POST" action="{{ url('/') }}/admin/promotions">
                                @csrf<input type="hidden" name="_section" value="offers"><input
                                    type="hidden" name="id" value="{{ $p?->id }}">
                                <div class="editor">
                                    <label>Code<input class="form-control" name="code"
                                            value="{{ $p?->code }}" required
                                            pattern="[A-Za-z0-9_-]{3,40}"></label>
                                    <label>Offer kind<select class="form-select" name="kind">
                                            @foreach (['coupon', 'referral'] as $v)
                                            <option @selected($p?->kind === $v)>{{ $v }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Referral owner email<input class="form-control" name="owner_email"
                                            type="email" value="{{ $p?->owner_email }}"></label>
                                    <label>Discount type<select class="form-select" name="type">
                                            @foreach (['percent', 'fixed'] as $v)
                                            <option @selected($p?->type === $v)>{{ $v }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Discount value<input class="form-control" name="value"
                                            type="number" min="1" required
                                            value="{{ $p?->value ?? 10 }}"></label>
                                    <label>Minimum bag subtotal ₹<input class="form-control" name="minimum"
                                            type="number" min="0" required
                                            value="{{ $p?->minimum ?? 0 }}"></label>
                                    <label>Maximum discount ₹ (optional)<input class="form-control"
                                            name="maximum" type="number" min="1"
                                            value="{{ $p?->maximum }}"></label>
                                    <label>Applies to<select class="form-select" name="scope">
                                            @foreach (['store', 'category', 'product'] as $v)
                                            <option @selected($p?->scope === $v)>{{ $v }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Category names or product IDs, comma separated<input
                                            class="form-control" name="targets"
                                            value="{{ $p ? implode(', ', json_decode($p->targets ?? '[]', true)) : '' }}"></label>
                                    <label>Total usage limit (optional)<input class="form-control"
                                            name="usage_limit" type="number" min="1"
                                            value="{{ $p?->usage_limit }}"></label>
                                    <label>Expires at · India time (optional)<input class="form-control"
                                            name="expires_at" type="datetime-local"
                                            value="{{ $p?->expires_at ? date('Y-m-d\TH:i', strtotime($p->expires_at)) : '' }}"></label>
                                    <label class="wide-field">Storefront headline<input class="form-control"
                                            name="banner_text" maxlength="180"
                                            value="{{ $p?->banner_text }}"
                                            placeholder="Optional: e.g. Your everyday favourites, now 10% off"><small>Leave
                                            blank to generate a headline from the
                                            discount.</small></label><label>Offer animation<select
                                            class="form-select" name="banner_mode">
                                            @foreach (['both' => 'Marquee + soft pulse', 'marquee' => 'Marquee only', 'pulse' => 'Soft pulse only', 'static' => 'Static offer card'] as $value => $label)
                                            <option value="{{ $value }}"
                                                @selected(($p?->banner_mode ?? 'both') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label><label class="visibility-field"><input type="hidden"
                                            name="show_banner" value="0"><span
                                            class="form-check form-switch"><input class="form-check-input"
                                                name="show_banner" type="checkbox" value="1"
                                                @checked($p?->show_banner ?? true)><span>Show on
                                                storefront</span></span><small>Public coupons only. Referral
                                            codes stay private.</small></label>
                                </div><button class="btn btn-primary">Save offer</button>
                            </form>
                            @if ($p)
                            <form method="POST"
                                action="{{ url('/') }}/admin/promotions/{{ $p->id }}/toggle"
                                style="margin-top:10px">@csrf<button
                                    class="btn btn-primary">{{ $p->active ? 'Pause offer' : 'Activate offer' }}</button>
                            </form>
                            @endif
                        </details>
                        @endforeach
                    </section>
                    <section class="admin-page" id="orders">
                        <div class="page-section-heading"><div><h2>Order management</h2><p>View order details, track fulfilment and record collected payments.</p></div></div>
                        <p class="text-secondary">Latest 100 orders</p>
                        <div data-admin-list="orders"></div>
@foreach ($orders as $o)
                            <details class="editor-card" id="admin-order-{{ $o->id }}" data-admin-record='{{ json_encode(['group' => 'orders', 'title' => \App\Support\OrderDisplay::reference($o), 'number' => \App\Support\OrderDisplay::reference($o), 'customer' => $o->name, 'date' => date('d M Y', strtotime($o->created_at)), 'amount' => '₹'.number_format($o->total), 'status' => ucfirst($o->status), 'payment' => $o->payment_status === 'paid' ? 'Collected' : 'Unpaid']) }}' data-search-row>
                                <summary>{{ \App\Support\OrderDisplay::reference($o) }} · {{ $o->name }}</summary>
                                <div class="admin-order-overview">
                                    <section><h3>Customer and delivery</h3><p><strong>{{ $o->name }}</strong><br>{{ $o->email }}<br>{{ $o->phone }}<br>{{ $o->address }}, {{ $o->city }} {{ $o->pincode }}</p></section>
                                    <section><h3>Order summary</h3><p>Placed {{ date('d M Y, h:i A', strtotime($o->created_at)) }} IST<br>Subtotal ₹{{ number_format($o->subtotal) }}<br>Shipping ₹{{ number_format($o->shipping) }}<br>Discount ₹{{ number_format($o->discount) }} {{ $o->code }}<br>Wallet credit ₹{{ number_format($o->wallet_spent ?? 0) }}<br><strong>Total ₹{{ number_format($o->total) }}</strong></p></section>
                                </div>
                                @include('admin.whatsapp-action', ['whatsappUrl' => \App\Support\OrderWhatsApp::url($o)])
                                <section class="admin-order-items"><h3>Items</h3>
                                    @foreach (\App\Support\OrderDisplay::items($o) as $item)
                                        <div class="admin-order-item"><img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="64" height="80"><div><strong>{{ $item['name'] }}</strong><p>{{ $item['size'] }} · Qty {{ $item['quantity'] }}</p></div><strong>₹{{ number_format($item['price'] * $item['quantity']) }}</strong></div>
                                    @endforeach
                                </section>
                                @if ($o->status !== 'cancelled')
                                    <h3 class="mt-4">Update fulfilment</h3>
                                            <form method="POST"
                                                action="{{ url('/') }}/admin/orders/{{ $o->id }}">
                                                @csrf<select class="form-select" name="status">
                                                    @foreach (['placed', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $s)
                                                    <option @selected($o->status === $s)>
                                                        {{ $s }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <label>Carrier<input class="form-control" name="carrier"
                                                        value="{{ $o->carrier }}"></label><label>Tracking
                                                    number<input class="form-control"
                                                        name="tracking_number"
                                                        value="{{ $o->tracking_number }}"></label><label>Tracking
                                                    URL<input type="url" class="form-control"
                                                        name="tracking_url"
                                                        value="{{ $o->tracking_url }}"></label><label>COD
                                                    payment<select class="form-select"
                                                        name="payment_status">
                                                        <option value="unpaid"
                                                            @selected($o->payment_status === 'unpaid')>Unpaid</option>
                                                        <option value="paid"
                                                            @selected($o->payment_status === 'paid')>Collected</option>
                                                    </select></label><button type="submit"
                                                    class="btn btn-primary mt-2">Update</button>
                                            </form>
                                @else
                                    <p class="alert alert-secondary">This order is cancelled.</p>
                                @endif
                            </details>
                        @endforeach
                    </section>
                    <section class="admin-page" id="customers">
                        <div class="page-section-heading">
                            <div>
                                <h2>Users & customers</h2>
                                <p>Manage names, email addresses, password resets and account access. Latest 100
                                    users.</p>
                            </div>
                            <div class="d-flex gap-2"><span class="badge text-bg-primary">{{ $userCount }}
                                    accounts</span><span class="badge text-bg-secondary">{{ $disabledCount }}
                                    disabled</span></div>
                        </div>
                        <div class="alert alert-light border small"><i
                                class="bi bi-info-circle me-2"></i>Disabling an account ends its sessions and
                            blocks sign-in. Resetting a password ends previous sessions. Existing passwords are
                            never displayed.</div>
                        <div data-admin-list="customers"></div>
<div class="card table-responsive mb-4 admin-legacy-user-list">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email / Mobile</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers as $customer)
                                    <tr data-search-row>
                                        <td><strong>{{ $customer->name }}</strong>
                                            @if ($customer->id === auth()->id())
                                            <span class="badge text-bg-light ms-1">You</span>
                                            @endif
                                        </td>
                                        <td>{{ $customer->email }}<small
                                                class="text-secondary d-block">{{ $customer->phone ?? '—' }}</small>
                                        </td>
                                        <td><span
                                                class="badge {{ $customer->role === 'admin' ? 'text-bg-primary' : 'text-bg-light' }}">{{ ucfirst($customer->role) }}</span>
                                        </td>
                                        <td><span
                                                class="badge {{ $customer->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $customer->is_active ? 'Enabled' : 'Disabled' }}</span>
                                        </td>
                                        <td>{{ $customer->created_at->format('d M Y') }}</td>
                                        <td><button class="btn btn-sm btn-light"
                                                data-open-editor="user-{{ $customer->id }}"><i
                                                    class="bi bi-person-gear"></i> Manage</button></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @foreach ($customers as $customer)
                        <details class="editor-card" id="user-{{ $customer->id }}" data-search-row data-admin-record='{{ json_encode(['group' => 'customers', 'title' => $customer->name, 'name' => $customer->name, 'email' => $customer->email, 'role' => ucfirst($customer->role), 'status' => $customer->is_active ? 'Enabled' : 'Disabled', 'joined' => $customer->created_at->format('d M Y')]) }}'>
                            <summary class="editor-summary"><i class="bi bi-person-circle"></i>
                                {{ $customer->name }} · {{ $customer->email }} <span
                                    class="badge {{ $customer->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $customer->is_active ? 'Enabled' : 'Disabled' }}</span>
                            </summary>
                            <form method="POST" action="{{ url('/admin/users/' . $customer->id) }}">
                                @csrf<input type="hidden" name="_section" value="customers">
                                <div class="editor"><label>Full name<input class="form-control"
                                            name="name" value="{{ $customer->name }}" required
                                            maxlength="100" autocomplete="off"></label><label>Login
                                        email<input class="form-control" name="email" type="email"
                                            value="{{ $customer->email }}" required maxlength="200"
                                            autocomplete="off"></label><label>Mobile number<input
                                            class="form-control" name="phone"
                                            value="{{ $customer->phone }}"
                                            pattern="[6-9][0-9]{9}"></label><label>New password
                                        (optional)<input class="form-control" name="password" type="password"
                                            minlength="{{ $customer->role === 'admin' ? 12 : 10 }}"
                                            autocomplete="new-password"><small>Leave blank to keep the current
                                            password. Minimum {{ $customer->role === 'admin' ? 12 : 10 }}
                                            characters.</small></label><label>Confirm new password<input
                                            class="form-control" name="password_confirmation" type="password"
                                            autocomplete="new-password"></label>
                                    @if ($customer->id === auth()->id())
                                    <label>Your current password<input class="form-control"
                                            name="current_password" type="password"
                                            autocomplete="current-password"><small>Required when changing
                                            your own password.</small></label><input type="hidden"
                                        name="is_active" value="1">
                                    <div class="account-access-note"><i class="bi bi-shield-check"></i>
                                        Your own admin account stays enabled.</div>@else<label>Account
                                        access<select class="form-select" name="is_active">
                                            <option value="1" @selected($customer->is_active)>Enabled —
                                                can sign in</option>
                                            <option value="0" @selected(!$customer->is_active)>Disabled —
                                                access blocked</option>
                                        </select><small>Disabling also ends existing
                                            sessions.</small></label>
                                    @endif
                                </div>
                                <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save
                                    account changes</button>
                            </form>
                        </details>
                        @endforeach
                    </section>
                    <section class="admin-page" id="settings">
                        <div class="page-section-heading">
                            <div>
                                <h2>My admin account</h2>
                                <p>Update your administrator name, login email and password.</p>
                            </div><span class="badge text-bg-primary">Administrator</span>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="bi bi-person-lock me-2"></i> Account details &
                                    security</h3>
                            </div>
                            <form class="card-body" method="POST"
                                action="{{ url('/admin/users/' . auth()->id()) }}">@csrf<input type="hidden"
                                    name="_section" value="settings"><input type="hidden" name="is_active"
                                    value="1">
                                <div class="editor"><label>Full name<input class="form-control" name="name"
                                            value="{{ old('name', auth()->user()->name) }}" required
                                            maxlength="100" autocomplete="name"></label><label>Login email<input
                                            class="form-control" name="email" type="email"
                                            value="{{ old('email', auth()->user()->email) }}" required
                                            autocomplete="email"></label><label>Mobile number<input
                                            class="form-control" name="phone"
                                            value="{{ old('phone', auth()->user()->phone) }}"
                                            pattern="[6-9][0-9]{9}"></label><label>Current password<input
                                            class="form-control" name="current_password" type="password"
                                            autocomplete="current-password"><small>Required only if setting a new
                                            password.</small></label><label>New password<input class="form-control"
                                            name="password" type="password" minlength="12"
                                            autocomplete="new-password"><small>Minimum 12 characters. Leave blank
                                            to keep it.</small></label><label>Confirm new password<input
                                            class="form-control" name="password_confirmation" type="password"
                                            autocomplete="new-password"></label></div>
                                <div class="alert alert-light border small"><i
                                        class="bi bi-shield-check me-2"></i>Your own account stays enabled.
                                    Updating your password signs out other sessions while keeping this session
                                    active.</div><button class="btn btn-primary">Save my admin account</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </main>
        <footer class="app-footer"><span class="float-end d-none d-sm-inline">Raja Ali</span><strong>© 2026 AVELLE.</strong> Your store, in control.</footer>
    </div>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/adminlte/adminlte.min.js') }}"></script>
    <script src="{{ asset('admin.js') }}?v=10"></script>
<script src="{{ asset('admin-records.js') }}?v=2"></script>
</body>

</html>