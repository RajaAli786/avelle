<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SellerAccess;
use App\Support\SellerOrders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SellerController
{
    private function rules(): array
    {
        return [
            "shop_name" => "required|string|max:120",
            "phone" => ["required", 'regex:/^[6-9][0-9]{9}$/'],
            "pickup_address" => "required|string|min:10|max:1000",
            "city" => "required|string|max:100",
            "state" => "required|string|max:100",
            "pincode" => ["required", 'regex:/^[1-9][0-9]{5}$/'],
            "description" => "nullable|string|max:3000",
        ];
    }
    public function applyForm()
    {
        if (Auth::check() && DB::table("sellers")->where("user_id", Auth::id())->exists()) {
            return redirect(url("/seller"));
        }
        abort_if(Auth::user()?->role === "admin", 403);
        return view("market.seller-apply");
    }
    public function apply(Request $r)
    {
        abort_if(Auth::user()?->role === "admin", 403);
        $r->merge(["email" => strtolower(trim($r->email ?? ""))]);
        $rules = $this->rules();
        if (!Auth::check()) {
            $rules += [
                "name" => "required|string|max:100",
                "email" => "required|email|max:200|unique:users,email",
                "password" => ["required", "confirmed", Password::min(12)],
            ];
        }
        $d = $r->validate($rules);
        $user = DB::transaction(function () use ($d) {
            $u = Auth::user();
            if (!$u) {
                $u = User::create(
                    collect($d)
                        ->only(["name", "email", "password"])
                        ->all(),
                );
            }
            $u = User::where("id", $u->id)->lockForUpdate()->first();
            abort_unless($u->is_active, 403);
            if (DB::table("sellers")->where("user_id", $u->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "seller" => "Your seller application already exists.",
                ]);
            }
            $u->role = "seller";
            $u->save();
            DB::table("sellers")->insert(
                collect($d)
                    ->only([
                        "shop_name",
                        "phone",
                        "pickup_address",
                        "city",
                        "state",
                        "pincode",
                        "description",
                    ])
                    ->all() + [
                        "user_id" => $u->id,
                        "slug" => Str::slug($d["shop_name"]) . "-" . strtolower(Str::random(8)),
                        "status" => "pending",
                        "commission_rate" => 10,
                        "created_at" => now(),
                        "updated_at" => now(),
                    ],
            );
            return $u;
        });
        Auth::login($user);
        $r->session()->regenerate();
        $r->session()->put("auth_version", $user->auth_version);
        return redirect(url("/seller"))->with(
            "success",
            "Seller application received. Admin approval is required before adding products.",
        );
    }
    public function loginForm()
    {
        return view("market.seller-login");
    }
    public function login(Request $r)
    {
        $r->merge(["email" => strtolower(trim($r->email ?? ""))]);
        $d = $r->validate(["email" => "required|email", "password" => "required|string"]);
        $u = User::where("email", $d["email"])->where("is_active", true)->first();
        if (
            !$u ||
            !DB::table("sellers")->where("user_id", $u->id)->exists() ||
            !\Illuminate\Support\Facades\Hash::check($d["password"], $u->password)
        ) {
            return back()
                ->withErrors(["email" => "Seller email or password is incorrect."])
                ->withInput($r->only("email"));
        }
        Auth::login($u);
        $r->session()->regenerate();
        $r->session()->put("auth_version", $u->auth_version);
        return redirect(url("/seller"));
    }
    public function index()
    {
        if (!Auth::check()) {
            return redirect(url("/seller/login"));
        }
        $s = SellerAccess::profile();
        $products = DB::table("products")->where("seller_id", $s->id)->latest()->get();
        $ids = $products->pluck("id");
        $shipments = DB::table("seller_orders")
            ->join("orders", "orders.id", "=", "seller_orders.order_id")
            ->where("seller_orders.seller_id", $s->id)
            ->select(
                "seller_orders.*",
                "orders.reference",
                "orders.name as customer_name",
                "orders.phone as customer_phone",
                "orders.address",
                "orders.city",
                "orders.state",
                "orders.pincode",
                "orders.payment_status",
            )
            ->latest("seller_orders.id")
            ->paginate(20);
        return view("market.seller-dashboard", [
            "seller" => $s,
            "products" => $products,
            "shipments" => $shipments,
            "catalogCategories" => \App\Support\Shop::categories(),
            "productImages" => DB::table("product_images")
                ->whereIn("product_id", $ids)
                ->orderBy("position")
                ->get()
                ->groupBy("product_id"),
            "variants" => DB::table("product_variants")
                ->whereIn("product_id", $ids)
                ->get()
                ->groupBy("product_id"),
            "summary" => DB::table("seller_orders")
                ->where("seller_id", $s->id)
                ->where("status", "!=", "cancelled")
                ->selectRaw(
                    "COUNT(*) as orders, COALESCE(SUM(seller_amount),0) as estimated, COALESCE(SUM(CASE WHEN settled_at IS NOT NULL THEN seller_amount ELSE 0 END),0) as recorded",
                )
                ->first(),
            "sellerOffers" => DB::table("promotions")->where("seller_id", $s->id)->latest()->get(),
            "sellerMode" => true,
        ]);
    }
    public function profile(Request $r)
    {
        $s = SellerAccess::profile();
        $d = $r->validate($this->rules());
        DB::transaction(function () use ($s, $d) {
            DB::table("sellers")->where("id", $s->id)->lockForUpdate()->first();
            DB::table("sellers")
                ->where("id", $s->id)
                ->update($d + ["status" => "pending", "updated_at" => now()]);
        });
        return back()->with(
            "success",
            "Business details saved and submitted for approval again. Products stay hidden until approval.",
        );
    }
    public function product(Request $r)
    {
        return (new CatalogAdminController())->asSeller()->product($r);
    }
    public function variants(Request $r, int $id)
    {
        return (new CatalogAdminController())->asSeller()->variants($r, $id);
    }
    public function mediaOrder(Request $r, int $id)
    {
        return (new CatalogAdminController())->asSeller()->mediaOrder($r, "product", $id);
    }
    public function mediaDelete(int $id, int $image)
    {
        return (new CatalogAdminController())->asSeller()->removeMedia("product", $id, $image);
    }
    public function shipment(Request $r, int $id)
    {
        $s = SellerAccess::profile(true);
        $d = $r->validate([
            "status" => "required|in:placed,confirmed,shipped,delivered",
            "carrier" => "nullable|string|max:100",
            "tracking_number" => "nullable|string|max:150",
            "tracking_url" => "nullable|url:http,https|max:2048",
        ]);
        SellerOrders::change($id, $d, (int) $s->id);
        return redirect(url("/seller") . "#seller-orders")->with("success", "Shipment updated.");
    }
}
