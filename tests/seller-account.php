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

use App\Http\Controllers\SellerAccountController;
\Illuminate\Support\Facades\Mail::fake();
DB::beginTransaction();
try {
    $c = new SellerAccountController();
    $u = User::create([
        "name" => "QA Account Seller",
        "email" => "qa-seller-account-" . uniqid() . "@example.test",
        "password" => "OriginalSeller123!",
    ]);
    Auth::login($u);
    denied(
        fn() => $c->update(req(["name" => "No", "email" => $u->email])),
        "Ordinary customer cannot use seller account settings",
    );
    $u->forceFill(["role" => "seller"])->save();
    DB::table("sellers")->insert([
        "user_id" => $u->id,
        "shop_name" => "QA Account Studio",
        "slug" => "qa-account-" . uniqid(),
        "phone" => "9876543210",
        "pickup_address" => "QA account fixture pickup address",
        "city" => "Delhi",
        "state" => "Delhi",
        "pincode" => "110001",
        "status" => "pending",
        "commission_rate" => 10,
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    $c->update(
        req(["name" => "Updated Seller Name", "email" => $u->email, "role" => "admin", "is_active" => false]),
    );
    check(
        $u->fresh()->name === "Updated Seller Name" &&
            $u->fresh()->role === "seller" &&
            $u->fresh()->is_active,
        "Seller can edit own name without changing role or active status",
    );
    $newEmail = "qa-seller-new-" . uniqid() . "@example.test";
    rejects(
        fn() => $c->update(req(["name" => "QA", "email" => $newEmail])),
        "Seller email change requires current password",
    );
    rejects(
        fn() => $c->update(req(["name" => "QA", "email" => $newEmail, "current_password" => "wrong"])),
        "Incorrect password blocks seller email change",
    );
    $other = User::create([
        "name" => "QA Other",
        "email" => "qa-other-" . uniqid() . "@example.test",
        "password" => "OtherPassword123!",
    ]);
    rejects(
        fn() => $c->update(
            req(["name" => "QA", "email" => $other->email, "current_password" => "OriginalSeller123!"]),
        ),
        "Seller cannot use another account email",
    );
    $version = $u->auth_version;
    $c->update(
        req([
            "name" => "Updated Seller Name",
            "email" => $newEmail,
            "current_password" => "OriginalSeller123!",
        ]),
    );
    check(
        $u->fresh()->email === $newEmail && $u->auth_version === $version + 1,
        "Seller email change rotates credential version",
    );
    check(
        app("session")->driver()->get("auth_version") === $u->auth_version,
        "Email change preserves current seller session",
    );
    rejects(
        fn() => $c->password(
            req([
                "current_password" => "wrong",
                "password" => "NewSellerPassword123!",
                "password_confirmation" => "NewSellerPassword123!",
            ]),
        ),
        "Password change rejects incorrect current password",
    );
    rejects(
        fn() => $c->password(
            req([
                "current_password" => "OriginalSeller123!",
                "password" => "short",
                "password_confirmation" => "short",
            ]),
        ),
        "Seller password minimum length enforced",
    );
    rejects(
        fn() => $c->password(
            req([
                "current_password" => "OriginalSeller123!",
                "password" => "NewSellerPassword123!",
                "password_confirmation" => "MismatchPassword123!",
            ]),
        ),
        "Password confirmation must match",
    );
    $version = $u->auth_version;
    $result = $c->password(
        req([
            "current_password" => "OriginalSeller123!",
            "password" => "NewSellerPassword123!",
            "password_confirmation" => "NewSellerPassword123!",
        ]),
    );
    check(
        Hash::check("NewSellerPassword123!", $u->fresh()->password) && $u->auth_version === $version + 1,
        "Seller password saved hashed and old sessions revoked",
    );
    check(
        app("session")->driver()->get("auth_version") === $u->auth_version &&
            str_ends_with($result->getTargetUrl(), "/seller#seller-account"),
        "Password save returns to seller security and keeps session active",
    );
    $html = (new \App\Http\Controllers\SellerController())->index()->render();
    check(
        str_contains($html, 'id="seller-account"') &&
            str_contains($html, "/seller/password") &&
            !str_contains($html, "/account/security"),
        "Seller security UI remains inside seller layout, including pending accounts",
    );
    echo "Seller account checks passed. Fixtures rolled back.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
