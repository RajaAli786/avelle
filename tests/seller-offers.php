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

use App\Http\Controllers\SellerOfferController;
use App\Support\Shop;
use App\Support\OrderFlow;
\Illuminate\Support\Facades\Mail::fake();
DB::beginTransaction();
try {
    $users = [];
    $sellerIds = [];
    foreach (["a", "b"] as $key) {
        $u = User::create([
            "name" => "QA Seller " . $key,
            "email" => "qa-offer-" . $key . "-" . uniqid() . "@example.test",
            "password" => "OfferTest123456!",
        ]);
        $u->forceFill(["role" => "seller"])->save();
        $users[$key] = $u;
        $sellerIds[$key] = DB::table("sellers")->insertGetId([
            "user_id" => $u->id,
            "shop_name" => "QA Offer Shop " . $key,
            "slug" => "qa-offer-" . uniqid(),
            "phone" => "9876543210",
            "pickup_address" => "QA fixture pickup address",
            "city" => "Delhi",
            "state" => "Delhi",
            "pincode" => "110001",
            "status" => "approved",
            "commission_rate" => 10,
            "created_at" => now(),
            "updated_at" => now(),
        ]);
    }
    $admin = User::create([
        "name" => "QA Offer Admin",
        "email" => "qa-offer-admin-" . uniqid() . "@example.test",
        "password" => "OfferAdmin123456!",
    ]);
    $admin->forceFill(["role" => "admin"])->save();
    $buyer = User::create([
        "name" => "QA Offer Buyer",
        "email" => "qa-offer-buyer-" . uniqid() . "@example.test",
        "password" => "OfferBuyer123456!",
    ]);
    $cat = DB::table("categories")->insertGetId([
        "name" => "QA offer category " . uniqid(),
        "slug" => "qa-offer-" . uniqid(),
        "active" => true,
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    $category = DB::table("categories")->where("id", $cat)->value("name");
    $base = [
        "name" => "QA Seller Find",
        "category" => $category,
        "category_id" => $cat,
        "price" => 500,
        "old" => 900,
        "color" => "Pink",
        "image" => "photo-1515372039744-b8f02a3ae446",
        "tag" => "QA",
        "sizes" => '["M"]',
        "stock" => 20,
        "rating" => 0,
        "seller_status" => "approved",
        "active" => true,
        "created_at" => now(),
        "updated_at" => now(),
    ];
    $a = DB::table("products")->insertGetId($base + ["seller_id" => $sellerIds["a"]]);
    $b = DB::table("products")->insertGetId(
        array_merge($base, ["seller_id" => $sellerIds["b"], "price" => 900]),
    );
    $offers = new SellerOfferController();
    $code = "QA_SHOP_" . strtoupper(uniqid());
    $input = [
        "code" => $code,
        "type" => "percent",
        "value" => 10,
        "minimum" => 0,
        "scope" => "store",
        "active" => 1,
        "show_banner" => 1,
        "banner_mode" => "both",
        "seller_id" => $sellerIds["b"],
        "kind" => "referral",
        "used" => 999,
        "admin_locked" => 1,
    ];
    Auth::login($users["a"]);
    $saved = $offers->save(req($input));
    $p = DB::table("promotions")->where("code", $code)->first();
    check(
        (int) $p->seller_id === $sellerIds["a"] &&
            $p->kind === "coupon" &&
            !$p->admin_locked &&
            $p->used === 0,
        "Seller coupon ownership and protected fields are server controlled",
    );
    check(
        str_ends_with($saved->getTargetUrl(), "/seller#seller-offers"),
        "Coupon saves return to seller offers section",
    );
    Auth::login($users["b"]);
    denied(fn() => $offers->save(req($input + ["id" => $p->id])), "Seller cannot edit another seller coupon");
    denied(fn() => $offers->toggle($p->id), "Seller cannot toggle another seller coupon");
    $buyer->markEmailAsVerified();
    Auth::login($buyer);
    denied(fn() => $offers->save(req($input)), "Customer cannot create seller coupons");
    Auth::login($users["a"]);
    rejects(
        fn() => $offers->save(
            req(array_merge($input, ["id" => $p->id, "scope" => "product", "targets" => (string) $b])),
        ),
        "Seller cannot target another seller product",
    );
    rejects(
        fn() => $offers->save(
            req(array_merge($input, ["id" => $p->id, "scope" => "category", "targets" => "Not my category"])),
        ),
        "Seller cannot target categories absent from own catalogue",
    );
    rejects(
        fn() => $offers->save(req(array_merge($input, ["id" => $p->id, "value" => 101]))),
        "Percentage above 100 is rejected",
    );
    $bag = [["id" => $a, "size" => "M", "quantity" => 1], ["id" => $b, "size" => "M", "quantity" => 1]];
    $store = new StoreController();
    $quote = fn($items = null) => $store
        ->quote(req(["items" => $items ?? $bag, "code" => $code]))
        ->getData(true);
    check($quote()["discount"] === 50, "All-my-products coupon discounts only seller merchandise");
    rejects(
        fn() => $quote([["id" => $b, "size" => "M", "quantity" => 1]]),
        "Seller coupon rejects bags containing only other sellers",
    );
    $offers->save(req(array_merge($input, ["id" => $p->id, "minimum" => 600])));
    rejects(fn() => $quote(), "Seller minimum is checked against eligible subtotal, not mixed bag total");
    $offers->save(
        req(
            array_merge($input, [
                "id" => $p->id,
                "minimum" => 500,
                "maximum" => 30,
                "scope" => "category",
                "targets" => $category,
            ]),
        ),
    );
    check($quote()["discount"] === 30, "Category coupon remains seller-scoped and respects savings cap");
    $visible = collect(StoreOffers::visible())->firstWhere("code", $code);
    check(
        $visible &&
            $visible["seller_id"] == $sellerIds["a"] &&
            str_contains($visible["scope_label"], "QA Offer Shop a") &&
            !array_key_exists("owner_email", $visible),
        "Public offer includes shop attribution without private owner email",
    );
    $offers->toggle($p->id);
    rejects(fn() => $quote(), "Seller can pause their coupon");
    check(
        !collect(StoreOffers::visible())->contains("code", $code),
        "Paused seller coupon disappears from advertising",
    );
    $offers->toggle($p->id);
    Auth::login($admin);
    (new AdminController())->toggle(req(), $p->id);
    Auth::login($users["a"]);
    rejects(fn() => $offers->toggle($p->id), "Seller cannot bypass an admin coupon lock");
    rejects(
        fn() => $offers->save(req(array_merge($input, ["id" => $p->id]))),
        "Editing cannot reactivate admin-locked coupon",
    );
    Auth::login($admin);
    (new AdminController())->toggle(req(), $p->id);
    check(
        DB::table("promotions")->where("id", $p->id)->value("active") &&
            !DB::table("promotions")->where("id", $p->id)->value("admin_locked"),
        "Admin can unlock a seller coupon",
    );
    Auth::login($users["a"]);
    $offers->save(
        req(
            array_merge($input, [
                "id" => $p->id,
                "type" => "fixed",
                "value" => 101,
                "maximum" => null,
                "scope" => "product",
                "targets" => (string) $a,
                "usage_limit" => 1,
            ]),
        ),
    );
    DB::table("promotions")
        ->where("id", $p->id)
        ->update(["expires_at" => now()->subMinute()]);
    rejects(fn() => $quote(), "Expired seller coupon rejected");
    DB::table("promotions")
        ->where("id", $p->id)
        ->update(["expires_at" => null]);
    DB::table("sellers")
        ->where("id", $sellerIds["a"])
        ->update(["status" => "suspended"]);
    check(!collect(StoreOffers::visible())->contains("code", $code), "Suspension hides seller offers");
    denied(fn() => $offers->toggle($p->id), "Suspended seller cannot manage coupons");
    DB::table("sellers")
        ->where("id", $sellerIds["a"])
        ->update(["status" => "approved"]);
    Auth::login($buyer);
    $checkout = [
        "items" => $bag,
        "code" => $code,
        "name" => "QA Offer Buyer",
        "email" => $buyer->email,
        "phone" => "9876543210",
        "address" => "QA offer buyer delivery",
        "city" => "Delhi",
        "state" => "Delhi",
        "pincode" => "110001",
    ];
    $result = $store->checkout(req($checkout))->getData(true);
    $order = DB::table("orders")->where("reference", $result["reference"])->first();
    $groups = DB::table("seller_orders")->where("order_id", $order->id)->get();
    check(
        $order->discount === 101 &&
            $groups->firstWhere("seller_id", $sellerIds["a"])->discount === 101 &&
            $groups->firstWhere("seller_id", $sellerIds["b"])->discount === 0,
        "Checkout allocates seller coupon only to its seller shipment",
    );
    check(
        DB::table("promotions")->where("id", $p->id)->value("used") === 1,
        "Seller coupon usage counted at checkout",
    );
    rejects(fn() => $quote(), "Exhausted seller coupon rejected");
    check(!collect(StoreOffers::visible())->contains("code", $code), "Exhausted seller coupon hidden");
    Auth::login($users["a"]);
    rejects(
        fn() => $offers->save(req(array_merge($input, ["id" => $p->id, "code" => "QA_RENAME_" . uniqid()]))),
        "Used seller coupon code cannot be renamed",
    );
    Auth::login($buyer);
    OrderFlow::cancel($order->id);
    check(
        DB::table("promotions")->where("id", $p->id)->value("used") === 0,
        "Cancellation releases seller coupon usage",
    );
    Auth::login($users["a"]);
    $html = (new \App\Http\Controllers\SellerController())->index()->render();
    check(
        str_contains($html, "SELLER CENTRAL") &&
            str_contains($html, "Offers &amp; coupons") &&
            str_contains($html, $code),
        "AdminLTE seller UI renders own coupon controls",
    );
    echo "Seller offer checks passed. Fixtures rolled back.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
