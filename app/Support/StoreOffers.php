<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
class StoreOffers
{
    public static function matches(array $item, $offer): bool
    {
        if ($offer->seller_id !== null && (int) ($item["seller_id"] ?? 0) !== (int) $offer->seller_id) {
            return false;
        }
        $targets = json_decode($offer->targets ?? "[]", true);
        return $offer->scope === "store" ||
            ($offer->scope === "category" && in_array($item["category"], $targets)) ||
            ($offer->scope === "product" && in_array((string) $item["id"], $targets));
    }
    public static function sellerAvailable($offer, bool $lock = false): bool
    {
        if (!$offer->seller_id) {
            return true;
        }
        $q = DB::table("sellers")->where("id", $offer->seller_id);
        $s = ($lock ? $q->lockForUpdate() : $q)->first();
        return $s &&
            $s->status === "approved" &&
            DB::table("users")->where("id", $s->user_id)->where("is_active", true)->exists();
    }
    public static function activeQuery()
    {
        return DB::table("promotions")
            ->where("active", true)
            ->where("admin_locked", false)
            ->where(fn($q) => $q->whereNull("expires_at")->orWhere("expires_at", ">", now()))
            ->where(fn($q) => $q->whereNull("usage_limit")->orWhereColumn("used", "<", "usage_limit"))
            ->where(function ($q) {
                $q->whereNull("seller_id")->orWhereExists(function ($s) {
                    $s->selectRaw("1")
                        ->from("sellers")
                        ->join("users", "users.id", "=", "sellers.user_id")
                        ->whereColumn("sellers.id", "promotions.seller_id")
                        ->where("sellers.status", "approved")
                        ->where("users.is_active", true);
                });
            });
    }
    public static function visible(): array
    {
        return self::activeQuery()
            ->where("kind", "coupon")
            ->where("show_banner", true)
            ->orderByDesc("id")
            ->get()
            ->filter(function ($p) {
                if (!$p->seller_id) {
                    return true;
                }
                $q = Shop::productQuery()->where("products.seller_id", $p->seller_id);
                $targets = json_decode($p->targets ?? "[]", true);
                if ($p->scope === "product") {
                    $q->whereIn("products.id", $targets);
                } elseif ($p->scope === "category") {
                    $q->whereIn("products.category", $targets);
                }
                return $q->exists();
            })
            ->map(function ($p) {
                $targets = json_decode($p->targets ?? "[]", true);
                $shop = $p->seller_id
                    ? DB::table("sellers")->where("id", $p->seller_id)->value("shop_name")
                    : null;
                $scope =
                    $p->scope === "store"
                        ? ($shop
                            ? "All products from " . $shop
                            : "Storewide")
                        : ($p->scope === "category"
                            ? implode(", ", $targets)
                            : DB::table("products")
                                ->whereIn("id", $targets)
                                ->when($p->seller_id, fn($q) => $q->where("seller_id", $p->seller_id))
                                ->pluck("name")
                                ->implode(", "));
                if ($shop && $p->scope !== "store") {
                    $scope = $shop . " · " . $scope;
                }
                $saving = $p->type === "percent" ? $p->value . "% OFF" : "₹" . $p->value . " OFF";
                return [
                    "id" => $p->id,
                    "seller_id" => $p->seller_id,
                    "seller_name" => $shop,
                    "code" => $p->code,
                    "title" =>
                        $p->banner_text ?:
                        $saving .
                            " on " .
                            ($shop ? $shop : ($p->scope === "store" ? "your favourites" : $scope)),
                    "saving" => $saving,
                    "scope" => $p->scope,
                    "scope_label" => $scope,
                    "targets" => $targets,
                    "minimum" => (int) $p->minimum,
                    "maximum" => $p->maximum === null ? null : (int) $p->maximum,
                    "expires_at" => $p->expires_at ? Carbon::parse($p->expires_at)->toIso8601String() : null,
                    "mode" => $p->banner_mode,
                ];
            })
            ->values()
            ->all();
    }
}
