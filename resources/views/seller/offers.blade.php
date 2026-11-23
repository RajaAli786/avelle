<div class="seller-section-intro">
    <div>
        <h2>A good find deserves a great offer.</h2>
        <p>Create discounts for your shop. One coupon or referral can be used per order.</p>
    </div>
    @if ($seller->status === 'approved')
        <button class="btn btn-primary" data-open-editor="seller-offer-new"><i class="bi bi-plus-lg"></i> Create
            offer</button>
    @endif
</div>
<div class="seller-info-note"><i class="bi bi-ticket-perforated"></i>
    <p><b>Your products. Your offers.</b> Offers apply only to your own eligible products. Minimum spend uses that
        eligible subtotal. Admin can lock an offer; changing its usage limit does not reset past usage.</p>
</div>
@if ($seller->status === 'approved')
    @foreach ($sellerOffers->concat([null]) as $offer)
        @php($offerState = $offer ? ($offer->admin_locked ? 'Admin locked' : ($offer->expires_at && now()->greaterThanOrEqualTo($offer->expires_at) ? 'Expired' : ($offer->usage_limit !== null && $offer->used >= $offer->usage_limit ? 'Exhausted' : ($offer->active ? 'Active' : 'Paused')))) : 'New offer')
        <details class="editor-card seller-offer-editor" id="seller-offer-{{ $offer?->id ?? 'new' }}" data-search-row>
            <summary class="editor-summary"><span class="offer-code-icon"><i
                        class="bi bi-ticket-perforated"></i></span><span><b>{{ $offer?->code ?? 'Create a new coupon' }}</b><small>{{ $offer ? ($offer->type === 'percent' ? $offer->value . '% off' : '₹' . $offer->value . ' off') : 'Give shoppers a reason to choose your collection' }}</small></span><span
                    class="seller-status {{ $offerState === 'Active' ? 'approved' : 'pending' }}">{{ $offerState }}</span>
                @if ($offer)
                    <span
                        class="offer-used">{{ $offer->used }}{{ $offer->usage_limit !== null ? ' / ' . $offer->usage_limit : '' }}
                        used</span>
                @endif
                <i class="bi bi-chevron-down">
                </i>
            </summary>
            <div class="offer-editor-body">
                @if ($offer?->admin_locked)
                    <div class="alert alert-warning">Admin paused this coupon. You can edit its details, but only admin
                        can unlock activation.</div>
                @endif
                <form method="POST" action="{{ url('/seller/offers') }}" data-seller-offer-form>
                    @csrf<input type="hidden" name="_section" value="seller-offers"><input type="hidden"
                        name="id" value="{{ $offer?->id }}">
                    <div class="editor"><label>Coupon code<input class="form-control" name="code" required
                                maxlength="40" pattern="[A-Za-z0-9_-]{3,40}" value="{{ $offer?->code }}"
                                placeholder="e.g. YOURSHOP10" @readonly($offer && $offer->used > 0)></label><label>Discount type<select
                                class="form-select" name="type">
                                <option value="percent" @selected(($offer?->type ?? 'percent') === 'percent')>Percentage (%)</option>
                                <option value="fixed" @selected($offer?->type === 'fixed')>Fixed amount (₹)</option>
                            </select></label><label>Discount value<input class="form-control" type="number"
                                name="value" required min="1" max="1000000"
                                value="{{ $offer?->value ?? 10 }}"></label><label>Minimum eligible subtotal ₹<input
                                class="form-control" type="number" name="minimum" required min="0"
                                max="1000000" value="{{ $offer?->minimum ?? 0 }}"></label><label>Maximum savings ₹
                            (optional)<input class="form-control" type="number" name="maximum" min="1"
                                max="1000000" value="{{ $offer?->maximum }}"></label><label>Total usage limit
                            (optional)<input class="form-control" type="number" name="usage_limit" min="1"
                                max="1000000" value="{{ $offer?->usage_limit }}"></label><label>Expires at (IST,
                            optional)<input class="form-control" type="datetime-local" name="expires_at"
                                value="{{ $offer?->expires_at ? \Illuminate\Support\Carbon::parse($offer->expires_at)->format('Y-m-d\TH:i') : '' }}"></label><label>Apply
                            to<select class="form-select" name="scope" data-offer-scope>
                                <option value="store" @selected(($offer?->scope ?? 'store') === 'store')>All my products</option>
                                <option value="category" @selected($offer?->scope === 'category')>My products in selected categories
                                </option>
                                <option value="product" @selected($offer?->scope === 'product')>Selected products from my shop
                                </option>
                            </select></label><label>Offer display<select class="form-select" name="banner_mode">
                                @foreach (['both' => 'Marquee + pulse', 'marquee' => 'Marquee only', 'pulse' => 'Pulse only', 'static' => 'Static'] as $mode => $label)
                                    <option value="{{ $mode }}" @selected(($offer?->banner_mode ?? 'both') === $mode)>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </label></div>
                    <div class="offer-targets" data-offer-targets="product"><label>Choose your products<select
                                class="form-select" multiple size="5" data-offer-products>
                                @foreach ($products as $productOption)
                                    <option value="{{ $productOption->id }}" @selected($offer?->scope === 'product' && in_array((string) $productOption->id, json_decode($offer->targets ?? '[]', true)))>
                                        #{{ $productOption->id }} · {{ $productOption->name }}</option>
                                @endforeach
                            </select>
                        </label><small>Hold Ctrl on Windows to select multiple products.</small></div>
                    <div class="offer-targets" data-offer-targets="category"><label>Choose your categories<select
                                class="form-select" multiple size="4" data-offer-categories>
                                @foreach ($products->pluck('category')->unique()->sort() as $categoryName)
                                    <option @selected($offer?->scope === 'category' && in_array($categoryName, json_decode($offer->targets ?? '[]', true)))>{{ $categoryName }}</option>
                                @endforeach
                            </select>
                        </label><small>Only your products in these categories receive the discount.</small></div><input
                        type="hidden" name="targets"
                        value="{{ implode(',', json_decode($offer->targets ?? '[]', true)) }}"><label
                        class="d-block mt-3">Public offer headline (optional)<input class="form-control"
                            name="banner_text" maxlength="180" value="{{ $offer?->banner_text }}"
                            placeholder="A little extra to love from our shop"></label>
                    <div class="offer-checks"><label><input type="checkbox" name="active" value="1"
                                @checked(($offer?->active ?? true) && !($offer?->admin_locked ?? false)) @disabled($offer?->admin_locked)> Active</label><label><input
                                type="checkbox" name="show_banner" value="1" @checked($offer?->show_banner ?? true)>
                            Advertise publicly</label></div><button type="submit"
                        class="btn btn-primary">{{ $offer ? 'Save offer' : 'Create coupon' }}</button>
                </form>
                @if ($offer && !$offer->admin_locked)
                    <form class="offer-pause-form" method="POST"
                        action="{{ url('/seller/offers/' . $offer->id . '/toggle') }}">@csrf<button type="submit"
                            class="btn btn-light"><i
                                class="bi {{ $offer->active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                            {{ $offer->active ? 'Pause coupon' : 'Activate coupon' }}</button></form>
                @endif
            </div>
        </details>
@endforeach @else<section class="seller-card seller-empty"><span class="empty-icon"><i
                class="bi bi-ticket-perforated"></i></span>
        <h3>Offers unlock with seller approval.</h3>
        <p>Once approved, create discounts for your own collection.</p>
    </section>
@endif
