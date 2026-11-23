<a class="account-order-card" href="{{ url('/account/orders/' . $o->reference) }}">
    <div><b>Order #{{ strtoupper(substr($o->reference, 0, 8)) }}</b><small>{{ date('d M Y', strtotime($o->created_at)) }} ·
            {{ count(json_decode($o->items, true)) }} product lines</small></div><span
        class="order-badge status-{{ $o->status }}">{{ ucfirst($o->status) }}</span><strong>₹{{ number_format($o->total) }}</strong><span>View
        order →</span>
</a>
