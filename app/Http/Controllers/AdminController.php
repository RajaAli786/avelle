<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController
{
    private function guard(Request $r): void
    {
        abort_unless(
            \Illuminate\Support\Facades\Auth::user()?->role === "admin" &&
                \Illuminate\Support\Facades\Auth::user()->is_active,
            403,
        );
    }

    private function commerceData(Request $r): array
    {
        return [
            "actionCounts" => [
                "reviews" => DB::table("reviews")->where("status", "pending")->count(),
                "returns" => DB::table("return_requests")
                    ->whereIn("status", ["requested", "approved", "received"])
                    ->count(),
                "sellers" => DB::table("sellers")->where("status", "pending")->count(),
                "products" => DB::table("products")
                    ->whereNotNull("seller_id")
                    ->where("seller_status", "pending")
                    ->count(),
                "support" => DB::table("inquiries")->where("status", "open")->count(),
            ],
            "reviewFilter" => $r->input("review_status", "all"),
            "sellers" => DB::table("sellers")
                ->join("users", "users.id", "=", "sellers.user_id")
                ->select(
                    "sellers.*",
                    "users.name as owner_name",
                    "users.email as owner_email",
                    "users.is_active as user_active",
                )
                ->latest("sellers.id")
                ->get(),
            "sellerProducts" => DB::table("products")
                ->join("sellers", "sellers.id", "=", "products.seller_id")
                ->select("products.*", "sellers.shop_name")
                ->latest("products.id")
                ->get(),
            "sellerShipments" => DB::table("seller_orders")
                ->join("orders", "orders.id", "=", "seller_orders.order_id")
                ->leftJoin("sellers", "sellers.id", "=", "seller_orders.seller_id")
                ->select("seller_orders.*", "orders.reference", "orders.payment_status", "sellers.shop_name")
                ->latest("seller_orders.id")
                ->limit(200)
                ->get(),
            "catalogCategories" => \App\Support\Shop::categories(),
            'catalogBrands' => DB::table('brands')->select('brands.*')->selectSub(
                DB::table('products')->selectRaw('count(*)')->whereColumn('brand_id', 'brands.id'), 'product_count'
            )->orderBy('sort_order')->orderBy('name')->get(),
            "productImages" => DB::table("product_images")
                ->orderBy("position")
                ->orderBy("id")
                ->get()
                ->groupBy("product_id"),
            "categoryImages" => DB::table("category_images")
                ->orderBy("position")
                ->orderBy("id")
                ->get()
                ->groupBy("category_id"),
            "variants" => DB::table("product_variants")->orderBy("id")->get()->groupBy("product_id"),
            "reviewQueue" => DB::table("reviews")
                ->join("users", "users.id", "=", "reviews.user_id")
                ->join("products", "products.id", "=", "reviews.product_id")
                ->select("reviews.*", "users.name as customer", "products.name as product")
                ->when(
                    $r->filled("review_status") && $r->input("review_status") !== "all",
                    fn($q) => $q->where("reviews.status", $r->input("review_status")),
                )
                ->latest("reviews.id")
                ->limit(100)
                ->get(),
            "returnQueue" => DB::table("return_requests")
                ->join("orders", "orders.id", "=", "return_requests.order_id")
                ->join("users", "users.id", "=", "return_requests.user_id")
                ->select(
                    "return_requests.*",
                    "orders.reference",
                    "orders.total as order_total",
                    "users.name as customer",
                )
                ->latest("return_requests.id")
                ->limit(100)
                ->get(),
            "contentPages" => DB::table("pages")->get(),
            "storeSettings" => \App\Support\Shop::settings(),
            "inquiries" => DB::table("inquiries")->latest()->limit(100)->get(),
        ];
    }

    public function index(Request $r)
    {
        if (\Illuminate\Support\Facades\Auth::user()?->role !== "admin") {
            return view("admin-login");
        }
        $r->validate(["review_status" => "nullable|in:all,pending,approved,rejected"]);
        $data = [
            'referralSettings' => \App\Support\ReferralWallet::settings(),
            'referralRewards' => DB::table('referral_rewards')->join('users', 'users.id', '=', 'referral_rewards.referrer_id')
                ->select('referral_rewards.*', 'users.name as referrer_name', 'users.email as referrer_email')->latest('referral_rewards.id')->limit(100)->get(),
            'walletTransactions' => DB::table('wallet_entries')->join('users', 'users.id', '=', 'wallet_entries.user_id')
                ->select('wallet_entries.*', 'users.name', 'users.email')->latest('wallet_entries.id')->limit(100)->get(),
            'emailDeliveries' => DB::table('store_mail_deliveries')->latest()->limit(100)->get(),
            "products" => DB::table("products")->orderBy("id")->get(),
            "promotions" => DB::table("promotions")->latest()->get(),
            "orders" => DB::table("orders")->latest()->limit(100)->get(),
            "revenue" => DB::table("orders")->where("status", "!=", "cancelled")->sum("total"),
            "count" => DB::table("orders")->count(),
            "customerCount" => \App\Models\User::where("role", "customer")->count(),
            "customers" => \App\Models\User::latest()->limit(100)->get(),
            "userCount" => \App\Models\User::count(),
            "disabledCount" => \App\Models\User::where("is_active", false)->count(),
            "activeOffers" => \App\Support\StoreOffers::activeQuery()->count(),
        ];
        return view("admin", array_merge($data, $this->commerceData($r)));
    }
    public function login(Request $r)
    {
        $r->merge(["email" => strtolower(trim($r->email ?? ""))]);
        $d = $r->validate(["email" => "required|email", "password" => "required|string"]);
        if (!\Illuminate\Support\Facades\Auth::attempt($d + ["role" => "admin", "is_active" => true])) {
            return back()
                ->withErrors(["email" => "Admin email or password is incorrect."])
                ->withInput($r->only("email"));
        }
        $r->session()->regenerate();
        $r->session()->put("auth_version", \Illuminate\Support\Facades\Auth::user()->auth_version);
        return redirect(url("/admin"));
    }
    public function logout(Request $r)
    {
        \Illuminate\Support\Facades\Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect(url("/admin"));
    }
    public function product(Request $r)
    {
        return app(CatalogAdminController::class)->product($r);
    }
    public function promotion(Request $r)
    {
        $this->guard($r);
        $r->merge(["code" => strtoupper(trim($r->code ?? ""))]);
        $d = $r->validate([
            "id" => "nullable|integer|exists:promotions,id",
            "code" => ["required", 'regex:/^[A-Z0-9_-]{3,40}$/', Rule::unique("promotions")->ignore($r->id)],
            "kind" => "required|in:coupon,referral",
            "owner_email" => "nullable|required_if:kind,referral|email|max:200",
            "type" => "required|in:percent,fixed",
            "value" => "required|integer|min:1|max:1000000",
            "minimum" => "required|integer|min:0|max:1000000",
            "maximum" => "nullable|integer|min:1|max:1000000",
            "scope" => "required|in:store,category,product",
            "targets" => "nullable|required_unless:scope,store|string|max:1000",
            "usage_limit" => "nullable|integer|min:1|max:1000000",
            "expires_at" => "nullable|date",
            "show_banner" => "nullable|boolean",
            "banner_text" => "nullable|string|max:180",
            "banner_mode" => "required|in:both,marquee,pulse,static",
        ]);
        if ($d["type"] === "percent" && $d["value"] > 100) {
            return back()->withErrors(["value" => "Percentage must be between 1 and 100."]);
        }
        $id = $d["id"] ?? null;
        unset($d["id"]);
        $d["show_banner"] = $r->boolean("show_banner");
        $d["targets"] = json_encode(
            array_values(array_filter(array_map("trim", explode(",", $d["targets"] ?? "")))),
        );
        $d["updated_at"] = now();
        if ($id) {
            DB::table("promotions")->where("id", $id)->update($d);
        } else {
            DB::table("promotions")->insert($d + ["created_at" => now(), "active" => true]);
        }
        return redirect(url("/admin") . "#offers")->with("success", "Offer saved.");
    }
    public function toggle(Request $r, int $id)
    {
        $this->guard($r);
        $p = DB::table("promotions")->find($id);
        abort_unless($p, 404);
        DB::table("promotions")
            ->where("id", $id)
            ->update([
                "active" => !$p->active,
                "admin_locked" => $p->seller_id ? (bool) $p->active : false,
                "updated_at" => now(),
            ]);
        return redirect(url("/admin") . "#offers")->with("success", "Offer updated.");
    }
    public function order(Request $r, int $id)
    {
        $this->guard($r);
        $d = $r->validate([
            "status" => "required|in:placed,confirmed,shipped,delivered,cancelled",
            "carrier" => "nullable|string|max:100",
            "tracking_number" => "nullable|string|max:150",
            "tracking_url" => "nullable|url:http,https|max:2048",
            "payment_status" => "nullable|in:unpaid,paid",
        ]);
        DB::transaction(function () use ($id, $d) {
            $o = DB::table("orders")->where("id", $id)->lockForUpdate()->first();
            abort_unless($o, 404);
            if ($d["status"] === "cancelled") {
                \App\Support\OrderFlow::cancel($id, "Cancelled by administrator");
                return;
            }
            if (
                $d["status"] !== $o->status &&
                DB::table("seller_orders")->where("order_id", $id)->whereNotNull("seller_id")->exists()
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "status" =>
                        "Update marketplace shipments in Seller orders. Use this form for COD collection or full-order cancellation.",
                ]);
            }
            if ($o->status === "cancelled") {
                abort(422, "Cancelled orders cannot be changed.");
            }
            $rank = ["placed" => 0, "confirmed" => 1, "shipped" => 2, "delivered" => 3];
            if ($rank[$d["status"]] < $rank[$o->status]) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "status" => "Order status cannot move backwards.",
                ]);
            }
            $values = $d + ["updated_at" => now()];
            if ($d["status"] === "delivered" && !$o->delivered_at) {
                $values["delivered_at"] = now();
            }
            if ($o->payment_method !== "cod") {
                unset($values["payment_status"]);
            }
            DB::table("orders")->where("id", $id)->update($values);
            \App\Support\ReferralWallet::sync($id);
            if (!DB::table("seller_orders")->where("order_id", $id)->whereNotNull("seller_id")->exists()) {
                DB::table("seller_orders")
                    ->where("order_id", $id)
                    ->update(collect($values)->except("payment_status")->all());
            }
            if ($d["status"] !== $o->status) {
                DB::afterCommit(fn() => \App\Support\StoreMail::status($o->reference, $d["status"]));
            }
            if ($d["status"] !== $o->status) {
                DB::table("order_events")->insert([
                    "order_id" => $id,
                    "status" => $d["status"],
                    "note" => "Status updated by store administrator",
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }
        });
        return redirect(url("/admin") . "#orders")->with(
            "success",
            "Order status, payment record and tracking saved.",
        );
    }
    public function user(Request $r, int $id)
    {
        $this->guard($r);
        $target = \App\Models\User::findOrFail($id);
        $r->merge(["email" => strtolower(trim($r->email ?? ""))]);
        $rules = [
            "name" => "required|string|max:100",
            "email" => ["required", "email", "max:200", Rule::unique("users")->ignore($id)],
            "phone" => ["nullable", 'regex:/^[6-9][0-9]{9}$/'],
            "is_active" => "required|boolean",
            "password" => [
                "nullable",
                "confirmed",
                \Illuminate\Validation\Rules\Password::min($target->role === "admin" ? 12 : 10),
            ],
        ];
        if ($r->filled("password") && $id === \Illuminate\Support\Facades\Auth::id()) {
            $rules["current_password"] = "required|current_password:web";
        }
        $d = $r->validate($rules);
        DB::transaction(function () use ($r, $id, $d) {
            $target = \App\Models\User::where("id", $id)->lockForUpdate()->firstOrFail();
            $enabled = $r->boolean("is_active");
            $self = $id === \Illuminate\Support\Facades\Auth::id();
            if (!$enabled && $self) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "is_active" => "You cannot disable your own admin account.",
                ]);
            }
            if (!$enabled && $target->role === "admin" && $target->is_active) {
                $active = \App\Models\User::where("role", "admin")
                    ->where("is_active", true)
                    ->lockForUpdate()
                    ->get();
                if ($active->count() <= 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "is_active" => "At least one active administrator is required.",
                    ]);
                }
            }
            $oldEmail = $target->email;
            $revoke = $target->is_active !== $enabled || $r->filled("password") || $oldEmail !== $d["email"];
            $target->fill(
                collect($d)
                    ->only(["name", "email", "phone"])
                    ->all(),
            );
            $target->is_active = $enabled;
            if ($r->filled("password")) {
                $target->password = $d["password"];
            }
            if ($revoke) {
                $target->auth_version++;
                $target->remember_token = \Illuminate\Support\Str::random(60);
            }
            if ($oldEmail !== $target->email) {
                $target->email_verified_at = null;
            }
            $target->save();
            if ($oldEmail !== $target->email) {
                DB::afterCommit(fn () => $target->sendEmailVerificationNotification());
            }
            if ($oldEmail !== $target->email) {
                DB::table("promotions")
                    ->where("kind", "referral")
                    ->where("owner_email", $oldEmail)
                    ->update(["owner_email" => $target->email, "updated_at" => now()]);
            }
            if ($self && $revoke) {
                $r->session()->regenerate();
                $r->session()->put("auth_version", $target->auth_version);
            }
        });
        return redirect(
            url("/admin") . "#" . ($id === \Illuminate\Support\Facades\Auth::id() ? "settings" : "customers"),
        )->with("success", "Account updated. Password resets and status changes revoke previous sessions.");
    }
}
