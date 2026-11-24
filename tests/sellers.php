<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Models\User;
use App\Support\StoreOffers;

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS " . $message . PHP_EOL;
}
function rejects($fn, $message)
{
    try {
        $fn();
    } catch (ValidationException $e) {
        check(true, $message);
        return;
    }
    throw new RuntimeException("Expected validation rejection: " . $message);
}
function denied($fn, $message)
{
    try {
        $fn();
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        check($e->getStatusCode() === 403, $message);
        return;
    }
    throw new RuntimeException("Expected access rejection: " . $message);
}
config(["session.driver" => "array"]);
app("session")->forgetDrivers();
Auth::forgetGuards();
$session = app("session")->driver();
$session->start();
function req($data = [])
{
    $r = Request::create("/", "POST", $data);
    $r->setLaravelSession(app("session")->driver());
    $r->setUserResolver(fn() => Auth::user());
    return $r;
}
view()->share("errors", new Illuminate\Support\ViewErrorBag());

use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerAdminController;
use App\Http\Controllers\CatalogAdminController;
use App\Support\SellerOrders;
use App\Support\Inventory;
use App\Support\Shop;
use App\Support\OrderFlow;

\Illuminate\Support\Facades\Mail::fake();
DB::beginTransaction();
try {
    $sc = new SellerController();
    $sa = new SellerAdminController();
    $business = [
        "shop_name" => "QA Seller A",
        "phone" => "9876543210",
        "pickup_address" => "QA pickup address for seller",
        "city" => "Delhi",
        "state" => "Delhi",
        "pincode" => "110001",
        "description" => "QA business profile",
    ];
    $sc->apply(
        req(
            $business + [
                "name" => "QA Seller Owner",
                "email" => "seller-a-" . uniqid() . "@example.test",
                "password" => "SellerPassword123!",
                "password_confirmation" => "SellerPassword123!",
                "role" => "admin",
                "status" => "approved",
                "commission_rate" => 0,
            ],
        ),
    );
    $uA = Auth::user();
    $sA = DB::table("sellers")->where("user_id", $uA->id)->first();
    check(
        $sA->status === "pending" && $uA->role === "seller" && (float) $sA->commission_rate === 10.0,
        "Seller application cannot self-approve or set commission / admin role",
    );
    check(
        str_contains($sc->index()->render(), "Your seller profile is pending"),
        "Pending seller dashboard explains approval",
    );
    denied(fn() => $sc->product(req()), "Pending seller cannot create products");
    rejects(fn() => $sc->apply(req($business)), "Duplicate seller application rejected");
    Auth::logout();
    $sc->apply(
        req(
            array_merge($business, [
                "shop_name" => "QA Seller B",
                "name" => "QA Second Seller",
                "email" => "seller-b-" . uniqid() . "@example.test",
                "password" => "SellerPassword123!",
                "password_confirmation" => "SellerPassword123!",
            ]),
        ),
    );
    $uB = Auth::user();
    $sB = DB::table("sellers")->where("user_id", $uB->id)->first();
    $admin = User::create([
        "name" => "QA Admin",
        "email" => "seller-admin-" . uniqid() . "@example.test",
        "password" => "AdminPassword123!",
    ]);
    $admin->forceFill(["role" => "admin"])->save();
    Auth::login($admin);
    $sa->review(req(["status" => "approved", "commission_rate" => 12.5]), $sA->id);
    $sa->review(req(["status" => "approved", "commission_rate" => 8]), $sB->id);
    check(
        DB::table("sellers")
            ->where("status", "approved")
            ->whereIn("id", [$sA->id, $sB->id])
            ->count() === 2,
        "Admin approves sellers and sets commission",
    );
    $cat = DB::table("categories")->insertGetId([
        "name" => "QA Seller Category " . uniqid(),
        "slug" => "qa-seller-" . uniqid(),
        "active" => true,
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    $data = [
        "name" => "QA Seller Product A",
        "category_id" => $cat,
        "audience" => "women",
        "description" => "This is a product description for seller regression checks.",
        "price" => 500,
        "old" => 800,
        "color" => "Pink",
        "sizes" => "M",
        "stock" => 12,
        "active" => 1,
        "featured" => 1,
        "image" => "photo-1515372039744-b8f02a3ae446",
        "seller_status" => "approved",
        "seller_id" => $sB->id,
    ];
    Auth::login($uA);
    $sc->product(req($data));
    $pA = DB::table("products")->latest("id")->first();
    check(
        (int) $pA->seller_id === (int) $sA->id && $pA->seller_status === "pending" && !$pA->featured,
        "Seller product ownership and review status are server controlled",
    );
    check(!Shop::productQuery()->where("products.id", $pA->id)->exists(), "Unapproved product stays hidden");
    Auth::login($uB);
    denied(fn() => $sc->product(req($data + ["id" => $pA->id])), "Seller cannot edit another seller product");
    denied(fn() => $sc->variants(req(), $pA->id), "Seller cannot edit another seller variants");
    denied(fn() => $sc->mediaOrder(req(), $pA->id), "Seller cannot reorder another seller images");
    $sc->product(req(array_merge($data, ["name" => "QA Private Product B"])));
    $pB = DB::table("products")->latest("id")->first();
    Auth::login($admin);
    $sa->product(req(["seller_status" => "approved"]), $pA->id);
    $sa->product(req(["seller_status" => "approved"]), $pB->id);
    check(
        Shop::productQuery()
            ->whereIn("products.id", [$pA->id, $pB->id])
            ->count() === 2,
        "Approved seller products become shoppable",
    );
    Auth::login($uA);
    $sc->variants(
        req(["variants" => [["size" => "M", "color" => "Pink", "stock" => 12, "active" => 1]]]),
        $pA->id,
    );
    check(
        DB::table("products")->where("id", $pA->id)->value("seller_status") === "pending",
        "Seller variant changes require moderation again",
    );
    Auth::login($admin);
    $sa->product(req(["seller_status" => "approved"]), $pA->id);
    $variant = DB::table("product_variants")->where("product_id", $pA->id)->first();
    $houseData = $data;
    $houseData["name"] = "QA Store Product";
    unset($houseData["seller_id"], $houseData["seller_status"]);
    (new CatalogAdminController())->product(req($houseData));
    $house = DB::table("products")->latest("id")->first();
    $buyer = User::create([
        "name" => "QA Buyer",
        "email" => "seller-buyer-" . uniqid() . "@example.test",
        "password" => "BuyerPassword123!",
    ]);
    $buyer->markEmailAsVerified();
    Auth::login($buyer);
    denied(
        fn() => $sa->review(req(["status" => "approved", "commission_rate" => 0]), $sA->id),
        "Customer cannot approve a seller",
    );
    denied(fn() => $sc->index(), "Ordinary customer cannot access a seller dashboard");
    $code = "QA_SELLER_" . strtoupper(uniqid());
    DB::table("promotions")->insert([
        "code" => $code,
        "kind" => "coupon",
        "type" => "fixed",
        "value" => 101,
        "minimum" => 0,
        "scope" => "product",
        "targets" => json_encode([(string) $pA->id]),
        "active" => true,
    ]);
    $bag = [
        ["id" => $pA->id, "variant_id" => $variant->id, "size" => "M", "quantity" => 2],
        ["id" => $pB->id, "size" => "M", "quantity" => 1],
        ["id" => $house->id, "size" => "M", "quantity" => 1],
    ];
    $checkout = [
        "items" => $bag,
        "code" => $code,
        "name" => "QA Buyer",
        "email" => $buyer->email,
        "phone" => "9876543210",
        "address" => "QA buyer delivery address",
        "city" => "Delhi",
        "state" => "Delhi",
        "pincode" => "110001",
        "checkout_key" => (string) \Illuminate\Support\Str::uuid(),
    ];
    $store = new StoreController();
    $result = $store->checkout(req($checkout))->getData(true);
    $o = DB::table("orders")->where("reference", $result["reference"])->first();
    $shipments = DB::table("seller_orders")->where("order_id", $o->id)->get();
    check($shipments->count() === 3, "Mixed cart splits into seller and store shipments");
    $a = $shipments->firstWhere("seller_id", $sA->id);
    $b = $shipments->firstWhere("seller_id", $sB->id);
    $h = $shipments->whereNull("seller_id")->first();
    check(
        $a->discount === 101 &&
            $b->discount === 0 &&
            $h->discount === 0 &&
            $shipments->sum("discount") === $o->discount,
        "Targeted discount allocated only to eligible seller items",
    );
    check(
        $a->net_amount === 899 && $a->commission_amount === 112 && $a->seller_amount === 787,
        "Seller commission calculated from discounted merchandise",
    );
    check(
        $shipments->sum("net_amount") + $o->shipping === $o->total,
        "Split amounts reconcile with customer total",
    );
    $store->checkout(req($checkout));
    check(
        DB::table("seller_orders")->where("order_id", $o->id)->count() === 3,
        "Checkout retry does not duplicate seller shipments",
    );
    Auth::login($uA);
    $html = $sc->index()->render();
    check(
        str_contains($html, "QA Seller Product A") && !str_contains($html, "QA Private Product B"),
        "Seller dashboard excludes other seller products and order items",
    );
    denied(
        fn() => $sc->shipment(req(["status" => "confirmed"]), $b->id),
        "Seller cannot change another seller shipment",
    );
    rejects(
        fn() => $sc->shipment(req(["status" => "delivered"]), $a->id),
        "Shipment status cannot skip dispatch steps",
    );
    $sc->shipment(req(["status" => "confirmed"]), $a->id);
    rejects(
        fn() => $sc->shipment(req(["status" => "shipped"]), $a->id),
        "Dispatch requires carrier and tracking number",
    );
    $sc->shipment(
        req(["status" => "shipped", "carrier" => "QA Courier", "tracking_number" => "QA123"]),
        $a->id,
    );
    check(
        DB::table("orders")->where("id", $o->id)->value("status") === "placed",
        "Parent remains placed while other shipments are placed",
    );
    Auth::login($buyer);
    rejects(fn() => OrderFlow::cancel($o->id), "Partially dispatched order cannot be cancelled");
    Auth::login($admin);
    $sa->review(req(["status" => "suspended", "commission_rate" => 12.5]), $sA->id);
    check(
        !Shop::productQuery()->where("products.id", $pA->id)->exists(),
        "Suspended seller products disappear",
    );
    rejects(fn() => Inventory::cart(["items" => [$bag[0]]]), "Suspended seller product cannot be ordered");
    Auth::login($uA);
    denied(
        fn() => $sc->shipment(req(["status" => "delivered"]), $a->id),
        "Suspended seller cannot update fulfilment",
    );
    Auth::login($admin);
    $sa->review(req(["status" => "approved", "commission_rate" => 20]), $sA->id);
    check(
        (float) DB::table("seller_orders")->where("id", $a->id)->value("commission_rate") === 12.5,
        "Existing commission snapshot survives later rate change",
    );
    SellerOrders::change($a->id, ["status" => "delivered"]);
    foreach ([$b, $h] as $row) {
        SellerOrders::change($row->id, ["status" => "confirmed"]);
        SellerOrders::change($row->id, [
            "status" => "shipped",
            "carrier" => "QA Courier",
            "tracking_number" => "QA-" . $row->id,
        ]);
        SellerOrders::change($row->id, ["status" => "delivered"]);
    }
    check(
        DB::table("orders")->where("id", $o->id)->value("status") === "delivered",
        "Parent becomes delivered only after all shipments deliver",
    );
    rejects(
        fn() => $sa->payout(req(["payout_reference" => "QA-EXTERNAL"]), $a->id),
        "Payout record requires collected payment",
    );
    (new AdminController())->order(req(["status" => "delivered", "payment_status" => "paid"]), $o->id);
    rejects(
        fn() => $sa->payout(req(["payout_reference" => "QA-EXTERNAL"]), $a->id),
        "Payout waits until return window ends",
    );
    DB::table("orders")
        ->where("id", $o->id)
        ->update(["delivered_at" => now()->subDays(40)]);
    $returnId = DB::table("return_requests")->insertGetId([
        "order_id" => $o->id,
        "user_id" => $buyer->id,
        "items" => "[]",
        "reason" => "Other",
        "notes" => "QA return",
        "status" => "requested",
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    rejects(
        fn() => $sa->payout(req(["payout_reference" => "QA-EXTERNAL"]), $a->id),
        "Orders with returns block unadjusted payout",
    );
    DB::table("return_requests")
        ->where("id", $returnId)
        ->update(["status" => "rejected"]);
    $sa->payout(req(["payout_reference" => "QA-EXTERNAL"]), $a->id);
    check(
        DB::table("seller_orders")->where("id", $a->id)->value("payout_reference") === "QA-EXTERNAL",
        "Admin can record an eligible external payout",
    );
    rejects(
        fn() => $sa->payout(req(["payout_reference" => "DUPLICATE"]), $a->id),
        "Duplicate payout recording rejected",
    );
    $adminHtml = (new AdminController())->index(req())->render();
    check(
        str_contains($adminHtml, "Seller applications") &&
            str_contains($adminHtml, "Seller orders & payout records"),
        "Admin seller approval and settlement screens render",
    );
    Auth::login($buyer);
    $own = (new \App\Http\Controllers\OrdersController())->show($o->reference)->render();
    check(
        str_contains($own, "Your shipments") && str_contains($own, "QA Courier"),
        "Buyer sees own split shipments and tracking",
    );
    echo "Seller checks passed. Fixtures rolled back.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
