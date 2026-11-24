<section class="wallet-hero">
    <span class="eyebrow">GOOD FINDS ARE BETTER SHARED</span>
    <h1>Refer a friend.<br>A little joy comes back.</h1>
    <p>Your referral rewards, all in one place.</p>
    <div class="wallet-balance"><span>Available shopping credit</span><strong>₹{{ number_format(max(0, $wallet->balance)) }}</strong></div>
    @if ($wallet->balance < 0)<p>A reversed reward has left an adjustment of ₹{{ number_format(abs($wallet->balance)) }}. Future rewards will clear this first.</p>@endif
    <a class="pink-button" href="{{ url('/shop') }}">Find something you love ↗</a>
</section>
@if (!$user->hasVerifiedEmail())
    <div class="form-card"><h2>Verify your email first</h2><p>Activate your referral code by confirming your email address.</p><a href="{{ url('/email/verify') }}">Verify email →</a></div>
@elseif ($referralSettings['referral_enabled'])
    <section class="form-card wallet-share">
        <span class="eyebrow">YOUR PERSONAL CODE</span><h2>{{ $wallet->referral_code }}</h2>
        <label>Share this link<input readonly aria-label="Your referral link" value="{{ url('/account/register') . '?ref=' . $wallet->referral_code }}" onclick="this.select()"></label>
        <p>Your friend saves ₹{{ number_format($referralSettings['referral_friend_discount']) }} on their first order. Their eligible merchandise after discounts must reach ₹{{ number_format($referralSettings['referral_minimum']) }} for your reward.</p>
        <p>You earn {{ $referralSettings['referral_reward_type'] === 'percent' ? $referralSettings['referral_reward_value'] . '%' : '₹' . number_format($referralSettings['referral_reward_value']) }}, capped at ₹{{ number_format($referralSettings['referral_reward_max']) }} per qualifying order.</p>
    </section>
@else
    <div class="form-card"><h2>Referrals are paused</h2><p>Your existing wallet and reward history remain available.</p></div>
@endif
<div class="form-card"><h2>How rewards work</h2><ol class="wallet-rules"><li>Share your code with a new customer. They apply it at checkout on their first order.</li><li>The reward stays pending until delivery, payment collection and the {{ $returnDays }}-day return window are complete.</li><li>Cancelled or returned orders do not earn rewards. Open returns hold the reward.</li><li>Use available credit on merchandise at checkout. Wallet credit cannot be withdrawn or transferred. Shipping is paid separately.</li></ol>
    @if (!$referralSettings['wallet_enabled'])<p>Wallet redemption is temporarily paused.</p>@endif
</div>
<section class="form-card"><h2>Referral rewards</h2><div class="wallet-table-wrap"><table class="wallet-table"><thead><tr><th>Date</th><th>Reward</th><th>Status</th><th>Available after</th></tr></thead><tbody>
@forelse ($rewards as $reward)<tr><td>{{ \Illuminate\Support\Carbon::parse($reward->created_at)->format('d M Y') }}</td><td>₹{{ number_format($reward->amount) }}</td><td><span class="wallet-status">{{ ucfirst($reward->status) }}</span></td><td>{{ $reward->available_at ? \Illuminate\Support\Carbon::parse($reward->available_at)->format('d M Y') : 'Delivery + return window' }}</td></tr>
@empty<tr><td colspan="4">No referrals yet. Your next shared find could be the first.</td></tr>@endforelse
</tbody></table></div></section>
<section class="form-card"><h2>Wallet history</h2><div class="wallet-table-wrap"><table class="wallet-table"><thead><tr><th>Date</th><th>Details</th><th>Amount</th><th>Balance</th></tr></thead><tbody>
@forelse ($walletEntries as $entry)<tr><td>{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('d M Y') }}</td><td>{{ $entry->note }}</td><td>{{ $entry->amount > 0 ? '+' : '−' }}₹{{ number_format(abs($entry->amount)) }}</td><td>₹{{ number_format($entry->balance_after) }}</td></tr>
@empty<tr><td colspan="4">Your wallet history will appear here.</td></tr>@endforelse
</tbody></table></div>{{ $walletEntries->links() }}</section>
