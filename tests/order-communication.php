<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Mail\StoreMessage;
use App\Support\OrderWhatsApp;
use App\Support\StoreMail;
use App\Support\SellerOrders;
use App\Support\OrderFlow;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\CatalogAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

function check($condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function req(array $data = []): Request
{
    $request = Request::create('/admin', 'POST', $data);
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => Auth::user());
    app()->instance('request', $request);
    app('url')->setRequest($request);
    return $request;
}
function countMail(): int { return Mail::sent(StoreMessage::class)->count(); }

config(['session.driver' => 'array', 'mail.admin_address' => null]);
app('session')->forgetDrivers(); Auth::forgetGuards(); app('session')->driver()->start();
view()->share('errors', new Illuminate\Support\ViewErrorBag);
Mail::fake();
// Execute registered after-commit callbacks inside the rollback fixture so hooks can be
// asserted without committing customer data. Every transport is mocked.
DB::connection()->setTransactionManager(new class extends Illuminate\Database\DatabaseTransactionsManager {
    public function addCallback($callback) { $callback(); }
});
DB::beginTransaction();
try {
    $suffix = uniqid();
    $admin = User::create(['name' => 'Communication Admin', 'email' => 'comm-admin-' . $suffix . '@example.test', 'password' => 'QaOnly!123456']);
    $admin->forceFill(['role' => 'admin'])->save();
    $buyer = User::create(['name' => 'Communication Customer', 'email' => 'comm-buyer-' . $suffix . '@example.test', 'password' => 'QaOnly!123456']);
    $products = DB::table('products')->limit(2)->get();
    $items = $products->map(fn ($product) => ['id' => $product->id, 'name' => $product->name,
        'size' => 'M', 'color' => 'Pink', 'quantity' => 2, 'price' => 400,
        'category' => $product->category, 'seller_id' => null])->all();
    $createOrder = function () use ($buyer, $items) {
        $id = DB::table('orders')->insertGetId([
            'reference' => (string) Illuminate\Support\Str::uuid(), 'user_id' => $buyer->id,
            'name' => $buyer->name, 'email' => $buyer->email, 'phone' => '9876543210',
            'address' => 'Private QA delivery address', 'city' => 'Delhi', 'state' => 'Delhi', 'pincode' => '110001',
            'items' => json_encode($items), 'subtotal' => 1600, 'shipping' => 79, 'discount' => 100,
            'wallet_spent' => 50, 'total' => 1529, 'status' => 'placed', 'payment_method' => 'cod',
            'payment_status' => 'unpaid', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('orders')->find($id);
    };
    $order = $createOrder();
    check(OrderWhatsApp::phone('9876543210') === '919876543210', 'Indian mobile number gets the country code');
    check(OrderWhatsApp::phone('+91 98765-43210') === '919876543210', 'Formatted +91 mobile is supported');
    foreach (['', '123', 'bad9876543210', 'javascript:1234567890', '919999999999999'] as $invalid) {
        check(OrderWhatsApp::phone($invalid) === null, 'Invalid phone is rejected');
    }
    $link = OrderWhatsApp::url($order);
    parse_str(parse_url($link, PHP_URL_QUERY), $query);
    check(str_starts_with($link, 'https://wa.me/919876543210?text=') && str_contains($link, '%0A'), 'WhatsApp destination and line breaks are correctly encoded');
    foreach ($items as $item) check(str_contains($query['text'], $item['name']), 'WhatsApp message includes product ' . $item['name']);
    check(str_contains($query['text'], 'Wallet credit: ₹50') && str_contains($query['text'], 'Total: ₹1,529'), 'WhatsApp message has wallet credit and accurate payable total');
    check(!str_contains($query['text'], $order->address) && !str_contains($query['text'], $order->email), 'Message does not include unnecessary address or email');
    foreach (['placed', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $status) {
        $preview = clone $order; $preview->status = $status;
        check(str_contains(rawurldecode(OrderWhatsApp::url($preview)), 'Status: ' . ucfirst($status)), 'WhatsApp message reflects ' . $status);
    }

    StoreMail::order($order->reference);
    check(Mail::sent(StoreMessage::class)->last()->heading === 'Order placed', 'Order placement still sends automatic email');
    Auth::login($admin); req();
    $controller = new AdminController;
    foreach (['confirmed', 'shipped', 'delivered'] as $status) {
        $before = countMail();
        $controller->order(req(['status' => $status, 'carrier' => 'QA Courier', 'tracking_number' => 'QA-123']), $order->id);
        check(countMail() === $before + 1 && Mail::sent(StoreMessage::class)->last()->heading === 'Order ' . $status, 'Automatic email sends at order ' . $status);
    }
    $before = countMail();
    $controller->order(req(['status' => 'delivered', 'carrier' => 'QA Courier', 'tracking_number' => 'QA-123']), $order->id);
    check(countMail() === $before, 'Unchanged order save does not send another email');
    $controller->order(req(['status' => 'delivered', 'carrier' => 'QA Courier', 'tracking_number' => 'QA-456', 'payment_status' => 'paid']), $order->id);
    check(countMail() === $before + 1 && Mail::sent(StoreMessage::class)->last()->heading === 'Order details updated', 'Tracking/payment changes send email without a status change');
    $cancelled = $createOrder();
    $before = countMail(); OrderFlow::cancel($cancelled->id);
    check(countMail() === $before + 1 && Mail::sent(StoreMessage::class)->last()->heading === 'Order cancelled', 'Cancellation still sends automatic email');

    Auth::login($buyer);
    $orders = new OrdersController;
    $before = countMail();
    $orders->requestReturn(req(['reason' => 'Other', 'notes' => 'QA return notification check', 'quantities' => [0 => 1]]), $order->reference);
    check(countMail() === $before + 1 && Mail::sent(StoreMessage::class)->last()->heading === 'Return requested', 'New return request sends automatic email');
    $return = DB::table('return_requests')->where('order_id', $order->id)->latest()->first();
    Auth::login($admin);
    $catalogue = new CatalogAdminController;
    foreach (['approved', 'received', 'completed'] as $status) {
        $before = countMail();
        $catalogue->returns(req(['status' => $status, 'refund_amount' => 100, 'refund_reference' => $status === 'completed' ? 'QA-EXTERNAL-REFUND' : null]), $return->id);
        check(countMail() === $before + 1 && Mail::sent(StoreMessage::class)->last()->heading === 'Return ' . $status, 'Automatic email sends at return ' . $status);
    }
    $before = countMail();
    $catalogue->returns(req(['status' => 'completed', 'refund_amount' => 100, 'refund_reference' => 'QA-EXTERNAL-REFUND']), $return->id);
    check(countMail() === $before, 'Unchanged return save does not send another email');
    $secondReturnId = DB::table('return_requests')->insertGetId([
        'order_id' => $order->id, 'user_id' => $buyer->id, 'items' => json_encode([$items[1]]),
        'reason' => 'Other', 'notes' => 'QA rejected return', 'status' => 'requested', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $catalogue->returns(req(['status' => 'rejected', 'refund_amount' => 0]), $secondReturnId);
    check(Mail::sent(StoreMessage::class)->last()->heading === 'Return rejected', 'Rejected return sends automatic email');

    $sellerId = DB::table('sellers')->insertGetId([
        'user_id' => $admin->id, 'shop_name' => 'QA notification shop', 'slug' => 'qa-comm-' . $suffix,
        'phone' => '9876543210', 'pickup_address' => 'QA pickup address', 'city' => 'Delhi', 'state' => 'Delhi',
        'pincode' => '110001', 'status' => 'approved', 'commission_rate' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $splitOrder = $createOrder(); $splitItems = $items; $splitItems[0]['seller_id'] = $sellerId;
    SellerOrders::split($splitOrder->id, $splitItems, 0);
    $shipment = DB::table('seller_orders')->where('order_id', $splitOrder->id)->where('seller_id', $sellerId)->first();
    foreach (['confirmed', 'shipped', 'delivered'] as $status) {
        $before = countMail();
        SellerOrders::change($shipment->id, ['status' => $status, 'carrier' => 'QA split courier', 'tracking_number' => 'SPLIT-123']);
        check(countMail() === $before + 1 && Mail::sent(StoreMessage::class)->last()->heading === 'Shipment ' . $status, 'Split shipment sends email at ' . $status);
    }
    check(DB::table('orders')->where('id', $splitOrder->id)->value('status') === 'placed', 'Shipment notification does not incorrectly advance undelivered sibling shipments');
    $before = countMail();
    SellerOrders::change($shipment->id, ['status' => 'delivered', 'carrier' => 'QA split courier', 'tracking_number' => 'SPLIT-123']);
    check(countMail() === $before, 'Unchanged shipment save does not send another email');
    $mail = Mail::sent(StoreMessage::class)->last();
    check(count($mail->orderDetails['items']) === 2 && str_contains($mail->messageText, 'Items in this shipment'), 'Shipment email identifies its own items and retains the complete order');
    $shipment = DB::table('seller_orders')->find($shipment->id);
    $shipment->customer_phone = $buyer->phone ?? $order->phone; $shipment->customer_name = $buyer->name;
    check(str_contains(rawurldecode(OrderWhatsApp::shipmentUrl($shipment)), 'Shipment #' . $shipment->id . ': Delivered'), 'Shipment WhatsApp message reflects shipment status');
    $return = DB::table('return_requests')->find($return->id); $return->customer_phone = $order->phone; $return->customer = $buyer->name;
    check(str_contains(rawurldecode(OrderWhatsApp::returnUrl($return)), 'Return #' . $return->id . ': Completed'), 'Return WhatsApp message reflects return status');
    $html = $controller->index(req())->render();
    check(str_contains($html, 'Send on WhatsApp') && str_contains($html, 'Email updates continue automatically.'), 'Admin shows WhatsApp actions alongside continuing email updates');
    echo "Order communication checks passed. Fixtures rolled back; emails mocked; WhatsApp was not opened.\n";
} finally {
    Auth::logout(); DB::rollBack();
}
