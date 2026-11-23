<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class Inventory
{
    public static function cart(array $data, bool $lock = false): array
    {
        $items = [];
        $subtotal = 0;
        $products = [];
        $variants = [];
        $reservations = [];
        foreach ($data["items"] as $item) {
            $seller = null;
            $q = DB::table("products")->where("id", $item["id"]);
            $p = ($lock ? $q->lockForUpdate() : $q)->first();
            if ($p && $p->seller_id) {
                $seller = DB::table("sellers")->where("id", $p->seller_id);
                $seller = ($lock ? $seller->lockForUpdate() : $seller)->first();
                $owner = $seller ? DB::table("users")->find($seller->user_id) : null;
                if (!$seller || $seller->status !== "approved" || !$owner?->is_active) {
                    throw ValidationException::withMessages([
                        "items" => "This seller is currently unavailable.",
                    ]);
                }
            }
            if (
                !$p ||
                !$p->active ||
                $p->seller_status !== "approved" ||
                ($p->category_id && !in_array($p->category_id, Shop::activeCategoryIds()))
            ) {
                throw ValidationException::withMessages(["items" => "A product is no longer available."]);
            }
            $qty = (int) $item["quantity"];
            $variant = null;
            $vid = $item["variant_id"] ?? null;
            if ($vid) {
                $v = DB::table("product_variants")
                    ->where("id", $vid)
                    ->where("product_id", $p->id)
                    ->where("active", true);
                $variant = ($lock ? $v->lockForUpdate() : $v)->first();
                if (!$variant || $variant->size !== $item["size"]) {
                    throw ValidationException::withMessages(["items" => "Choose a valid size and colour."]);
                }
            } elseif (
                DB::table("product_variants")->where("product_id", $p->id)->where("active", true)->exists()
            ) {
                throw ValidationException::withMessages([
                    "items" => "Select a size and colour option for " . $p->name . ".",
                ]);
            } elseif (!in_array($item["size"], json_decode($p->sizes, true))) {
                throw ValidationException::withMessages(["items" => "A product size is unavailable."]);
            }
            $products[$p->id] = ($products[$p->id] ?? 0) + $qty;
            if ($products[$p->id] > $p->stock) {
                throw ValidationException::withMessages(["items" => $p->name . " has insufficient stock."]);
            }
            if ($variant) {
                $variants[$variant->id] = ($variants[$variant->id] ?? 0) + $qty;
                if ($variants[$variant->id] > $variant->stock) {
                    throw ValidationException::withMessages([
                        "items" => "The selected size/colour has insufficient stock.",
                    ]);
                }
            }
            $price = $variant?->price ?? $p->price;
            $subtotal += $price * $qty;
            $items[] = [
                "seller_id" => $p->seller_id,
                "seller_name" => isset($seller) ? $seller->shop_name : "Avelle",
                "id" => $p->id,
                "variant_id" => $variant?->id,
                "name" => $p->name,
                "category" => $p->category,
                "size" => $variant?->size ?? $item["size"],
                "color" => $variant?->color ?? $p->color,
                "sku" => $variant?->sku ?? $p->sku,
                "quantity" => $qty,
                "price" => (int) $price,
            ];
            $key = $p->id . "-" . ($variant?->id ?? 0);
            if (isset($reservations[$key])) {
                $reservations[$key]["quantity"] += $qty;
            } else {
                $reservations[$key] = ["id" => $p->id, "variant_id" => $variant?->id, "quantity" => $qty];
            }
        }
        return [$items, $subtotal, array_values($reservations)];
    }
    public static function take(array $line): void
    {
        DB::table("products")->where("id", $line["id"])->decrement("stock", $line["quantity"]);
        if ($line["variant_id"] ?? null) {
            DB::table("product_variants")
                ->where("id", $line["variant_id"])
                ->decrement("stock", $line["quantity"]);
        }
    }
    public static function restore(array $line): void
    {
        if ($line["variant_id"] ?? null) {
            $v = DB::table("product_variants")->where("id", $line["variant_id"])->lockForUpdate()->first();
            if ($v) {
                DB::table("product_variants")->where("id", $v->id)->increment("stock", $line["quantity"]);
                if ($v->active) {
                    DB::table("products")->where("id", $line["id"])->increment("stock", $line["quantity"]);
                }
            }
        } else {
            DB::table("products")->where("id", $line["id"])->increment("stock", $line["quantity"]);
        }
    }
}
