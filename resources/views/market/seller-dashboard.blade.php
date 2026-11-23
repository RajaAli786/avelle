@extends('layouts.seller') @section('title', $seller->shop_name . ' · Seller Central')
@php
    $totalProducts = $products->count();
    $liveProducts =
        $seller->status === 'approved'
            ? $products->where('active', true)->where('seller_status', 'approved')->count()
            : 0;
    $pendingProducts = $products->where('seller_status', 'pending')->count();
    $lowStock = $products->where('stock', '<=', 5)->count();
    $reviewPercent = $totalProducts ? round(($liveProducts / $totalProducts) * 100) : 0;
    $recentShipments = $shipments->getCollection()->take(5);
    $sellerOffers = $sellerOffers ?? collect();
@endphp
@section('content')<section class="seller-page" id="seller-overview">
        <div class="seller-welcome">
            <div><span class="seller-welcome-tag"><i class="bi bi-sun"></i> YOUR SELLER SPACE</span>
                <h2>Welcome back, {{ explode(' ', auth()->user()->name)[0] }}<span class="welcome-spark">✦</span></h2>
                <p>Here’s what’s happening with <b>{{ $seller->shop_name }}</b>.</p><a href="#products">Let’s grow your
                    catalogue <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="seller-welcome-art" aria-hidden="true">
                <div class="art-grid"></div>
                <div class="art-bag"><i class="bi bi-bag-heart"></i><span>avelle.</span></div><span
                    class="art-star star-one">✦</span><span class="art-star star-two">✦</span><span class="art-label"><i
                        class="bi bi-heart-fill"></i> made to be loved</span>
            </div>
        </div>
        <div class="seller-stat-grid">
            <article class="seller-stat">
                <div class="stat-top"><span class="stat-icon stat-blue"><i class="bi bi-box-seam"></i></span><span
                        class="stat-caption">Catalogue</span></div><span class="stat-label">Total
                    products</span><strong>{{ $totalProducts }}</strong><small><span
                        class="mini-dot blue"></span>{{ $liveProducts }} approved & visible</small>
            </article>
            <article class="seller-stat">
                <div class="stat-top"><span class="stat-icon stat-purple"><i class="bi bi-bag-check"></i></span><span
                        class="stat-caption">Orders</span></div><span class="stat-label">Order
                    records</span><strong>{{ $summary->orders }}</strong><small>Excludes cancelled shipments</small>
            </article>
            <article class="seller-stat">
                <div class="stat-top"><span class="stat-icon stat-pink"><i class="bi bi-graph-up-arrow"></i></span><span
                        class="stat-caption">After commission</span></div><span class="stat-label">Estimated
                    earnings</span><strong>₹{{ number_format($summary->estimated) }}</strong><small>Includes orders still in
                    progress</small>
            </article>
            <article class="seller-stat">
                <div class="stat-top"><span class="stat-icon stat-green"><i class="bi bi-wallet2"></i></span><span
                        class="stat-caption">Recorded</span></div><span class="stat-label">External
                    payouts</span><strong>₹{{ number_format($summary->recorded) }}</strong><small><a
                        href="#seller-earnings">View payout records <i class="bi bi-arrow-up-right"></i></a></small>
            </article>
        </div>
        <div class="seller-overview-grid">
            <section class="seller-card">
                <div class="seller-card-header">
                    <div>
                        <h3>Recent orders</h3>
                        <p>Your latest shipment records</p>
                    </div><a href="#seller-orders">View all <i class="bi bi-arrow-up-right"></i></a>
                </div>
                @if ($recentShipments->count())
                    <div class="table-responsive">
                        <table class="table seller-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Your amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentShipments as $shipment)
                                    <tr>
                                        <td><a href="#seller-orders"
                                                data-open-editor="shipment-{{ $shipment->id }}">#{{ $shipment->id }}</a><small>{{ \Illuminate\Support\Carbon::parse($shipment->created_at)->format('d M Y') }}</small>
                                        </td>
                                        <td><span
                                                class="seller-status {{ $shipment->status }}">{{ ucfirst($shipment->status) }}</span>
                                        </td>
                                        <td class="fw-semibold">₹{{ number_format($shipment->seller_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                </div>@else<div class="seller-empty"><span class="empty-icon"><i class="bi bi-bag-heart"></i></span>
                        <h4>Your first order is on its way.</h4>
                        <p>Add your collection, get it approved and give shoppers something to love.</p><a href="#products"
                            class="btn btn-light">Explore your catalogue <i class="bi bi-arrow-right"></i></a>
                    </div>
                @endif
            </section>
            <section class="seller-card catalogue-health">
                <div class="seller-card-header">
                    <div>
                        <h3>Catalogue health</h3>
                        <p>A little attention goes a long way</p>
                    </div><i class="bi bi-sliders2"></i>
                </div>
                <div class="catalogue-ring" style="--progress:{{ $reviewPercent }}%">
                    <div><strong>{{ $liveProducts }}<small>/ {{ $totalProducts }}</small></strong><span>live
                            products</span></div>
                </div>
                <div class="health-rows"><a href="#products"><span><i class="mini-dot blue"></i>Approved &
                            visible</span><b>{{ $liveProducts }}</b></a><a href="#products"><span><i
                                class="mini-dot amber"></i>Awaiting review</span><b>{{ $pendingProducts }}</b></a><a
                        href="#products"><span><i class="mini-dot rose"></i>Low stock (5 or
                            fewer)</span><b>{{ $lowStock }}</b></a></div>
            </section>
        </div>
        <div class="seller-bottom-grid">
            <section class="seller-card seller-next"><span class="stat-icon stat-purple"><i class="bi bi-stars"></i></span>
                <div>
                    <h3>Your next great offer starts here.</h3>
                    <p>Create a coupon for your shop, a category or selected products.</p><a href="#seller-offers">Create an
                        offer <i class="bi bi-arrow-right"></i></a>
                </div>
            </section>
            <section class="seller-card commission-card"><span class="eyebrow">YOUR CURRENT
                    COMMISSION</span><strong>{{ number_format($seller->commission_rate, 2) }}<small>%</small></strong>
                <p>Applied to discounted merchandise. Shipping is excluded. Existing orders keep their original rate.</p><a
                    href="#seller-earnings">Understand your earnings <i class="bi bi-arrow-up-right"></i></a>
            </section>
        </div>
    </section>
    <div class="seller-page" data-page="products">
        @if ($seller->status === 'approved')
            @include('admin.catalogue')
        @else
            <section class="seller-card p-4">
                <h3>Your catalogue is waiting.</h3>
                <p>Product management opens after your seller account is approved.</p>
            </section>
        @endif
    </div>
    <section class="seller-page" id="seller-orders">
        <div class="seller-section-intro">
            <div>
                <h2>Every order, handled with care.</h2>
                <p>Only your own shipment items are shown. {{ $shipments->total() }} shipment records.</p>
            </div><span class="seller-pill"><i class="bi bi-truck"></i> Manual fulfilment</span>
        </div>
        @forelse($shipments as $shipment)
            <details class="seller-order-card editor-card" id="shipment-{{ $shipment->id }}" data-search-row>
                <summary class="shipment-summary"><span class="shipment-symbol"><i class="bi bi-box-seam"></i></span><span
                        class="shipment-reference"><b>Shipment
                            #{{ $shipment->id }}</b><small>{{ $shipment->reference }}</small></span><span
                        class="seller-status {{ $shipment->status }}">{{ ucfirst($shipment->status) }}</span><strong>₹{{ number_format($shipment->seller_amount) }}</strong><i
                        class="bi bi-chevron-down"></i></summary>
                <div class="shipment-body">
                    <div class="shipment-columns">
                        <div><span class="detail-eyebrow">YOUR ITEMS</span>
                            @foreach (json_decode($shipment->items) as $line)
                                <p><b>{{ $line->name }}</b><br><span class="text-secondary">{{ $line->size }} /
                                        {{ $line->color ?? '' }} · Qty {{ $line->quantity }}</span></p>
                            @endforeach
                        </div>
                        <div><span class="detail-eyebrow">DELIVER TO</span>
                            <p><b>{{ $shipment->customer_name }}</b><br>{{ $shipment->customer_phone }}<br>{{ $shipment->address }},
                                {{ $shipment->city }}, {{ $shipment->state }} {{ $shipment->pincode }}</p><small
                                class="text-secondary">Use delivery details only to fulfil this shipment.</small>
                        </div>
                    </div>
                    <div class="shipment-money"><span>Subtotal<b>₹{{ $shipment->subtotal }}</b></span><span>Discount<b>−
                                ₹{{ $shipment->discount }}</b></span><span>Commission
                            ({{ $shipment->commission_rate }}%)<b>−
                                ₹{{ $shipment->commission_amount }}</b></span><span>Your estimated
                            amount<b>₹{{ $shipment->seller_amount }}</b></span></div>
                    @if ($seller->status === 'approved' && !in_array($shipment->status, ['cancelled', 'delivered']))
                        @include('market.shipment-form', [
                            'shipment' => $shipment,
                            'action' => url('/seller/shipments/' . $shipment->id),
                        ])
                    @else
                        <p class="mb-0">{{ $shipment->carrier }} · {{ $shipment->tracking_number }}</p>
                    @endif
                </div>
        </details>@empty<div class="seller-card seller-empty"><span class="empty-icon"><i
                        class="bi bi-bag-check"></i></span>
                <h3>A fresh start for your shop.</h3>
                <p>Your incoming orders will appear here, ready to confirm and dispatch.</p>
            </div>
        @endforelse
        <div class="seller-pagination">
            {{ $shipments->fragment('seller-orders')->links() }}</div>
    </section>
    <section class="seller-page" id="seller-earnings">
        <div class="seller-section-intro">
            <div>
                <h2>Know where your earnings stand.</h2>
                <p>Estimates and externally processed payout records, in one place.</p>
            </div><span class="seller-pill"><i class="bi bi-info-circle"></i> No automatic transfers</span>
        </div>
        <div class="seller-stat-grid earnings-stats">
            <article class="seller-stat"><span class="stat-label">Estimated
                    earnings</span><strong>₹{{ number_format($summary->estimated) }}</strong><small>Before any return
                    adjustments</small></article>
            <article class="seller-stat"><span class="stat-label">Recorded external
                    payouts</span><strong>₹{{ number_format($summary->recorded) }}</strong><small>Recorded by store
                    admin</small></article>
            <article class="seller-stat"><span class="stat-label">Current
                    commission</span><strong>{{ number_format($seller->commission_rate, 2) }}%</strong><small>Captured
                    separately for each order</small></article>
        </div>
        <div class="seller-info-note"><i class="bi bi-shield-check"></i>
            <p><b>A clear path to settlement.</b> Delivery and payment collection must be complete, the return window must
                end, and any returns need reconciliation before a payout is recorded. Estimates are not a withdrawable
                balance.</p>
        </div>
        <section class="seller-card">
            <div class="seller-card-header">
                <div>
                    <h3>Shipment earnings</h3>
                    <p>Records on the current orders page</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table seller-table">
                    <thead>
                        <tr>
                            <th>Shipment</th>
                            <th>After discount</th>
                            <th>Commission</th>
                            <th>Your amount</th>
                            <th>Payout record</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shipments as $shipment)
                            <tr data-search-row>
                                <td>#{{ $shipment->id }}<small>{{ ucfirst($shipment->status) }}</small></td>
                                <td>₹{{ number_format($shipment->net_amount) }}</td>
                                <td>₹{{ number_format($shipment->commission_amount) }}</td>
                                <td class="fw-semibold">₹{{ number_format($shipment->seller_amount) }}</td>
                                <td>
                                    @if ($shipment->settled_at)
                                        <span
                                            class="seller-status delivered">Recorded</span><small>{{ $shipment->payout_reference }}</small>
                                    @elseif($shipment->status === 'cancelled')
                                    <span class="seller-status cancelled">Cancelled</span>@else<span
                                            class="seller-status pending">Not recorded</span>
                                    @endif
                                </td>
                        </tr>@empty<tr>
                                <td colspan="5" class="text-center py-5 text-secondary">Your shipment earnings will
                                    appear here.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section><a href="#seller-orders" class="seller-text-link mt-3 d-inline-block">Browse order pages <i
                class="bi bi-arrow-right"></i></a>
    </section>
    <section class="seller-page" id="seller-offers">@include('seller.offers')</section>
    <section class="seller-page" id="seller-business">
        <div class="seller-section-intro">
            <div>
                <h2>A shop that feels like you.</h2>
                <p>Keep your business and pickup details up to date.</p>
            </div><span class="seller-status {{ $seller->status }}">{{ ucfirst($seller->status) }} account</span>
        </div>
        <div class="business-grid">
            <section class="seller-card p-4">
                <h3 class="mb-4">Business profile</h3>
                <form method="POST" action="{{ url('/seller/profile') }}">@csrf<input type="hidden" name="_section"
                        value="seller-business">@include('market.seller-fields')<div class="business-save">
                        <p><i class="bi bi-info-circle"></i> Changes require admin reapproval. Your products stay hidden
                            until approval.</p><button type="submit" class="btn btn-primary"><i
                                class="bi bi-check2"></i> Submit updated details</button>
                    </div>
                </form>
            </section>
            <aside>
                <section class="seller-card business-identity"><span class="business-avatar"><i
                            class="bi bi-shop"></i></span>
                    <h3>{{ $seller->shop_name }}</h3>
                    <p>{{ $seller->city }}, {{ $seller->state }}</p><span
                        class="seller-status {{ $seller->status }}">{{ ucfirst($seller->status) }} seller</span>
                    <hr>
                    <dl>
                        <dt>Commission</dt>
                        <dd>{{ $seller->commission_rate }}%</dd>
                        <dt>Contact</dt>
                        <dd>{{ $seller->phone }}</dd>
                        <dt>Account email</dt>
                        <dd>{{ auth()->user()->email }}</dd>
                    </dl><a class="btn btn-light w-100" href="#seller-account"><i class="bi bi-shield-lock"></i> Account
                        & password</a>
                </section>
                <div class="business-help"><i class="bi bi-headset"></i>
                    <h4>Need a hand?</h4>
                    <p>Our support form is open for account or order questions.</p><a href="{{ url('/pages/contact') }}"
                        target="_blank" rel="noopener">Contact support <i class="bi bi-arrow-up-right"></i></a>
                </div>
            </aside>
        </div>
    </section>
<section class="seller-page" id="seller-account">@include('seller.account')</section>@endsection
