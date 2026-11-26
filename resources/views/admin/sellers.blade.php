<section class="admin-page" id="sellers">
    <div class="page-section-heading">
        <div>
            <h2>Seller applications</h2>
            <p>Approve businesses and review their products. Account disable controls remain in Users & customers.</p>
        </div><a class="btn btn-light" href="{{ url('/seller/apply') }}" target="_blank">Open seller application</a>
    </div>
    <div data-admin-list="sellers"></div>
@forelse($sellers as $s)
    <details class="editor-card" id="seller-{{ $s->id }}" data-search-row data-admin-record='{{ json_encode(['group' => 'sellers', 'title' => $s->shop_name, 'shop' => $s->shop_name, 'owner' => $s->owner_name, 'email' => $s->owner_email, 'commission' => $s->commission_rate.'%', 'status' => ucfirst($s->status)]) }}'>
        <summary class="editor-summary">{{ $s->shop_name }} · {{ $s->status }} · {{ $s->commission_rate }}%
            commission</summary>
        <p>{{ $s->owner_name }} · {{ $s->owner_email }} · {{ $s->phone }} · User
            {{ $s->user_active ? 'enabled' : 'disabled' }}
        </p>
        <p>{{ $s->pickup_address }}, {{ $s->city }}, {{ $s->state }} {{ $s->pincode }}</p>
        <p>{{ $s->description }}</p>
        <form method="POST" action="{{ url('/admin/sellers/' . $s->id) }}">@csrf<div class="editor"><label>Approval
                    status<select class="form-select" name="status">
                        @foreach (['pending', 'approved', 'rejected', 'suspended'] as $status)
                        <option @selected($s->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </label><label>Commission percentage<input class="form-control" name="commission_rate"
                        type="number" min="0" max="100" step="0.01" required
                        value="{{ $s->commission_rate }}"></label><label>Note visible to seller
                    <textarea class="form-control" name="admin_note">{{ $s->admin_note }}</textarea>
                </label></div><button type="submit" class="btn btn-primary">Save seller review</button></form>
    </details>@empty<p>No applications yet. Sellers can apply from the storefront footer.</p>
    @endforelse
    <h2 class="mt-4">
        Seller product approval</h2>
    <div data-admin-list="sellerProducts"></div>
@forelse($sellerProducts as $p)
    <details class="editor-card" id="seller-product-{{ $p->id }}" data-search-row data-admin-record='{{ json_encode(['group' => 'sellerProducts', 'title' => $p->name, 'name' => $p->name, 'shop' => $p->shop_name, 'category' => $p->category, 'price' => '₹'.number_format($p->price), 'status' => ucfirst($p->seller_status)]) }}'>
        <summary class="editor-summary">{{ $p->shop_name }} · {{ $p->name }} · {{ $p->seller_status }}
        </summary>
        <p>Product #{{ $p->id }} · ₹{{ $p->price }} · {{ $p->category }}</p>
        <p style="white-space:pre-wrap">{{ $p->description }}</p>
        <div class="admin-gallery">
            @foreach ($productImages[$p->id] ?? collect() as $im)
            <div class="admin-image"><img src="{{ \App\Support\Shop::mediaUrl($im->path) }}"
                    alt="{{ $im->alt }}"></div>
            @endforeach
        </div>
        <p><button type="button" class="btn btn-light" data-open-editor="product-{{ $p->id }}">Review full
                details / variants</button></p>
        <form method="POST" action="{{ url('/admin/seller-products/' . $p->id) }}">@csrf<label>Product review<select
                    class="form-select" name="seller_status">
                    @foreach (['pending', 'approved', 'rejected'] as $status)
                    <option @selected($p->seller_status === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label><label class="d-block my-3">Seller feedback
                <textarea class="form-control" name="moderation_note">{{ $p->moderation_note }}</textarea>
            </label><button type="submit" class="btn btn-primary">Save product review</button></form>
    </details>@empty<p>No seller products awaiting review.</p>
    @endforelse
</section>
<section class="admin-page" id="seller-orders">
    <h2>Seller orders & payout records</h2>
    <p>Latest 200 shipment records. Commission is captured when the order is placed. Shipping fees belong to the
        platform and are not included in seller earnings. Record external payouts only after delivery, collected payment
        and the return window. Orders with returns require separate reconciliation.</p>
    <div data-admin-list="shipments"></div>
@forelse($sellerShipments as $shipment)
    <details class="editor-card" id="shipment-{{ $shipment->id }}" data-search-row data-admin-record='{{ json_encode(['group' => 'shipments', 'title' => 'Shipment #'.$shipment->id, 'shipment' => '#'.$shipment->id, 'order' => \App\Support\OrderDisplay::reference((object)['id'=>$shipment->order_id]), 'shop' => $shipment->shop_name ?? 'Avelle', 'amount' => '₹'.number_format($shipment->seller_amount), 'status' => ucfirst($shipment->status), 'payout' => $shipment->settled_at ? 'Recorded' : 'Pending']) }}'>
        <summary class="editor-summary">{{ $shipment->reference }} · {{ $shipment->shop_name ?? 'Avelle store' }} ·
            Shipment #{{ $shipment->id }} · {{ $shipment->status }}</summary>
        @include('admin.whatsapp-action', ['whatsappUrl' => \App\Support\OrderWhatsApp::shipmentUrl($shipment)])
        @foreach (json_decode($shipment->items) as $line)
        <p>{{ $line->name }} · {{ $line->size }} × {{ $line->quantity }}</p>
        @endforeach
        <p>
            Subtotal ₹{{ $shipment->subtotal }} · Discount ₹{{ $shipment->discount }} · Commission
            ₹{{ $shipment->commission_amount }} · Seller amount ₹{{ $shipment->seller_amount }} · Parent payment
            {{ $shipment->payment_status }}
        </p>
        @if (!in_array($shipment->status, ['cancelled', 'delivered']))
        @include('market.shipment-form', [
        'shipment' => $shipment,
        'action' => url('/admin/seller-shipments/' . $shipment->id),
        ])
        @endif @if ($shipment->seller_id)
        @if ($shipment->settled_at)
        <div class="alert alert-success mt-3">External payout recorded:
            {{ $shipment->payout_reference }} ·
            {{ $shipment->settled_at }}<br>{{ $shipment->payout_note }}
        </div>@else<form method="POST"
            class="mt-4" action="{{ url('/admin/seller-shipments/' . $shipment->id . '/payout') }}">
            @csrf<label class="d-block my-2">External payout reference<input class="form-control"
                    name="payout_reference" required maxlength="150"></label><label
                class="d-block my-2">Reconciliation note
                <textarea class="form-control" name="payout_note"></textarea>
            </label><button type="submit" class="btn btn-outline-primary">Record already-processed
                payout</button><small class="d-block mt-2">This does not transfer money.</small></form>
        @endif
        @endif
    </details>
    @empty<p>No marketplace shipments yet. Existing historical orders stay in Orders.</p>
    @endforelse
</section>