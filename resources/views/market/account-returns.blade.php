<div class="account-section-heading">
    <span class="eyebrow">A LITTLE HELP AFTER DELIVERY</span>
    <h1>My returns</h1>
    <p>Follow the status of your return requests. Start a request from its delivered order page.</p>
</div>
@forelse($returnRequests as $return)
    <article class="form-card account-return-card">
        <div class="account-review-heading">
            <div><span class="account-reviewed-label">REQUEST #{{ $return->id }}</span>
                <h2>Order #{{ strtoupper(substr($return->reference, 0, 8)) }}</h2>
            </div><span class="account-state {{ $return->status }}">{{ ucfirst($return->status) }}</span>
        </div>
        <p class="account-return-date">Requested
            {{ \Illuminate\Support\Carbon::parse($return->created_at)->format('d M Y') }}</p>
        @foreach (json_decode($return->items) as $item)
            <div class="account-return-item"><b>{{ $item->name ?? 'Product' }}</b><span>{{ $item->size ?? '' }} · Qty
                    {{ $item->quantity }}</span></div>
        @endforeach
        <p>
            <b>Reason:</b> {{ $return->reason }}
        </p>
        <p>{{ $return->notes }}</p>
        @if ($return->admin_note)
            <div class="account-info">
                <p><b>Store update:</b> {{ $return->admin_note }}</p>
            </div>
            @endif @if ($return->refund_amount > 0)
                <p class="account-refund-record">Refund record: ₹{{ number_format($return->refund_amount) }}
                    @if ($return->refund_reference)
                        · Reference: {{ $return->refund_reference }}
                    @endif
                </p>
            @endif
            <a href="{{ url('/account/orders/' . $return->reference) }}" class="text-link">Open order details →</a>
</article>@empty<div class="form-card account-empty"><i class="bi bi-arrow-return-left" aria-hidden="true"></i>
        <h2>No return requests.</h2>
        <p>If something needs attention after delivery, open the order to check its return options.</p><a
            href="{{ url('/account/orders') }}" class="pink-button">View my orders →</a>
    </div>
@endforelse @include('market.account-pagination', ['paginator' => $returnRequests])
