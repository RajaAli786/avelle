<section class="admin-page" id="referral-wallet">
    <div class="page-section-heading"><div><h2>Referral wallet & rewards</h2><p>Set the rules. Customers see their own rewards only.</p></div>
    <form method="POST" action="{{ url('/admin/referrals/process') }}">@csrf<button class="btn btn-primary">Check pending rewards</button></form></div>
    <div class="editor-card p-4 mb-4"><h3>Program settings</h3><p>New rules apply to new orders. All amounts are whole rupees. Referral codes are entered in the coupon box.</p>
    <form method="POST" action="{{ url('/admin/referrals/settings') }}">@csrf
        <div class="editor">
        <label>Referral program<select class="form-select" name="referral_enabled"><option value="0" @selected(!$referralSettings['referral_enabled'])>Paused</option><option value="1" @selected($referralSettings['referral_enabled'])>Active</option></select></label>
        <label>Wallet redemption<select class="form-select" name="wallet_enabled"><option value="1" @selected($referralSettings['wallet_enabled'])>Enabled</option><option value="0" @selected(!$referralSettings['wallet_enabled'])>Paused</option></select></label>
        <label>Reward type<select class="form-select" name="referral_reward_type"><option value="fixed" @selected($referralSettings['referral_reward_type'] === 'fixed')>Fixed rupees</option><option value="percent" @selected($referralSettings['referral_reward_type'] === 'percent')>Percentage of eligible merchandise after discount</option></select></label>
        <label>Reward value<input class="form-control" type="number" name="referral_reward_value" min="0" max="10000" required value="{{ $referralSettings['referral_reward_value'] }}"></label>
        <label>Maximum reward per order (₹)<input class="form-control" type="number" name="referral_reward_max" min="0" max="10000" required value="{{ $referralSettings['referral_reward_max'] }}"></label>
        <label>Minimum merchandise after discount (₹)<input class="form-control" type="number" name="referral_minimum" min="1" max="1000000" required value="{{ $referralSettings['referral_minimum'] }}"></label>
        <label>New customer's discount (₹)<input class="form-control" type="number" name="referral_friend_discount" min="0" max="10000" required value="{{ $referralSettings['referral_friend_discount'] }}"></label>
        </div><p>Rewards unlock only after delivery, collected payment and the store return window. Customers cannot transfer or withdraw wallet credit.</p><button class="btn btn-primary mt-3">Save referral rules</button>
    </form></div>
    <div class="admin-table-shell"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order</th><th>Referrer</th><th>Code / basis</th><th>Reward</th><th>Status</th><th>Available after</th></tr></thead><tbody>
    @forelse ($referralRewards as $reward)<tr><td>{{ 'AVL-' . str_pad($reward->order_id, 8, '0', STR_PAD_LEFT) }}</td><td>{{ $reward->referrer_name }}<br><small>{{ $reward->referrer_email }}</small></td><td>{{ $reward->code }}<br><small>₹{{ number_format($reward->eligible_amount) }} eligible · {{ $reward->reward_value }}{{ $reward->reward_type === 'percent' ? '%' : ' rupees' }}</small></td><td>₹{{ number_format($reward->amount) }}</td><td><span class="badge text-bg-{{ $reward->status === 'credited' ? 'success' : ($reward->status === 'reversed' ? 'secondary' : 'warning') }}">{{ ucfirst($reward->status) }}</span></td><td>{{ $reward->available_at ?? 'Awaiting delivery' }}</td></tr>
    @empty<tr><td colspan="6" class="text-center p-4">No referral rewards yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <h3 class="mt-4">Wallet transactions <small class="text-muted">Latest 100</small></h3>
    <div class="admin-table-shell"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Customer</th><th>Details</th><th>Amount</th><th>Balance after</th><th>Date</th></tr></thead><tbody>
    @forelse ($walletTransactions as $entry)<tr><td>{{ $entry->name }}<br><small>{{ $entry->email }}</small></td><td>{{ $entry->note }}</td><td>{{ $entry->amount > 0 ? '+' : '−' }}₹{{ number_format(abs($entry->amount)) }}</td><td>₹{{ number_format($entry->balance_after) }}</td><td>{{ $entry->created_at }}</td></tr>
    @empty<tr><td colspan="5" class="text-center p-4">No wallet transactions yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="alert alert-info mt-4">Seller commission is managed separately under <a href="#sellers">Sellers</a>. Settlement history is under <a href="#seller-orders">Seller orders & payouts</a>. Referral rewards are funded by the platform and do not change the seller's commission snapshot.</div>
</section>
<section class="admin-page" id="email-delivery">
    <div class="page-section-heading"><div><h2>Email delivery</h2><p>Latest 100 messages. Failed sends retry automatically up to five attempts while the scheduler is running.</p></div></div>
    <div class="alert alert-info">Sent means the mail transport accepted the message; inbox delivery is not guaranteed. Logged means a preview was created without an external email. Fix SMTP settings before retrying repeated failures.</div>
    <div class="alert alert-light border"><strong>Background processing:</strong>
        @if (!empty($referralSettings['store_tasks_last_run_at']))
            Last checked {{ \Illuminate\Support\Carbon::parse($referralSettings['store_tasks_last_run_at'])->diffForHumans() }}.
        @else
            Waiting for the scheduler's first run.
        @endif
        On XAMPP, run <code>Start-StoreTasks.ps1</code> from the project folder after starting MySQL. <code>Start-Local.ps1</code> also starts these tasks.
    </div>
    <div class="admin-table-shell"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Next attempt / error</th><th>Action</th></tr></thead><tbody>
    @forelse ($emailDeliveries as $delivery)<tr><td>{{ $delivery->recipient }}</td><td>{{ $delivery->subject }}<br><small>{{ $delivery->created_at }}</small></td><td><span class="badge text-bg-{{ $delivery->status === 'sent' ? 'success' : ($delivery->status === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($delivery->status) }}</span></td><td>{{ $delivery->attempts }}</td><td>{{ $delivery->next_attempt_at ?? '—' }}<br><small>{{ $delivery->error_type }}</small></td><td>
        @if ($delivery->payload && in_array($delivery->status, ['pending', 'retry', 'failed']))<form method="POST" action="{{ url('/admin/emails/' . $delivery->id . '/retry') }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry email</button></form>@else<span class="text-muted">—</span>@endif
    </td></tr>@empty<tr><td colspan="6" class="text-center p-4">New store emails will appear here.</td></tr>@endforelse
    </tbody></table></div></div>
</section>
