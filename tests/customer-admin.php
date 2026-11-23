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

use App\Http\Controllers\ShoppingController;
use App\Http\Controllers\MarketplaceController;
use App\Support\ReviewEligibility;
use App\Support\SellerOrders;
\Illuminate\Support\Facades\Mail::fake();
DB::beginTransaction();
function pageReq(string $path, array $data = [])
{
    $r = Request::create($path, "GET", $data);
    $r->setLaravelSession(app("session")->driver());
    $r->setUserResolver(fn() => Auth::user());
    return $r;
}
try {
    $buyer = User::create([
        "name" => "QA Account Buyer",
        "email" => "qa-account-buyer-" . uniqid() . "@example.test",
        "password" => "BuyerPassword123!",
    ]);
    $other = User::create([
        "name" => "QA Private Buyer",
        "email" => "qa-private-buyer-" . uniqid() . "@example.test",
        "password" => "OtherBuyerPassword123!",
    ]);
    $admin = User::create([
        "name" => "QA Account Admin",
        "email" => "qa-account-admin-" . uniqid() . "@example.test",
        "password" => "AdminPassword123!",
    ]);
    $admin->forceFill(["role" => "admin"])->save();
    $seller = User::create([
        "name" => "QA Delivery Seller",
        "email" => "qa-delivery-seller-" . uniqid() . "@example.test",
        "password" => "DeliverySeller123!",
    ]);
    $seller->forceFill(["role" => "seller"])->save();
    $sid = DB::table("sellers")->insertGetId([
        "user_id" => $seller->id,
        "shop_name" => "QA Delivery Shop",
        "slug" => "qa-delivery-" . uniqid(),
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
    $cat = DB::table("categories")->insertGetId([
        "name" => "QA Account Category " . uniqid(),
        "slug" => "qa-account-cat-" . uniqid(),
        "active" => 1,
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    $category = DB::table("categories")->where("id", $cat)->value("name");
    $base = [
        "name" => "QA Review Eligible Dress",
        "category" => $category,
        "category_id" => $cat,
        "price" => 500,
        "old" => 800,
        "color" => "Rose",
        "image" => "photo-1515372039744-b8f02a3ae446",
        "tag" => "QA",
        "sizes" => '["M"]',
        "stock" => 20,
        "rating" => 0,
        "slug" => "qa-review-dress-" . uniqid(),
        "description" => "QA customer review product description.",
        "active" => 1,
        "seller_status" => "approved",
        "created_at" => now(),
        "updated_at" => now(),
    ];
    $a = DB::table("products")->insertGetId($base);
    $b = DB::table("products")->insertGetId(
        array_merge($base, [
            "name" => "QA Partial Delivery Product",
            "slug" => "qa-partial-" . uniqid(),
            "seller_id" => $sid,
        ]),
    );
    $line = fn($id, $name, $sellerId = null) => [
        "id" => $id,
        "name" => $name,
        "size" => "M",
        "color" => "Rose",
        "quantity" => 1,
        "price" => 500,
        "category" => $category,
        "seller_id" => $sellerId,
        "seller_name" => $sellerId ? "QA Delivery Shop" : "Avelle",
    ];
    $makeOrder = function ($u, $items, $status) use ($line) {
        return DB::table("orders")->insertGetId([
            "user_id" => $u->id,
            "reference" => (string) \Illuminate\Support\Str::uuid(),
            "name" => $u->name,
            "email" => $u->email,
            "phone" => "9876543210",
            "address" => "QA fixture delivery address",
            "city" => "Delhi",
            "state" => "Delhi",
            "pincode" => "110001",
            "items" => json_encode($items),
            "subtotal" => count($items) * 500,
            "shipping" => 79,
            "discount" => 0,
            "total" => count($items) * 500 + 79,
            "status" => $status,
            "payment_method" => "cod",
            "delivered_at" => $status === "delivered" ? now() : null,
            "created_at" => now(),
            "updated_at" => now(),
        ]);
    };
    $oa = $makeOrder($buyer, [$line($a, "QA Review Eligible Dress")], "delivered");
    $ob = $makeOrder($other, [$line($b, "QA Private Product", $sid)], "delivered");
    $partial = $makeOrder(
        $buyer,
        [$line($a, "QA Review Eligible Dress"), $line($b, "QA Partial Delivery Product", $sid)],
        "placed",
    );
    SellerOrders::split(
        $partial,
        [$line($a, "QA Review Eligible Dress"), $line($b, "QA Partial Delivery Product", $sid)],
        0,
    );
    DB::table("seller_orders")
        ->where("order_id", $partial)
        ->where("seller_id", $sid)
        ->update(["status" => "delivered", "delivered_at" => now()]);
    check(ReviewEligibility::canReview($buyer->id, $a), "Delivered parent order enables product review");
    check(
        ReviewEligibility::canReview($buyer->id, $b),
        "Delivered seller shipment enables review before whole order delivery",
    );
    check(
        !ReviewEligibility::canReview($other->id, $a),
        "Another customer delivery cannot grant review eligibility",
    );
    $shopping = new ShoppingController();
    Auth::login($other);
    rejects(
        fn() => $shopping->review(
            req(["rating" => 5, "title" => "Not purchased", "body" => "This should not be accepted."]),
            $a,
        ),
        "Unverified review submission blocked",
    );
    Auth::login($buyer);
    $saved = $shopping->review(
        req([
            "rating" => 5,
            "title" => "QA visible review",
            "body" => "A helpful review after my delivery.",
            "account_reviews" => 1,
        ]),
        $a,
    );
    $review = DB::table("reviews")->where("user_id", $buyer->id)->where("product_id", $a)->first();
    check(
        $review->status === "pending" && str_ends_with($saved->getTargetUrl(), "/account/reviews"),
        "Review saves pending and returns to My Reviews",
    );
    $html = (new AccountController())->index(pageReq("/account/reviews"))->render();
    check(
        str_contains($html, "Pending approval") && str_contains($html, "QA visible review"),
        "Customer sees saved review and pending approval status",
    );
    $oldCreated = now()->subDays(7)->format("Y-m-d H:i:s");
    DB::table("reviews")
        ->where("id", $review->id)
        ->update(["created_at" => $oldCreated, "status" => "approved", "reply" => "Old store response"]);
    $shopping->review(
        req([
            "rating" => 4,
            "title" => "QA edited review",
            "body" => "My updated experience with this product.",
            "account_reviews" => 1,
        ]),
        $a,
    );
    $edited = DB::table("reviews")->find($review->id);
    check(
        $edited->created_at === $oldCreated && $edited->status === "pending" && $edited->reply === null,
        "Review edits preserve submission date and reset moderation / stale reply",
    );
    $pd = (new MarketplaceController())
        ->product(DB::table("products")->where("id", $a)->value("slug"))
        ->getData();
    check(
        $pd["reviewEligible"] && $pd["ownReview"]->id === $review->id,
        "Product page receives own review and delivered-purchase eligibility",
    );
    Auth::login($other);
    $pd = (new MarketplaceController())
        ->product(DB::table("products")->where("id", $a)->value("slug"))
        ->render();
    $pd = preg_replace('/\s+/u', ' ', $pd);
    check(
        str_contains($pd, "Reviews open after delivery.") &&
            !str_contains($pd, "Write your verified-purchase review"),
        "Ineligible signed-in customer receives a clear explanation",
    );
    Auth::login($buyer);
    $account = new AccountController();
    $data = $account->index(pageReq("/account/orders", ["status" => "in_progress"]))->getData();
    check(
        $data["orders"]->total() === 1 && $data["orders"]->items()[0]->id === $partial,
        "In-progress order filter is limited to current customer",
    );
    $data = $account
        ->index(pageReq("/account/orders", ["status" => "delivered", "q" => "QA Review Eligible Dress"]))
        ->getData();
    check(
        $data["orders"]->total() === 1 && $data["orders"]->items()[0]->id === $oa,
        "Product search and status filter combine within customer orders",
    );
    DB::table("return_requests")->insert([
        "order_id" => $oa,
        "user_id" => $buyer->id,
        "items" => json_encode([$line($a, "QA Review Eligible Dress")]),
        "reason" => "Size or fit",
        "notes" => "QA own return request",
        "status" => "requested",
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    DB::table("return_requests")->insert([
        "order_id" => $ob,
        "user_id" => $other->id,
        "items" => json_encode([$line($b, "QA Private Product", $sid)]),
        "reason" => "Other",
        "notes" => "QA_SECRET_OTHER_RETURN",
        "status" => "requested",
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    $returns = $account->index(pageReq("/account/returns"))->render();
    check(
        str_contains($returns, "QA own return request") && !str_contains($returns, "QA_SECRET_OTHER_RETURN"),
        "My Returns never exposes another customer request",
    );
    DB::table("carts")->insert([
        "user_id" => $buyer->id,
        "items" => json_encode([["id" => $a, "size" => "M", "quantity" => 1]]),
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    $empty = $shopping->saveCart(req(["items" => []]))->getData(true);
    check(
        $empty["items"] === [] && DB::table("carts")->where("user_id", $buyer->id)->value("items") === "[]",
        "Removing last cart item persists the empty cart",
    );
    rejects(fn() => $shopping->saveCart(req()), "Cart API still requires an explicit items field");
    $address = [
        "label" => "Home",
        "name" => "QA Address",
        "phone" => "9876543210",
        "address" => "QA fixture address for checkout",
        "city" => "Delhi",
        "state" => "Delhi",
        "pincode" => "110001",
    ];
    $shopping->address(req($address));
    $first = DB::table("addresses")->where("user_id", $buyer->id)->first();
    check((bool) $first->is_default, "First delivery address becomes default");
    $shopping->address(req($address + ["id" => $first->id, "is_default" => 0]));
    check(
        (bool) DB::table("addresses")->where("id", $first->id)->value("is_default"),
        "Editing sole default address keeps a usable default",
    );
    $shopping->address(req(array_merge($address, ["label" => "Office"])));
    $second = DB::table("addresses")->where("user_id", $buyer->id)->latest("id")->first();
    $shopping->deleteAddress($first->id);
    check(
        (bool) DB::table("addresses")->where("id", $second->id)->value("is_default"),
        "Deleting default address promotes another owned address",
    );
    Auth::login($admin);
    $av = (new AdminController())->index(pageReq("/admin", ["review_status" => "pending"]));
    $ad = $av->getData();
    check(
        $ad["reviewQueue"]->every(fn($r) => $r->status === "pending") && $ad["actionCounts"]["reviews"] >= 1,
        "Admin review filter and pending-task counts reflect database state",
    );
    $ah = $av->render();
    check(
        str_contains($ah, "Needs your attention") && str_contains($ah, "QA edited review"),
        "Admin attention dashboard links into populated moderation queue",
    );
    (new \App\Http\Controllers\CatalogAdminController())->review(
        req(["status" => "approved", "reply" => "Thanks for sharing your experience."]),
        $review->id,
    );
    Auth::login($buyer);
    $approved = $account->index(pageReq("/account/reviews"))->render();
    check(
        str_contains($approved, "Approved") && str_contains($approved, "Thanks for sharing your experience."),
        "Customer sees review approval and store response",
    );
    $summary = $account->index(pageReq("/account"))->getData();
    check(
        $summary["accountStats"]["orders"] === 2 && $summary["accountStats"]["in_progress"] === 1,
        "Account dashboard metrics use complete owned order counts",
    );
    echo "Customer/admin checks passed. Fixtures rolled back.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
