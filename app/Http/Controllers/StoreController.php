<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreController
{
    public function index()
    {
        return view("store", [
            "offers" => \App\Support\StoreOffers::visible(),
            "accountData" => \Illuminate\Support\Facades\Auth::user()?->only([
                "name",
                "email",
                "phone",
                "address",
                "city",
                "pincode",
            ]),
        ]);
    }
    public function offers()
    {
        return response()->json(\App\Support\StoreOffers::visible())->header("Cache-Control", "no-store");
    }
    public function products()
    {
        return response()->json(
            DB::table("products")
                ->get()
                ->map(function ($p) {
                    $p->sizes = json_decode($p->sizes);
                    return $p;
                }),
        );
    }
    private function cart(array $data, bool $lock = false): array
    {
        return \App\Support\Inventory::cart($data, $lock);
    }
    private function rules(): array
    {
        return [
            "items" => "required|array|min:1|max:30",
            "items.*.id" => "required|integer",
            "items.*.size" => "required|string|max:30",
            "items.*.variant_id" => "nullable|integer|min:1",
            "items.*.quantity" => "required|integer|min:1|max:10",
            "code" => "nullable|string|max:40",
            "use_wallet" => "nullable|boolean",
        ];
    }
    private function discount(
        ?string $code,
        array $items,
        int $subtotal,
        ?string $email = null,
        bool $lock = false,
        ?int $customerId = null,
    ): array {
        if (!$code) {
            return [0, null];
        }
        $referral = \App\Support\ReferralWallet::referral($code, \Illuminate\Support\Facades\Auth::user());
        if ($referral) {
            $settings = $referral['settings'];
            $discount = min($subtotal, (int) $settings['referral_friend_discount']);
            if ($subtotal - $discount < (int) $settings['referral_minimum']) {
                throw ValidationException::withMessages(['code' => 'This referral requires ₹' . $settings['referral_minimum'] . ' of merchandise after the friend discount.']);
            }
            return [$discount, (object) [
                'id' => null, 'code' => $referral['code'], 'kind' => 'referral',
                'owner_email' => $referral['owner']->email, 'scope' => 'store', 'seller_id' => null, 'targets' => '[]',
            ]];
        }
        $query = DB::table("promotions")->where("code", strtoupper(trim($code)));
        $p = ($lock ? $query->lockForUpdate() : $query)->first();
        if (
            !$p ||
            !$p->active ||
            $p->admin_locked ||
            !\App\Support\StoreOffers::sellerAvailable($p, $lock) ||
            ($p->expires_at && now()->greaterThanOrEqualTo($p->expires_at)) ||
            ($p->usage_limit !== null && $p->used >= $p->usage_limit)
        ) {
            throw ValidationException::withMessages(["code" => "This code is unavailable or expired."]);
        }
        if (!$p->seller_id && $subtotal < $p->minimum) {
            throw ValidationException::withMessages([
                "code" => "This code requires a minimum order of ₹" . $p->minimum . ".",
            ]);
        }
        if ($p->kind === "referral" && $email) {
            if (strtolower($email) === strtolower($p->owner_email ?? "")) {
                throw ValidationException::withMessages(["code" => "You cannot use your own referral code."]);
            }
            if (
                DB::table("orders")
                ->where(function ($q) use ($email, $customerId) {
                    $q->whereRaw("lower(email) = ?", [strtolower($email)]);
                    if ($customerId) {
                        $q->orWhere("user_id", $customerId);
                    }
                })
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    "code" => "Referral offers are for first orders only.",
                ]);
            }
        }
        $targets = json_decode($p->targets ?? "[]", true);
        $eligible = 0;
        foreach ($items as $i) {
            if (\App\Support\StoreOffers::matches($i, $p)) {
                $eligible += $i["price"] * $i["quantity"];
            }
        }
        if ($p->seller_id && $eligible < $p->minimum) {
            throw ValidationException::withMessages([
                "code" => "This coupon requires ₹" . $p->minimum . " of eligible products from this seller.",
            ]);
        }
        if (!$eligible) {
            throw ValidationException::withMessages([
                "code" => "This code does not apply to the products in your bag.",
            ]);
        }
        $amount = $p->type === "percent" ? (int) floor(($eligible * $p->value) / 100) : $p->value;
        if ($p->maximum !== null) {
            $amount = min($amount, $p->maximum);
        }
        return [min($eligible, $amount), $p];
    }
    public function quote(Request $request)
    {
        $data = $request->validate($this->rules());
        [$items, $subtotal] = $this->cart($data);
        [$discount, $p] = $this->discount($data["code"] ?? null, $items, $subtotal);
        $s = \App\Support\Shop::settings();
        $shipping = $subtotal >= (int) $s["free_shipping_minimum"] ? 0 : (int) $s["shipping_fee"];
        $walletUsed = 0;
        $customer = \Illuminate\Support\Facades\Auth::user();
        if (!empty($data['use_wallet']) && $customer?->hasVerifiedEmail() && \App\Support\ReferralWallet::settings()['wallet_enabled']) {
            $walletUsed = min(max(0, (int) \App\Support\ReferralWallet::account($customer->id)->balance), max(0, $subtotal - $discount));
        }
        return response()->json([
            'wallet_used' => $walletUsed,
            "items" => $items,
            "subtotal" => $subtotal,
            "shipping" => $shipping,
            "total" => $subtotal + $shipping - $discount - $walletUsed,
            "code" => $p?->code,
            "discount" => $discount,
            "note" => $p?->kind === "referral" ? "First-order eligibility is checked at checkout." : null,
        ]);
    }
    public function checkout(Request $request)
    {
        $r = $request;
        if (!\Illuminate\Support\Facades\Auth::check()) {
            $target = url('/checkout');
            $r->session()->put('url.intended', $target);
            return response()->json([
                'message' => 'Please sign in or create an account to place your order.',
                'login_url' => url('/account/login') . '?return_to=' . rawurlencode($target),
            ], 401);
        }
        if (!\Illuminate\Support\Facades\Auth::user()->hasVerifiedEmail()) {
            $r->session()->put('url.intended', url('/checkout'));
            return response()->json(['message' => 'Verify your email before placing an order.', 'login_url' => url('/email/verify')], 403);
        }
        $rules = array_merge($this->rules(), [
            "name" => "required|string|max:100",
            "email" => "required|email|max:200",
            "phone" => ["required", 'regex:/^[6-9][0-9]{9}$/'],
            "address" => "required|string|max:500",
            "city" => "required|string|max:100",
            "pincode" => ["required", 'regex:/^[1-9][0-9]{5}$/'],
            "state" => "nullable|string|max:100",
            "checkout_key" => "nullable|uuid",
            "saved_address_id" => "nullable|integer|min:1",
            "address_label" => "nullable|string|max:30",
        ]);
        $r->merge(["email" => \Illuminate\Support\Facades\Auth::user()->email]);
        $data = $r->validate($rules);
        if (!\App\Support\Shop::settings()["cod_enabled"]) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "payment" => "Cash on delivery is currently unavailable.",
            ]);
        }
        $result = DB::transaction(function () use ($data) {
            $user = \App\Models\User::where('id', \Illuminate\Support\Facades\Auth::id())->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $user->hasVerifiedEmail(), 403);
            if ($user && !empty($data["checkout_key"])) {
                $existing = DB::table("orders")
                    ->where("checkout_key", $data["checkout_key"])
                    ->where("user_id", $user->id)
                    ->first();
                if ($existing) {
                    return [
                        "reference" => $existing->reference,
                        "total" => $existing->total,
                        "message" => "Order already placed.",
                        "account_created" => false,
                    ];
                }
            }
            [$items, $subtotal, $requested] = $this->cart($data, true);
            \App\Support\AddressBook::save($user->id, [
                'id' => $data['saved_address_id'] ?? null,
                'label' => $data['address_label'] ?? 'Home',
                'name' => $data['name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'] ?? '',
                'pincode' => $data['pincode'],
            ]);
            [$discount, $promo] = $this->discount(
                $data["code"] ?? null,
                $items,
                $subtotal,
                $user->email,
                true,
                $user->id,
            );
            foreach ($requested as $line) {
                \App\Support\Inventory::take($line);
            }
            if ($promo?->id) {
                DB::table("promotions")->where("id", $promo->id)->increment("used");
            }
            $settings = \App\Support\Shop::settings();
            $shipping =
                $subtotal >= (int) $settings["free_shipping_minimum"] ? 0 : (int) $settings["shipping_fee"];
            $ref = (string) Str::uuid();
            $total = $subtotal + $shipping - $discount;
            $orderId = DB::table("orders")->insertGetId(
                collect($data)
                    ->only(["name", "email", "phone", "address", "city", "pincode", "state", "checkout_key"])
                    ->merge([
                        "user_id" => $user->id,
                        "reference" => $ref,
                        "items" => json_encode($items),
                        "subtotal" => $subtotal,
                        "shipping" => $shipping,
                        "discount" => $discount,
                        "code" => $promo?->code,
                        "total" => $total,
                        "status" => "placed",
                        "payment_method" => "cod",
                        "created_at" => now(),
                        "updated_at" => now(),
                    ])
                    ->all(),
            );
            $walletSpent = !empty($data['use_wallet'])
                ? \App\Support\ReferralWallet::spend($user->id, $orderId, $subtotal - $discount) : 0;
            if ($walletSpent) {
                $total -= $walletSpent;
                $items = \App\Support\ReferralWallet::withWalletShares($items, $discount, $promo, $walletSpent);
                DB::table('orders')->where('id', $orderId)->update([
                    'wallet_spent' => $walletSpent, 'total' => $total,
                    'items' => json_encode($items),
                    'payment_status' => $total === 0 ? 'paid' : 'unpaid',
                ]);
            }
            \App\Support\ReferralWallet::capture($orderId, $promo, $items, $subtotal, $discount);
            \App\Support\SellerOrders::split($orderId, $items, $discount, $promo);
            DB::table("order_events")->insert([
                "order_id" => $orderId,
                "status" => "placed",
                "note" => "Order placed",
                "created_at" => now(),
                "updated_at" => now(),
            ]);
            DB::table("carts")->where("user_id", $user->id)->delete();
            DB::afterCommit(function () use ($ref) {
                \App\Support\StoreMail::order($ref);
            });
            return [
                "reference" => $ref,
                "total" => $total,
                "message" => "Order placed. Pay on delivery.",
                "account_created" => false,
            ];
        });
        $request->session()->forget('referral_code');
        $result["csrf_token"] = csrf_token();
        $result["account"] = \Illuminate\Support\Facades\Auth::user()->only([
            "name",
            "email",
            "phone",
            "address",
            "city",
            "pincode",
        ]);
        return response()->json($result, 201);
    }
}
