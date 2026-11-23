@extends('layouts.market') @section('title', 'Order ' . strtoupper(substr($order->reference, 0, 8)) . ' · Avelle')
@section('content') @if (isset($shipments) && $shipments->count())
    <section class="shell market-section">
        <h2>Your shipments</h2>
        <p>Items from different sellers are fulfilled separately. The main order status follows the least-progressed
            active shipment.</p>
        @foreach ($shipments as $shipment)
            <details class="seller-panel" style="border:1px solid #ddd;padding:16px;margin-bottom:12px">
                <summary>Shipment #{{ $shipment->id }} · {{ $shipment->status }}</summary>
                @foreach (json_decode($shipment->items) as $item)
                    <p>{{ $item->name }} · {{ $item->seller_name ?? 'Avelle' }} · {{ $item->size }} ×
                        {{ $item->quantity }}</p>
                @endforeach
                <p>
                    {{ $shipment->carrier }} · {{ $shipment->tracking_number }}</p>
                @if ($shipment->tracking_url)
                    <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener">Track shipment →</a>
                @endif
            </details>
        @endforeach
    </section>
@endif
<div class="shell breadcrumbs"><a href="{{ url('/') }}">Home</a><span>/</span><a
        href="{{ url('/account/orders') }}">My
        orders</a><span>/</span><span>{{ strtoupper(substr($order->reference, 0, 8)) }}</span></div>
<section class="shell order-page">
    @if (request('new'))
        <div class="order-confirmation"><span>✓</span>
            <div>
                <h1>Your order is placed!</h1>
                <p>Your account and order details are ready below. Pay cash on delivery.</p>
            </div>
        </div>
    @endif
    <div class="section-title">
        <div><span class="eyebrow">YOUR AVELLE ORDER</span>
            <h1 class="page-title">#{{ strtoupper(substr($order->reference, 0, 8)) }}</h1>
            <p class="muted">Placed {{ date('d M Y, h:i A', strtotime($order->created_at)) }} IST</p>
        </div><a class="outline-button" href="{{ url('/account/orders/' . $order->reference . '/receipt') }}"
            target="_blank" rel="noopener">Order receipt ↗</a>
    </div>
    <div class="order-tracker {{ $order->status === 'cancelled' ? 'is-cancelled' : '' }}">
        @if ($order->status === 'cancelled')
            <b>Order cancelled</b>
        @else
            @foreach (['placed' => 'Placed', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $status => $label)
                @php($rank = ['placed' => 0, 'confirmed' => 1, 'shipped' => 2, 'delivered' => 3])<div class="{{ ($rank[$order->status] ?? 0) >= $loop->index ? 'complete' : '' }}">
                    <span>{{ ($rank[$order->status] ?? 0) >= $loop->index ? '✓' : $loop->iteration }}</span><b>{{ $label }}</b>
                </div>
            @endforeach
        @endif
    </div>
    @if ($order->tracking_number)
        <div class="shipment-info"><span>♧</span>
            <div><b>{{ $order->carrier ?: 'Your shipment' }}</b>
                <p>Tracking number: {{ $order->tracking_number }}</p>
                @if ($order->tracking_url)
                    <a href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer">Track with carrier
                        ↗</a>
                @endif
            </div>
        </div>
    @endif
    <div class="order-detail-layout">
        <div>
            <section class="form-card">
                <h2>Items in this order</h2>
                @foreach (json_decode($order->items, true) as $i)
                    <div class="ordered-item">
                        <div><b>{{ $i['name'] }}</b>
                            <p>{{ $i['size'] }} @if (!empty($i['color']))
                                    · {{ $i['color'] }}
                                @endif · Qty {{ $i['quantity'] }}</p>
                            @if (!empty($i['sku']))
                                <small>{{ $i['sku'] }}</small>
                            @endif
                        </div>
                        <strong>₹{{ number_format($i['price'] * $i['quantity']) }}</strong>
                    </div>
                @endforeach
            </section>
            <section class="form-card">
                <h2>Delivery address</h2>
                <p>{{ $order->name }}<br>{{ $order->address }}<br>{{ $order->city }} {{ $order->state }}
                    {{ $order->pincode }}<br>{{ $order->phone }}</p>
            </section>
            @if (in_array($order->status, ['placed', 'confirmed']))
                <section class="form-card">
                    <h2>Need to cancel?</h2>
                    <p>You can cancel before your order is dispatched.</p>
                    <form method="POST" action="{{ url('/account/orders/' . $order->reference . '/cancel') }}">
                        @csrf<button class="outline-button">Cancel this order</button></form>
                </section>
            @endif
            @if ($order->status === 'delivered')
                <section class="form-card" id="return-request">
                    <h2>Request a return</h2>
                    <p>Return requests are accepted within {{ $settings['return_days'] }} days of delivery, subject to
                        the <a href="{{ url('/pages/returns') }}">return policy</a>.</p>
                    <form method="POST" action="{{ url('/account/orders/' . $order->reference . '/returns') }}">@csrf
                        @foreach (json_decode($order->items, true) as $line => $i)
                            <label class="return-line"><span>{{ $i['name'] }} · {{ $i['size'] }}</span><select
                                    name="quantities[{{ $line }}]">
                                    @for ($q = 0; $q <= $i['quantity']; $q++)
                                        <option value="{{ $q }}">
                                            {{ $q === 0 ? 'Do not return' : $q . ' to return' }}</option>
                                    @endfor
                                </select>
                            </label>
                        @endforeach
                        <label>
                            Reason<select name="reason" required>
                                @foreach (['Size or fit', 'Item damaged', 'Wrong item', 'Quality issue', 'Other'] as $reason)
                                    <option>{{ $reason }}</option>
                                @endforeach
                            </select>
                        </label><label>Tell us more
                            <textarea name="notes" required minlength="10" maxlength="2000"></textarea>
                        </label><button class="pink-button">Submit return request</button>
                    </form>
                </section>
            @endif
            @foreach ($returns as $ret)
                <section class="form-card">
                    <h2>Return request #{{ $ret->id }}</h2><span
                        class="order-badge">{{ ucfirst($ret->status) }}</span>
                    <p>{{ $ret->reason }} · {{ $ret->notes }}</p>
                    @if ($ret->admin_note)
                        <p>{{ $ret->admin_note }}</p>
                        @endif @if ($ret->refund_reference)
                            <p>Recorded refund: ₹{{ number_format($ret->refund_amount) }} · Reference
                                {{ $ret->refund_reference }}</p>
                        @endif
                </section>
            @endforeach
        </div>
        <aside>
            <section class="order-summary">
                <h2>Order summary</h2>
                <dl>
                    <div>
                        <dt>Subtotal</dt>
                        <dd>₹{{ number_format($order->subtotal) }}</dd>
                    </div>
                    <div>
                        <dt>Shipping</dt>
                        <dd>₹{{ $order->shipping }}</dd>
                    </div>
                    <div>
                        <dt>Discount {{ $order->code }}</dt>
                        <dd>−₹{{ $order->discount }}</dd>
                    </div>
                    <div class="summary-total">
                        <dt>Total</dt>
                        <dd>₹{{ number_format($order->total) }}</dd>
                    </div>
                </dl>
                <p class="muted">Cash on delivery · Payment {{ $order->payment_status }}</p><small>Reference:
                    {{ $order->reference }}</small>
            </section>
            <section class="form-card">
                <h2>Order activity</h2>
                <ol class="order-events">
                    @forelse($events as $event)
                        <li><b>{{ ucfirst($event->status) }}</b><small>{{ date('d M, h:i A', strtotime($event->created_at)) }}
                                IST</small>
                            <p>{{ $event->note }}</p>
                    </li>@empty<li>
                            <b>{{ ucfirst($order->status) }}</b><small>{{ date('d M Y', strtotime($order->updated_at)) }}</small>
                        </li>
                    @endforelse
                </ol>
            </section><a class="text-link"
                href="{{ url('/pages/contact') }}?subject={{ urlencode('Order ' . $order->reference) }}">Need help with
                this order? ↗</a>
        </aside>
    </div>
</section>@endsection
