<?php
namespace App\Support;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class ReviewEligibility
{
    public static function items(int $userId): Collection
    {
        $items = collect();
        $parents = DB::table("orders")
            ->where("user_id", $userId)
            ->where("status", "delivered")
            ->pluck("items");
        $shipments = DB::table("seller_orders")
            ->join("orders", "orders.id", "=", "seller_orders.order_id")
            ->where("orders.user_id", $userId)
            ->where("orders.status", "!=", "cancelled")
            ->where("seller_orders.status", "delivered")
            ->pluck("seller_orders.items");
        foreach ($parents->concat($shipments) as $json) {
            foreach (json_decode($json, true) ?? [] as $line) {
                if (isset($line["id"])) {
                    $items->put((int) $line["id"], $line);
                }
            };
        }
        return $items;
    }
    public static function canReview(int $userId, int $productId): bool
    {
        return self::items($userId)->has($productId);
    }
}
