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

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as Broker;
use App\Http\Controllers\RecoveryController;
use App\Mail\StoreMessage;
use App\Support\StoreMail;
Mail::fake();
DB::beginTransaction();
try {
    $u = User::create([
        "name" => "Email QA",
        "email" => "mail-qa-" . uniqid() . "@example.test",
        "password" => "OriginalPassword123!",
    ]);
    StoreMail::welcome($u);
    check(
        Mail::sent(
            StoreMessage::class,
            fn($m) => $m->hasTo($u->email) && $m->heading === "Welcome to Avelle",
        )->isNotEmpty(),
        "Expected notification recipient and content",
    );
    check(true, "Welcome email addresses the customer");
    $c = new RecoveryController();
    $c->send(req(["email" => $u->email]));
    $reset = Mail::sent(StoreMessage::class, fn($m) => $m->heading === "Reset your password")->first();
    check((bool) $reset && $reset->hasTo($u->email), "Password reset email generated for active customer");
    $token = basename(parse_url($reset->actionUrl, PHP_URL_PATH));
    $stored = DB::table("password_reset_tokens")->where("email", $u->email)->first();
    check(
        $stored->token !== $token && Hash::check($token, $stored->token),
        "Reset token is stored as a hash",
    );
    rejects(
        fn() => $c->reset(
            req([
                "email" => $u->email,
                "token" => $token,
                "password" => "short",
                "password_confirmation" => "short",
            ]),
        ),
        "Short reset password rejected",
    );
    $version = $u->auth_version;
    $c->reset(
        req([
            "email" => $u->email,
            "token" => $token,
            "password" => "NewPassword12345!",
            "password_confirmation" => "NewPassword12345!",
        ]),
    );
    $u->refresh();
    check(
        Hash::check("NewPassword12345!", $u->password) && $u->auth_version === $version + 1,
        "Password reset changes hash and revokes previous sessions",
    );
    check(
        !DB::table("password_reset_tokens")->where("email", $u->email)->exists(),
        "Successful reset consumes the token",
    );
    $c->reset(
        req([
            "email" => $u->email,
            "token" => $token,
            "password" => "ReusedPassword123!",
            "password_confirmation" => "ReusedPassword123!",
        ]),
    );
    check(
        Hash::check("NewPassword12345!", $u->fresh()->password),
        "Used reset link cannot change password again",
    );
    $token = Broker::createToken($u);
    DB::table("password_reset_tokens")
        ->where("email", $u->email)
        ->update(["created_at" => now()->subMinutes(61)]);
    $c->reset(
        req([
            "email" => $u->email,
            "token" => $token,
            "password" => "ExpiredPassword123!",
            "password_confirmation" => "ExpiredPassword123!",
        ]),
    );
    check(Hash::check("NewPassword12345!", $u->fresh()->password), "Expired reset link rejected");
    $u->forceFill(["is_active" => false])->save();
    $before = Mail::sent(StoreMessage::class)->count();
    $c->send(req(["email" => $u->email]));
    $c->send(req(["email" => "unknown-" . uniqid() . "@example.test"]));
    check(
        Mail::sent(StoreMessage::class)->count() === $before,
        "Disabled and unknown accounts do not receive reset emails",
    );
    $html = $reset->render();
    check(
        str_contains($html, "Reset password") && str_contains($html, "60 minutes"),
        "Email HTML template renders reset action and expiry",
    );
    check(
        $c->forgot()->render() !== "" &&
            str_contains($c->form(req(), str_repeat("x", 64))->render(), "Choose a new password"),
        "Recovery pages render",
    );
    $base = [
        "user_id" => $u->id,
        "reference" => (string) Illuminate\Support\Str::uuid(),
        "name" => $u->name,
        "email" => $u->email,
        "phone" => "9876543210",
        "address" => "QA fixture address",
        "city" => "Delhi",
        "pincode" => "110001",
        "items" => '[{"name":"QA item","size":"M","quantity":2,"price":500}]',
        "subtotal" => 1000,
        "shipping" => 79,
        "discount" => 0,
        "total" => 1079,
        "status" => "placed",
        "payment_method" => "cod",
        "created_at" => now(),
        "updated_at" => now(),
    ];
    DB::table("orders")->insert($base);
    config(["mail.admin_address" => "owner@example.test"]);
    StoreMail::order($base["reference"]);
    StoreMail::status($base["reference"], "shipped");
    check(
        Mail::sent(
            StoreMessage::class,
            fn($m) => $m->heading === "Order placed" &&
                $m->hasTo($u->email) &&
                in_array("Total: ₹1079 · Cash on delivery", $m->lines),
        )->isNotEmpty(),
        "Expected notification recipient and content",
    );
    check(
        Mail::sent(
            StoreMessage::class,
            fn($m) => $m->heading === "New store order" && $m->hasTo("owner@example.test"),
        )->isNotEmpty(),
        "Expected notification recipient and content",
    );
    check(
        Mail::sent(
            StoreMessage::class,
            fn($m) => $m->heading === "Order shipped" && $m->hasTo($u->email),
        )->isNotEmpty(),
        "Expected notification recipient and content",
    );
    check(true, "Order confirmation, owner alert and status emails use correct recipients");
    echo "Email checks passed. Fixtures rolled back; no external emails sent.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
