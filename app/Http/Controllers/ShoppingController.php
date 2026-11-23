<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShoppingController
{
    private function guard()
    {
        abort_unless(Auth::check(), 401);
    }
    public function cart()
    {
        $this->guard();
        return response()->json([
            "items" => json_decode(
                DB::table("carts")->where("user_id", Auth::id())->value("items") ?? "[]",
                true,
            ),
        ]);
    }
    public function saveCart(Request $r)
    {
        $this->guard();
        $d = $r->validate([
            "items" => "present|array|max:30",
            "items.*.id" => "required|integer|min:1",
            "items.*.size" => "required|string|max:30",
            "items.*.variant_id" => "nullable|integer|min:1",
            "items.*.quantity" => "required|integer|min:1|max:10",
        ]);
        $clean = [];
        foreach ($d["items"] as $i) {
            $key = $i["id"] . "-" . ($i["variant_id"] ?? 0) . "-" . $i["size"];
            if (isset($clean[$key])) {
                $clean[$key]["quantity"] = min(10, $clean[$key]["quantity"] + $i["quantity"]);
            } else {
                $clean[$key] = $i;
            }
        }
        DB::table("carts")->updateOrInsert(
            ["user_id" => Auth::id()],
            ["items" => json_encode(array_values($clean)), "updated_at" => now(), "created_at" => now()],
        );
        return response()->json(["items" => array_values($clean)]);
    }
    public function wishlist()
    {
        $this->guard();
        return response()->json(DB::table("wishlists")->where("user_id", Auth::id())->pluck("product_id"));
    }
    public function wish(Request $r)
    {
        $this->guard();
        $d = $r->validate([
            "product_id" => "required|integer|exists:products,id",
            "saved" => "required|boolean",
        ]);
        if ($r->boolean("saved")) {
            DB::table("wishlists")->updateOrInsert(
                ["user_id" => Auth::id(), "product_id" => $d["product_id"]],
                ["created_at" => now(), "updated_at" => now()],
            );
        } else {
            DB::table("wishlists")
                ->where("user_id", Auth::id())
                ->where("product_id", $d["product_id"])
                ->delete();
        }
        return $this->wishlist();
    }
    public function address(Request $r)
    {
        $this->guard();
        $d = $r->validate([
            "id" => "nullable|integer",
            "label" => "required|string|max:30",
            "name" => "required|string|max:100",
            "phone" => ["required", 'regex:/^[6-9][0-9]{9}$/'],
            "address" => "required|string|max:500",
            "city" => "required|string|max:100",
            "state" => "required|string|max:100",
            "pincode" => ["required", 'regex:/^[1-9][0-9]{5}$/'],
            "is_default" => "nullable|boolean",
        ]);
        $address = \App\Support\AddressBook::save(Auth::id(), $d);
        if ($r->expectsJson()) {
            return response()->json(['message' => 'Address saved.', 'address' => $address]);
        }
        return back()->with("success", "Address saved.");
    }
    public function deleteAddress(int $id)
    {
        $this->guard();
        DB::transaction(function () use ($id) {
            $a = DB::table("addresses")
                ->where("id", $id)
                ->where("user_id", Auth::id())
                ->lockForUpdate()
                ->first();
            abort_unless($a, 404);
            DB::table("addresses")->where("id", $id)->delete();
            if ($a->is_default) {
                $next = DB::table("addresses")->where("user_id", Auth::id())->first();
                if ($next) {
                    DB::table("addresses")
                        ->where("id", $next->id)
                        ->update(["is_default" => true]);
                }
            }
        });
        return back()->with("success", "Address removed.");
    }
    public function review(Request $r, int $id)
    {
        $this->guard();
        $d = $r->validate([
            "rating" => "required|integer|between:1,5",
            "title" => "required|string|max:120",
            "body" => "required|string|min:10|max:2000",
        ]);
        abort_unless(DB::table("products")->where("id", $id)->exists(), 404);
        if (!\App\Support\ReviewEligibility::canReview(Auth::id(), $id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "review" => "You can review this product after your purchase has been delivered.",
            ]);
        }
        DB::transaction(function () use ($id, $d) {
            $existing = DB::table("reviews")
                ->where("user_id", Auth::id())
                ->where("product_id", $id)
                ->lockForUpdate()
                ->first();
            $values = $d + ["status" => "pending", "reply" => null, "updated_at" => now()];
            if ($existing) {
                DB::table("reviews")->where("id", $existing->id)->update($values);
            } else {
                DB::table("reviews")->insert(
                    $values + ["user_id" => Auth::id(), "product_id" => $id, "created_at" => now()],
                );
            }
        });
        $slug = DB::table("products")->where("id", $id)->value("slug") ?: $id;
        return redirect(
            $r->boolean("account_reviews") ? url("/account/reviews") : url("/products/" . $slug) . "#reviews",
        )->with(
            "success",
            "Your review is saved and waiting for admin approval. You can follow its status in My Reviews.",
        );
    }
}
