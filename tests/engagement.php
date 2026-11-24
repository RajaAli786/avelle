<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Support\EmailVerification;
use App\Support\MailDelivery;
use App\Support\ReferralWallet;
use App\Support\OrderFlow;
use App\Support\StoreMail;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\AccountController;
use App\Mail\StoreMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

function check($condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function requestFor(string $uri = '/checkout', array $data = [], string $method = 'POST'): Request
{
    $request = Request::create($uri, $method, $data);
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => Auth::user());
    app()->instance('request', $request);
    app('url')->setRequest($request);
    return $request;
}
function rejects(callable $callback, string $label): void
{
    try { $callback(); } catch (ValidationException $exception) { check(true, $label); return; }
    throw new RuntimeException('Expected validation rejection: ' . $label);
}
function denied(callable $callback, string $label): void
{
    try { $callback(); } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        check($exception->getStatusCode() === 403, $label); return;
    }
    throw new RuntimeException('Expected access rejection: ' . $label);
}

config(['session.driver' => 'array', 'cache.default' => 'array', 'mail.default' => 'smtp']);
app('session')->forgetDrivers();
Auth::forgetGuards();
app('session')->driver()->start();
view()->share('errors', new Illuminate\Support\ViewErrorBag);
Mail::fake();
$fakeMail = Mail::getFacadeRoot();
DB::beginTransaction();
try {
    requestFor();
    $suffix = uniqid();
    $owner = User::create(['name' => 'Referral Owner', 'email' => 'owner-' . $suffix . '@example.test', 'password' => 'TestOnly!123456']);
    $buyer = User::create(['name' => 'Referral Buyer', 'email' => 'buyer-' . $suffix . '@example.test', 'password' => 'TestOnly!123456']);
    $admin = User::create(['name' => 'Referral Admin', 'email' => 'admin-' . $suffix . '@example.test', 'password' => 'TestOnly!123456']);
    $admin->role = 'admin'; $admin->save();
    Auth::login($buyer);
    $store = new StoreController;
    check($store->checkout(requestFor())->getStatusCode() === 403, 'Unverified order API is blocked before an order is created');
    check(str_ends_with((new MarketplaceController)->checkout()->getTargetUrl(), '/email/verify'), 'Checkout asks an unverified customer to verify email');

    $signed = EmailVerification::url($buyer);
    check(URL::hasValidSignature(requestFor($signed, [], 'GET')), 'Verification link has a valid expiring signature');
    denied(fn () => (new VerificationController)->verify(requestFor($signed . 'x', [], 'GET'), $buyer->id, sha1($buyer->email)), 'Modified verification signature is rejected');
    $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $buyer->id, 'hash' => sha1($buyer->email)]);
    denied(fn () => (new VerificationController)->verify(requestFor($expired, [], 'GET'), $buyer->id, sha1($buyer->email)), 'Expired verification link is rejected');
    Auth::login($owner);
    denied(fn () => (new VerificationController)->verify(requestFor($signed, [], 'GET'), $buyer->id, sha1($buyer->email)), 'Another account cannot verify the email link');
    Auth::login($buyer);
    app('session')->put('url.intended', url('/checkout'));
    $verified = (new VerificationController)->verify(requestFor($signed, [], 'GET'), $buyer->id, sha1($buyer->email));
    check($buyer->fresh()->hasVerifiedEmail() && str_ends_with($verified->getTargetUrl(), '/checkout'), 'Valid verification marks the account and returns to checkout');
    $owner->markEmailAsVerified();

    $settings = ['referral_enabled' => 1, 'wallet_enabled' => 1, 'referral_reward_type' => 'fixed',
        'referral_reward_value' => 50, 'referral_reward_max' => 100, 'referral_minimum' => 100, 'referral_friend_discount' => 10];
    denied(fn () => (new EngagementController)->settings(requestFor('/admin/referrals/settings', $settings)), 'Customer cannot change referral rules');
    denied(fn () => (new EngagementController)->retry(requestFor(), 1), 'Customer cannot retry store email');
    Auth::login($admin);
    (new EngagementController)->settings(requestFor('/admin/referrals/settings', $settings));
    check(ReferralWallet::settings()['referral_enabled'] == 1, 'Admin enables referral rules');
    rejects(fn () => (new EngagementController)->settings(requestFor('/admin/referrals/settings', array_merge($settings, ['referral_reward_type' => 'percent', 'referral_reward_value' => 101]))), 'Admin percentage reward is bounded to 100');
    $wallet = ReferralWallet::account($owner->id);
    check($wallet->referral_code === ReferralWallet::account($owner->id)->referral_code, 'Customer referral code stays stable');
    Auth::login($owner);
    rejects(fn () => $store->quote(requestFor('/api/promotions/quote', ['items' => [['id' => 1, 'size' => 'M', 'quantity' => 1]], 'code' => $wallet->referral_code])), 'Self referral is blocked');

    Auth::login($buyer);
    $product = DB::table('products')->where('active', true)->where('stock', '>', 5)->where('price', '>', 200)->firstOrFail();
    $variant = DB::table('product_variants')->where('product_id', $product->id)->where('active', true)->where('stock', '>', 2)->first();
    $item = ['id' => $product->id, 'size' => $variant?->size ?? json_decode($product->sizes, true)[0], 'quantity' => 1, 'variant_id' => $variant?->id];
    $payload = ['items' => [$item], 'code' => $wallet->referral_code, 'name' => 'Referral Buyer', 'email' => $buyer->email,
        'phone' => '9876543210', 'address' => 'Test delivery address', 'city' => 'Delhi', 'state' => 'Delhi', 'pincode' => '110001'];
    $quote = $store->quote(requestFor('/api/promotions/quote', $payload))->getData(true);
    check($quote['discount'] === 10, 'Personal referral code gives the configured first-order discount');
    $result = $store->checkout(requestFor('/api/orders', $payload))->getData(true);
    $order = DB::table('orders')->where('reference', $result['reference'])->firstOrFail();
    $reward = DB::table('referral_rewards')->where('order_id', $order->id)->firstOrFail();
    check($reward->amount === 50 && $reward->status === 'pending', 'First referral order creates a pending reward snapshot');
    ReferralWallet::sync($order->id);
    check(ReferralWallet::account($owner->id)->balance === 0, 'Undelivered order cannot credit a reward');
    DB::table('orders')->where('id', $order->id)->update(['status' => 'delivered', 'payment_status' => 'paid', 'delivered_at' => now()]);
    ReferralWallet::sync($order->id);
    check(ReferralWallet::account($owner->id)->balance === 0, 'Reward waits for the return window');
    DB::table('orders')->where('id', $order->id)->update(['delivered_at' => now()->subDays(40), 'payment_status' => 'unpaid']);
    ReferralWallet::sync($order->id);
    check(ReferralWallet::account($owner->id)->balance === 0, 'Reward requires collected payment');
    DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'paid']);
    $returnId = DB::table('return_requests')->insertGetId(['order_id' => $order->id, 'user_id' => $buyer->id, 'items' => $order->items,
        'reason' => 'Other', 'notes' => 'QA return', 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
    ReferralWallet::sync($order->id);
    check(ReferralWallet::account($owner->id)->balance === 0, 'Open return holds referral reward');
    DB::table('return_requests')->where('id', $returnId)->update(['status' => 'rejected']);
    ReferralWallet::sync($order->id); ReferralWallet::sync($order->id);
    check((int) ReferralWallet::account($owner->id)->balance === 50 && DB::table('wallet_entries')->where('entry_key', 'reward-credit:' . $reward->id)->count() === 1, 'Eligible reward credits exactly once');
    rejects(fn () => $store->quote(requestFor('/api/promotions/quote', $payload)), 'Repeat buyer cannot use another first-order referral');
    DB::table('return_requests')->where('id', $returnId)->update(['status' => 'completed']);
    ReferralWallet::sync($order->id); ReferralWallet::sync($order->id);
    check((int) ReferralWallet::account($owner->id)->balance === 0 && DB::table('referral_rewards')->where('id', $reward->id)->value('status') === 'reversed', 'Completed return reverses credited reward exactly once');

    // Shopping credit is separate from coupon discount and the seller settlement snapshot.
    ReferralWallet::entry($buyer->id, 75, 'qa-seed:' . $suffix, 'referral', 'QA shopping credit');
    $payload['code'] = null; $payload['use_wallet'] = 1; $payload['checkout_key'] = (string) Illuminate\Support\Str::uuid();
    $walletQuote = $store->quote(requestFor('/api/promotions/quote', $payload))->getData(true);
    check($walletQuote['wallet_used'] === 75, 'Quote includes optional available wallet credit');
    $result = $store->checkout(requestFor('/api/orders', $payload))->getData(true);
    $walletOrder = DB::table('orders')->where('reference', $result['reference'])->firstOrFail();
    $store->checkout(requestFor('/api/orders', $payload));
    check((int) $walletOrder->wallet_spent === 75 && (int) ReferralWallet::account($buyer->id)->balance === 0, 'Checkout debits credit once and retry does not charge again');
    check($walletOrder->total === $walletOrder->subtotal + $walletOrder->shipping - $walletOrder->discount - 75, 'COD payable amount subtracts wallet credit correctly');
    OrderFlow::cancel($walletOrder->id); OrderFlow::cancel($walletOrder->id);
    check((int) ReferralWallet::account($buyer->id)->balance === 75, 'Cancellation restores spent wallet credit exactly once');
    $payload['checkout_key'] = (string) Illuminate\Support\Str::uuid();
    $result = $store->checkout(requestFor('/api/orders', $payload))->getData(true);
    $returnedOrder = DB::table('orders')->where('reference', $result['reference'])->firstOrFail();
    DB::table('orders')->where('id', $returnedOrder->id)->update(['status' => 'delivered', 'delivered_at' => now()]);
    DB::table('return_requests')->insert(['order_id' => $returnedOrder->id, 'user_id' => $buyer->id, 'items' => $returnedOrder->items,
        'reason' => 'Other', 'notes' => 'QA wallet return', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
    ReferralWallet::sync($returnedOrder->id); ReferralWallet::sync($returnedOrder->id);
    check((int) ReferralWallet::account($buyer->id)->balance === 75, 'Completed full return restores wallet share once');
    $shareItems = [
        ['id' => 101, 'name' => 'Discounted item', 'price' => 100, 'quantity' => 1, 'size' => 'M', 'category' => 'QA', 'seller_id' => null],
        ['id' => 102, 'name' => 'Paid item', 'price' => 300, 'quantity' => 3, 'size' => 'M', 'category' => 'QA', 'seller_id' => null],
    ];
    $targeted = (object) ['seller_id' => null, 'scope' => 'product', 'targets' => '[101]'];
    $shareItems = ReferralWallet::withWalletShares($shareItems, 100, $targeted, 100);
    check($shareItems[0]['wallet_share'] === 0 && $shareItems[1]['wallet_share'] === 100, 'Targeted coupon and wallet shares reconcile without credit on a free item');
    ReferralWallet::entry($buyer->id, 25, 'qa-rounding:' . $suffix, 'referral', 'QA rounding balance');
    $partial = (array) $returnedOrder;
    unset($partial['id']);
    $partial = array_merge($partial, ['reference' => (string) Illuminate\Support\Str::uuid(), 'checkout_key' => null,
        'items' => json_encode($shareItems), 'subtotal' => 1000, 'discount' => 100, 'wallet_spent' => 100,
        'shipping' => 0, 'total' => 800, 'status' => 'delivered', 'delivered_at' => now()]);
    $partialId = DB::table('orders')->insertGetId($partial);
    ReferralWallet::spend($buyer->id, $partialId, 100);
    foreach ([[0, 0], [1, 33], [1, 66], [1, 100]] as [$line, $expected]) {
        DB::table('return_requests')->insert(['order_id' => $partialId, 'user_id' => $buyer->id,
            'items' => json_encode([array_merge($shareItems[$line], ['line' => $line, 'quantity' => 1])]),
            'reason' => 'Other', 'notes' => 'QA partial wallet return', 'status' => 'completed',
            'created_at' => now(), 'updated_at' => now()]);
        ReferralWallet::sync($partialId);
        check((int) ReferralWallet::account($buyer->id)->balance === $expected, 'Partial return credit reconciles to ₹' . $expected);
    }
    $html = (new AccountController)->index(requestFor('/account/wallet', [], 'GET'))->render();
    check(str_contains($html, 'Wallet history') && !str_contains($html, $owner->email), 'Customer wallet shows only their own history');

    // Simulate SMTP outage; no network mail transport is used in this suite.
    Mail::swap(new class {
        public function to($recipient) { return $this; }
        public function send($message) { throw new RuntimeException('Simulated SMTP outage'); }
    });
    check(!StoreMail::send($buyer->email, new StoreMessage('Retry QA', 'Private test payload')), 'SMTP failure does not crash the application');
    $delivery = DB::table('store_mail_deliveries')->latest('id')->firstOrFail();
    check($delivery->status === 'retry' && $delivery->attempts === 1 && !str_contains($delivery->payload, 'Private test payload'), 'Failed mail is retained encrypted with retry schedule');
    check(!MailDelivery::attempt($delivery->id) && DB::table('store_mail_deliveries')->where('id', $delivery->id)->value('attempts') === 1, 'Retry respects its backoff time');
    DB::table('store_mail_deliveries')->where('id', $delivery->id)->update(['locked_at' => now(), 'lock_token' => 'another-worker', 'status' => 'sending']);
    check(!MailDelivery::attempt($delivery->id, true), 'Concurrent worker claim prevents duplicate send');
    DB::table('store_mail_deliveries')->where('id', $delivery->id)->update(['locked_at' => null, 'lock_token' => null, 'status' => 'retry', 'attempts' => 4, 'next_attempt_at' => now()->subMinute()]);
    MailDelivery::attempt($delivery->id);
    check(DB::table('store_mail_deliveries')->where('id', $delivery->id)->value('status') === 'failed', 'Automatic retry stops after five failed attempts');
    Mail::swap($fakeMail);
    check(MailDelivery::attempt($delivery->id, true), 'Manual retry can deliver after SMTP is fixed');
    $sent = DB::table('store_mail_deliveries')->where('id', $delivery->id)->first();
    check($sent->status === 'sent' && $sent->payload === null && !MailDelivery::attempt($delivery->id, true), 'Sent email clears sensitive payload and cannot be resent');
    check(StoreMail::send($buyer->email, new StoreMessage('Rendered QA', 'Hello')), 'Healthy mail delivery remains compatible');
    config(['mail.default' => 'log']);
    StoreMail::send($buyer->email, new StoreMessage('Log QA', 'Hello'));
    check(DB::table('store_mail_deliveries')->latest('id')->value('status') === 'logged', 'Log transport is distinguished from externally sent mail');
    Auth::login($admin);
    $adminHtml = (new App\Http\Controllers\AdminController)->index(requestFor('/admin', [], 'GET'))->render();
    check(str_contains($adminHtml, 'Program settings') && str_contains($adminHtml, 'Email delivery') && str_contains($adminHtml, 'Wallet transactions'), 'Admin renders referral rules, ledger and email retry table');
    echo "Engagement checks passed. Fixtures rolled back; all mail transports were mocked.\n";
} finally {
    Mail::swap($fakeMail);
    Auth::logout();
    DB::rollBack();
}
