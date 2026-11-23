<section class="admin-action-centre" aria-label="Pending store tasks">
    <div class="admin-action-heading">
        <div><span class="admin-eyebrow">A LITTLE ATTENTION, A BETTER STORE</span>
            <h2>Needs your attention</h2>
        </div><span>{{ array_sum($actionCounts) }} pending items</span>
    </div>
    <div class="admin-action-grid">
        @foreach (['reviews' => ['bi-chat-square-heart', 'Reviews', 'Awaiting approval', '/admin?review_status=pending#reviews'], 'returns' => ['bi-arrow-return-left', 'Returns', 'Need a store update', '/admin#returns'], 'sellers' => ['bi-shop', 'Seller applications', 'Awaiting approval', '/admin#sellers'], 'products' => ['bi-box-seam', 'Seller products', 'Ready for review', '/admin#sellers'], 'support' => ['bi-headset', 'Support requests', 'Open inquiries', '/admin#support']] as $key => $task)
        <a href="{{ url($task[3]) }}"><span class="action-icon action-{{ $key }}"><i
                    class="bi {{ $task[0] }}"
                    aria-hidden="true"></i></span><strong>{{ $actionCounts[$key] }}</strong><b>{{ $task[1] }}</b><small>{{ $task[2] }}</small><i
                class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        @endforeach
    </div>
</section>