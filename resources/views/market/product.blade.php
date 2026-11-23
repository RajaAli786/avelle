@extends('layouts.market')
@section('title', $product->name . ' · Avelle')
@section('description', \Illuminate\Support\Str::limit($product->description, 155))
@section('content')
    <div class="shell breadcrumbs">
        <a href="{{ url('/') }}">Home</a><span>/</span><a href="{{ url('/shop') }}">Shop</a><span>/</span>
        @if ($product->category_id)
            <a
                href="{{ url('/category/' . \Illuminate\Support\Facades\DB::table('categories')->where('id', $product->category_id)->value('slug')) }}">{{ $product->category }}</a><span>/</span>
        @endif
        <span>
            {{ $product->name }}</span>
    </div>
    <section class="product-detail shell" id="productDetail" data-product="{{ $product->id }}">
        <div class="product-gallery">
            <div class="gallery-thumbnails">
                @forelse($product->images as $image)
                    <button class="{{ $loop->first ? 'active' : '' }}" data-gallery-image="{{ $image->url }}"
                        aria-label="View product image {{ $loop->iteration }}"><img src="{{ $image->url }}"
                        alt="{{ $image->alt ?: $product->name }}" loading="lazy"></button>@empty<button class="active"
                        data-gallery-image="{{ $product->cover }}"><img src="{{ $product->cover }}"
                            alt="{{ $product->name }}"></button>
                @endforelse
            </div>
            <button class="product-main-photo" data-zoom-image aria-label="Enlarge product image"><img id="mainProductImage"
                    src="{{ $product->cover }}" alt="{{ $product->name }}"><span>⤢ Tap to enlarge</span></button>
        </div>
        <div class="product-purchase">
            <div class="product-brand">{{ $product->brand ?: $product->category }}</div>
            <h1>{{ $product->name }}</h1>
            <p class="product-subtitle">{{ $product->color }} ·
                {{ $product->audience === 'girls' ? 'Girls' : ($product->audience === 'both' ? 'Women & girls' : 'Women') }}</p>
            @if ($product->review_count)
                <a class="product-rating" href="#reviews">★ {{ $product->review_average }}
                <span>{{ $product->review_count }} verified reviews</span></a>@else<span class="product-new">Be the
                    first verified buyer to review this find</span>
            @endif
            <div class="product-divider">
            </div>
            <div class="detail-price"><strong id="detailPrice">₹{{ number_format($product->display_price) }}</strong>
                @if ($product->old > $product->display_price)
                    <del>₹{{ number_format($product->old) }}</del><span
                        id="detailDiscount">{{ round((1 - $product->display_price / $product->old) * 100) }}% OFF</span>
                @endif
            </div>
            <p class="sku-line">Sold by <strong>{{ $product->seller_name }}</strong></p>
            <p class="sku-line">SKU: <span id="detailSku">{{ $product->sku ?: 'AVL-' . $product->id }}</span></p>
            @if ($product->variants->count())<label class="product-field">Select
                    colour<select id="productColor">
                        @foreach ($product->variants->pluck('color')->unique() as $color)
                            <option>{{ $color }}</option>
                        @endforeach
                    </select>
            </label>@else<div class="product-field">Colour <strong>{{ $product->color }}</strong></div>
            @endif
            <div class="size-heading"><b>Select size</b><a
                    href="{{ url('/pages/contact') }}?subject={{ urlencode('Size help: ' . $product->name) }}">Need size
                    help? ↗</a></div>
            <div class="size-options" id="productSizes">
                @foreach ($product->variants->count() ? $product->variants->pluck('size')->unique() : $product->sizes as $size)
                    <button data-select-size="{{ $size }}" aria-pressed="false">{{ $size }}</button>
                @endforeach
            </div>
            <div class="product-quantity"><label>Quantity <input id="productQuantity" type="number" min="1"
                        max="10" value="1"></label><span
                    id="productAvailability">{{ $product->stock ? 'In stock' : 'Currently sold out' }}</span></div>
            <p class="inline-error" id="productChoiceError" role="alert"></p>
            <div class="purchase-buttons"><button class="pink-button" id="addProductToBag"
                    @if (!$product->stock) disabled @endif>♧ Add to bag</button><button class="outline-button"
                    data-wish-product="{{ $product->id }}" aria-pressed="false">♡ Wishlist</button></div><button
                class="dark-button full buy-now" id="buyProductNow" @if (!$product->stock) disabled @endif>Buy
                now →</button>
            <div class="product-offer-box" id="detailOffers"></div>
            <div class="delivery-notes">
                <div><span>♧</span>
                    <p><b>Shipping ₹{{ $settings['shipping_fee'] }}</b><small>Free shipping on a bag subtotal of
                            ₹{{ number_format($settings['free_shipping_minimum']) }} or more.</small></p>
                </div>
                <div><span>↺</span>
                    <p><b>{{ $settings['return_days'] }}-day return request window</b><small>After delivery. <a
                                href="{{ url('/pages/returns') }}">View return policy</a></small></p>
                </div>
                <div><span>♙</span>
                    <p><b>Cash on delivery</b><small>Keep track of every order in your Avelle account.</small></p>
                </div>
            </div>
        </div>
    </section>
    <section class="shell product-information">
        <div class="information-tabs" role="tablist" aria-label="Product information"><button role="tab"
                id="tab-description" aria-controls="panel-description" aria-selected="true"
                data-info-tab="description">Description</button><button role="tab" id="tab-specifications"
                aria-controls="panel-specifications" aria-selected="false" data-info-tab="specifications">Additional
                information</button><button role="tab" id="tab-reviews" aria-controls="panel-reviews"
                aria-selected="false" data-info-tab="reviews">Reviews ({{ $product->review_count }})</button></div>
        <div class="information-panel" id="panel-description" role="tabpanel" aria-labelledby="tab-description">
            <h2>Product details</h2>
            <p class="preserve-lines">{{ $product->description }}</p>
            @if (count($product->highlights))
                <h3>Why you’ll love it</h3>
                <ul class="product-highlights">
                    @foreach ($product->highlights as $highlight)
                        <li>{{ $highlight }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="information-panel" id="panel-specifications" role="tabpanel" aria-labelledby="tab-specifications">
            <h2>Additional information</h2>
            @if (count($product->specifications))
                <dl class="specifications-grid">
                    @foreach ($product->specifications as $spec)
                        <div>
                            <dt>{{ $spec['name'] ?? '' }}</dt>
                            <dd>{{ $spec['value'] ?? '' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else<p>Have a question about material, measurements or care? <a
                        href="{{ url('/pages/contact') }}?subject={{ urlencode($product->name) }}">Ask us about this
                        product.</a></p>
            @endif
        </div>
        <div class="information-panel" id="panel-reviews" role="tabpanel" aria-labelledby="tab-reviews">
            <h2 id="reviews">Customer reviews</h2>
            <p class="muted">Only verified customers with a delivered order can submit a review.</p>
            @forelse($reviews as $review)
                <article class="review-card">
                    <div class="review-stars">
                        {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                    <h3>{{ $review->title }}</h3>
                    <p>{{ $review->body }}</p><small>{{ explode(' ', $review->customer_name)[0] }} · Verified purchase ·
                        {{ date('d M Y', strtotime($review->created_at)) }}</small>
                    @if ($review->reply)
                        <div class="review-reply"><b>Response from Avelle</b>
                            <p>{{ $review->reply }}</p>
                        </div>
                    @endif
                </article>
            @empty<p class="empty-review">No reviews yet. Your experience could help someone find their next favourite.
                </p>
                @endforelse @if ($reviews->hasMorePages())
                    <a href="{{ $reviews->nextPageUrl() }}#reviews" class="text-link">More reviews →</a>
                @endif
                @auth<div class="review-eligibility-box">
                        @if ($ownReview ?? null)
                            <div class="review-submission-status"><b>Your review:
                                    {{ $ownReview->status === 'pending' ? 'Pending approval' : ucfirst($ownReview->status) }}</b>
                                <p>{{ $ownReview->status === 'pending' ? 'Your review has been saved. Admin approval is needed before it appears publicly.' : ($ownReview->status === 'approved' ? 'Your review is approved and published.' : 'You can update your review and submit it again.') }}
                                </p><a href="{{ url('/account/reviews') }}">Manage my reviews →</a>
                            </div>
                            @endif @if ($reviewEligible ?? false)
                                <h3>{{ $ownReview ?? null ? 'Update your review' : 'Write your verified-purchase review' }}</h3>
                                <p class="muted">Your purchase has been delivered. Share the details that could help someone
                                    choose.</p>
                                <form method="POST" action="{{ url('/products/' . $product->id . '/reviews') }}">
                                    @csrf<label>Rating<select name="rating" required>
                                            @for ($r = 5; $r >= 1; $r--)
                                                <option value="{{ $r }}" @selected((int) old('rating', $ownReview?->rating ?? 5) === $r)>
                                                    {{ $r }} stars</option>
                                            @endfor
                                        </select>
                                    </label><label>Title<input name="title" required maxlength="120"
                                            value="{{ old('title', $ownReview?->title) }}"></label><label>Your review
                                        <textarea name="body" required minlength="10" maxlength="2000">{{ old('body', $ownReview?->body) }}</textarea>
                                </label><button class="pink-button">Submit for approval</button></form>@else<h3>Reviews
                                    open after delivery.</h3>
                                <p>You’re signed in. To review this product, it must be part of a delivered purchase on this
                                    account.</p><a href="{{ url('/account/orders') }}" class="outline-button">Check my orders
                                    →</a>
                            @endif
                    </div>
            @else<a href="{{ url('/account/login') }}" class="outline-button">Sign in to write a review</a>@endauth
    </div>
</section>
@if ($related->count())
    <section class="market-section shell">
        <div class="section-title">
            <div><span class="eyebrow">MORE TO FALL FOR</span>
                <h2>You may also like</h2>
            </div><a href="{{ url('/shop') }}">Explore all ↗</a>
        </div>
        <div class="shop-product-grid">
            @foreach ($related as $p)
                @include('market.card', ['p' => $p])
            @endforeach
        </div>
    </section>
@endif
@endsection
