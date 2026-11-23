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
DB::beginTransaction();
try {
    $base = [
        "name" => "QA Test Find",
        "category" => "QA_A",
        "price" => 699,
        "old" => 1299,
        "color" => "QA neutral",
        "image" => "photo-1515372039744-b8f02a3ae446",
        "tag" => "TEST",
        "sizes" => '["M","S"]',
        "stock" => 20,
        "rating" => 4.5,
        "created_at" => now(),
        "updated_at" => now(),
    ];
    $a = DB::table("products")->insertGetId($base);
    $b = DB::table("products")->insertGetId(
        array_merge($base, ["name" => "QA Second Find", "category" => "QA_B", "price" => 899]),
    );
    $cheap = DB::table("products")->insertGetId(array_merge($base, ["price" => 299]));
    $items = [["id" => $a, "size" => "M", "quantity" => 1], ["id" => $b, "size" => "S", "quantity" => 1]];
    $c = new StoreController();
    $account = new AccountController();
    $admin = new AdminController();
    $offer = [
        "kind" => "coupon",
        "type" => "fixed",
        "value" => 150,
        "minimum" => 0,
        "maximum" => null,
        "scope" => "product",
        "targets" => json_encode([(string) $a]),
        "usage_limit" => 1,
        "used" => 0,
        "active" => true,
        "expires_at" => null,
        "show_banner" => true,
        "banner_mode" => "both",
    ];
    DB::table("promotions")->insert($offer + ["code" => "QA_PRODUCT"]);
    $quote = fn($code, $bag = null) => $c
        ->quote(req(["items" => $bag ?? $items, "code" => $code]))
        ->getData(true);
    check($quote("QA_PRODUCT")["discount"] === 150, "Product-specific discount applies");
    rejects(
        fn() => $quote("QA_PRODUCT", [["id" => $b, "size" => "S", "quantity" => 1]]),
        "Product coupon rejects unmatched products",
    );
    DB::table("promotions")->insert(
        array_merge($offer, [
            "code" => "QA_CATEGORY",
            "type" => "percent",
            "value" => 20,
            "maximum" => 100,
            "scope" => "category",
            "targets" => '["QA_B"]',
            "usage_limit" => null,
        ]),
    );
    check($quote("QA_CATEGORY")["discount"] === 100, "Category percentage discount respects cap");
    DB::table("promotions")->insert(
        array_merge($offer, ["code" => "QA_EXPIRED", "expires_at" => "2020-01-01 00:00:00"]),
    );
    rejects(fn() => $quote("QA_EXPIRED"), "Expired coupon rejected");
    DB::table("promotions")->insert(array_merge($offer, ["code" => "QA_PAUSED", "active" => false]));
    rejects(fn() => $quote("QA_PAUSED"), "Paused coupon rejected");
    DB::table("promotions")->insert(
        array_merge($offer, ["code" => "QA_MINIMUM", "minimum" => 499, "scope" => "store"]),
    );
    rejects(
        fn() => $quote("QA_MINIMUM", [["id" => $cheap, "size" => "M", "quantity" => 1]]),
        "Minimum subtotal enforced",
    );
    DB::table("promotions")->insert(array_merge($offer, ["code" => "QA_HIDDEN", "show_banner" => false]));
    $visible = collect(StoreOffers::visible());
    check($visible->contains("code", "QA_PRODUCT"), "Active public coupon is displayed");
    check(
        !$visible->contains("code", "QA_EXPIRED") &&
            !$visible->contains("code", "QA_PAUSED") &&
            !$visible->contains("code", "QA_HIDDEN"),
        "Expired, paused and hidden offers are not advertised",
    );
    check(
        !array_key_exists("owner_email", $visible->first()),
        "Public offer response excludes owner personal data",
    );
    $payload = [
        "name" => "QA Customer",
        "email" => "qa-checkout@example.test",
        "phone" => "9876543210",
        "address" => "QA Address",
        "city" => "QA City",
        "pincode" => "110001",
        "items" => $items,
        "code" => "QA_PRODUCT",
        "password" => "QaCustomer!12345",
        "password_confirmation" => "QaCustomer!12345",
    ];
    Auth::logout();
    $before = DB::table("products")->where("id", $a)->value("stock");
    $order = $c->checkout(req($payload))->getData(true);
    $owner = Auth::user();
    check($order["total"] === 1448, "Checkout recalculates prices and totals on server");
    check(
        $order["account_created"] && $owner?->email === $payload["email"],
        "First checkout creates a customer profile and logs in",
    );
    check(
        $owner->role === "customer" &&
            Hash::check($payload["password"], $owner->password) &&
            $owner->password !== $payload["password"],
        "Customer password is hashed and role is restricted",
    );
    $stored = DB::table("orders")->where("reference", $order["reference"])->first();
    check($stored->user_id === $owner->id, "Order belongs to the authenticated customer");
    check(
        DB::table("products")->where("id", $a)->value("stock") === $before - 1,
        "Checkout decrements stock",
    );
    check(
        DB::table("promotions")->where("code", "QA_PRODUCT")->value("used") === 1,
        "Offer usage is counted atomically",
    );
    rejects(fn() => $quote("QA_PRODUCT"), "Exhausted coupon rejected");
    check(
        !collect(StoreOffers::visible())->contains("code", "QA_PRODUCT"),
        "Exhausted offer disappears from storefront",
    );
    $profile = $account->index(req())->getData();
    check($profile["orders"]->total() === 1, "Customer profile lists its own order");
    $second = User::create([
        "name" => "QA Second",
        "email" => "qa-second@example.test",
        "password" => "QaSecond!12345",
    ]);
    Auth::login($second);
    check(
        $account->index(req())->getData()["orders"]->total() === 0,
        "Customers cannot see another customer’s orders",
    );
    denied(fn() => $admin->product(req([])), "Customer cannot access admin product mutation");
    Auth::logout();
    rejects(fn() => $c->checkout(req($payload)), "Guest cannot claim an existing customer email");
    $registration = [
        "name" => "QA Registered",
        "email" => "qa-register@example.test",
        "password" => "QaRegister!12345",
        "password_confirmation" => "QaRegister!12345",
    ];
    $account->register(req($registration));
    $registered = Auth::user();
    check($registered?->email === $registration["email"], "Registration creates and signs in a customer");
    Auth::logout();
    $account->login(req(["email" => $registration["email"], "password" => $registration["password"]]));
    check(Auth::id() === $registered->id, "Customer can sign in with database credentials");
    $account->update(
        req([
            "name" => "QA Updated",
            "phone" => "9876543210",
            "address" => "QA Saved Address",
            "city" => "QA Town",
            "pincode" => "110001",
            "role" => "admin",
        ]),
    );
    check(
        $registered->fresh()->name === "QA Updated" && $registered->fresh()->role === "customer",
        "Profile update persists fields without privilege escalation",
    );
    $account->password(
        req([
            "current_password" => $registration["password"],
            "password" => "QaNewPassword!12345",
            "password_confirmation" => "QaNewPassword!12345",
        ]),
    );
    check(
        Hash::check("QaNewPassword!12345", $registered->fresh()->password),
        "Customer can change password with the current password",
    );
    DB::table("promotions")->insert(
        array_merge($offer, [
            "code" => "QA_REFER",
            "kind" => "referral",
            "owner_email" => $owner->email,
            "scope" => "store",
            "usage_limit" => null,
        ]),
    );
    check(!collect(StoreOffers::visible())->contains("code", "QA_REFER"), "Referral codes stay private");
    Auth::login($owner);
    rejects(
        fn() => $c->checkout(req(array_merge($payload, ["code" => "QA_REFER"]))),
        "Self-referral rejected",
    );
    DB::table("promotions")
        ->where("code", "QA_REFER")
        ->update(["owner_email" => "someone@example.test"]);
    rejects(
        fn() => $c->checkout(req(array_merge($payload, ["code" => "QA_REFER"]))),
        "Referral restricted to first order",
    );
    rejects(
        fn() => $quote("QA_MINIMUM", [["id" => $a, "size" => "INVALID", "quantity" => 1]]),
        "Invalid product size rejected",
    );
    DB::table("products")
        ->where("id", $a)
        ->update(["stock" => 1]);
    rejects(
        fn() => $quote("QA_MINIMUM", [
            ["id" => $a, "size" => "M", "quantity" => 1],
            ["id" => $a, "size" => "S", "quantity" => 1],
        ]),
        "Duplicate product lines cannot bypass stock",
    );
    $operator = User::create([
        "name" => "QA Admin",
        "email" => "qa-admin@example.test",
        "password" => "QaAdmin!12345",
    ]);
    $operator->role = "admin";
    $operator->save();
    Auth::logout();
    $admin->login(req(["email" => $operator->email, "password" => "QaAdmin!12345"]));
    check(
        Auth::id() === $operator->id && Auth::user()->role === "admin",
        "Database admin login enforces admin role",
    );
    $html = $admin->index(req())->render();
    check(
        str_contains($html, "app-sidebar") &&
            str_contains($html, "Good offers, your rules.") &&
            str_contains($html, "Customer accounts"),
        "AdminLTE dashboard renders authenticated management screens",
    );
    $admin->order(req(["status" => "cancelled"]), $stored->id);
    check(
        DB::table("orders")->where("id", $stored->id)->value("status") === "cancelled",
        "Admin can cancel an order",
    );
    check(
        DB::table("promotions")->where("code", "QA_PRODUCT")->value("used") === 0,
        "Cancellation releases offer usage",
    );
    check(
        DB::table("products")->where("id", $a)->value("stock") === 2,
        "Cancellation returns reserved stock",
    );
    Auth::logout();
    $admin->login(req(["email" => $registered->email, "password" => "QaNewPassword!12345"]));
    check(!Auth::check(), "Customer credentials cannot authenticate as admin");

    Auth::logout();
    $admin->login(req(["email" => $operator->email, "password" => "QaAdmin!12345"]));
    $oldVersion = $registered->fresh()->auth_version;
    $manage = [
        "name" => "QA Managed User",
        "email" => "qa-managed@example.test",
        "phone" => "9876543210",
        "is_active" => 0,
        "password" => "QaManaged!12345",
        "password_confirmation" => "QaManaged!12345",
    ];
    $admin->user(req($manage), $registered->id);
    $managed = $registered->fresh();
    check(
        $managed->name === "QA Managed User" &&
            $managed->email === "qa-managed@example.test" &&
            !$managed->is_active,
        "Admin can edit customer name/email and disable account",
    );
    check(
        Hash::check("QaManaged!12345", $managed->password) && $managed->auth_version > $oldVersion,
        "Admin password reset hashes password and revokes sessions",
    );
    Auth::logout();
    $account->login(req(["email" => $managed->email, "password" => "QaManaged!12345"]));
    check(!Auth::check(), "Disabled customer cannot sign in");
    Auth::login($managed);
    $disabledRequest = req();
    $disabledRequest->headers->set("Accept", "application/json");
    $disabledRequest->session()->put("auth_version", $managed->auth_version);
    $gate = new App\Http\Middleware\EnsureAccountIsActive();
    $response = $gate->handle($disabledRequest, fn() => response("should not run"));
    check(
        $response->getStatusCode() === 401 && !Auth::check(),
        "Disabling a user terminates an existing session",
    );
    $admin->login(req(["email" => $operator->email, "password" => "QaAdmin!12345"]));
    $admin->user(
        req(array_merge($manage, ["is_active" => 1, "password" => null, "password_confirmation" => null])),
        $registered->id,
    );
    $managed = $registered->fresh();
    check($managed->is_active, "Admin can re-enable a customer account");
    Auth::logout();
    $account->login(req(["email" => $managed->email, "password" => "QaManaged!12345"]));
    check(Auth::id() === $managed->id, "Re-enabled customer can sign in with reset password");
    $stale = req();
    $stale->headers->set("Accept", "application/json");
    $stale->session()->put("auth_version", $oldVersion);
    $response = $gate->handle($stale, fn() => response("should not run"));
    check(
        $response->getStatusCode() === 401 && !Auth::check(),
        "Credential version mismatch revokes old sessions",
    );
    $admin->login(req(["email" => $operator->email, "password" => "QaAdmin!12345"]));
    $self = [
        "name" => "QA Owner Updated",
        "email" => $operator->email,
        "phone" => null,
        "is_active" => 1,
        "password" => "QaOwnerNew!12345",
        "password_confirmation" => "QaOwnerNew!12345",
    ];
    rejects(
        fn() => $admin->user(req($self), $operator->id),
        "Own admin password change requires current password",
    );
    $admin->user(req($self + ["current_password" => "QaAdmin!12345"]), $operator->id);
    $operator = $operator->fresh();
    check(
        $operator->name === "QA Owner Updated" && Hash::check("QaOwnerNew!12345", $operator->password),
        "Admin can update own name and password",
    );
    check(
        (int) app("session")->driver()->get("auth_version") === $operator->auth_version,
        "Own admin credential update preserves current session",
    );
    Auth::setUser($operator);
    rejects(
        fn() => $admin->user(
            req(array_merge($self, ["password" => null, "password_confirmation" => null, "is_active" => 0])),
            $operator->id,
        ),
        "Admin cannot disable own account",
    );
    Auth::logout();
    $account->login(req(["email" => $managed->email, "password" => "QaManaged!12345"]));
    denied(fn() => $admin->user(req($manage), $operator->id), "Customer cannot mutate user account controls");

    Auth::logout();
    DB::rollBack();
    echo "All checks passed. Test fixtures rolled back." . PHP_EOL;
} catch (Throwable $e) {
    Auth::logout();
    DB::rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
