<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
class Shop
{
    public static function settings(): array
    {
        return array_merge(
            [
                "store_name" => "AVELLE",
                "shipping_fee" => 79,
                "free_shipping_minimum" => 1499,
                "return_days" => 7,
                "cod_enabled" => 1,
                "support_email" => "",
                "support_phone" => "",
                "business_address" => "",
            ],
            DB::table("store_settings")->pluck("value", "key")->all(),
        );
    }
    public static function mediaUrl(?string $path): string
    {
        if (!$path) {
            return asset("images/placeholder.svg");
        }
        return str_starts_with($path, "https://") ? $path : asset(ltrim($path, "/"));
    }
    public static function categories()
    {
        return DB::table("categories")
            ->orderBy("sort_order")
            ->orderBy("name")
            ->get()
            ->map(function ($c) {
                $c->images = DB::table("category_images")
                    ->where("category_id", $c->id)
                    ->orderBy("position")
                    ->orderBy("id")
                    ->get();
                $c->cover = self::mediaUrl($c->images->first()?->path);
                return $c;
            });
    }
    public static function activeCategoryIds(): array
    {
        $all = DB::table("categories")->get()->keyBy("id");
        return $all
            ->filter(function ($c) use ($all) {
                $seen = [];
                while ($c) {
                    if (!$c->active || isset($seen[$c->id])) {
                        return false;
                    }
                    $seen[$c->id] = true;
                    $c = $c->parent_id ? $all[$c->parent_id] ?? null : null;
                }
                return true;
            })
            ->keys()
            ->all();
    }
    public static function descendants(int $id): array
    {
        $rows = DB::table("categories")->get();
        $ids = [$id];
        do {
            $old = count($ids);
            foreach ($rows as $c) {
                if (in_array($c->parent_id, $ids) && !in_array($c->id, $ids)) {
                    $ids[] = $c->id;
                }
            }
        } while (count($ids) > $old);
        return $ids;
    }
    public static function productQuery()
    {
        return DB::table("products")
            ->where("products.active", true)
            ->where("products.seller_status", "approved")
            ->where(function ($q) {
                $q->whereNull("products.seller_id")->orWhereExists(function ($s) {
                    $s->selectRaw("1")
                        ->from("sellers")
                        ->join("users", "users.id", "=", "sellers.user_id")
                        ->whereColumn("sellers.id", "products.seller_id")
                        ->where("sellers.status", "approved")
                        ->where("users.is_active", true);
                });
            })
            ->where(
                fn($q) => $q->whereNull("category_id")->orWhereIn("category_id", self::activeCategoryIds()),
            );
    }
    public static function decorate($p)
    {
        $p->images = DB::table("product_images")
            ->where("product_id", $p->id)
            ->orderBy("position")
            ->orderBy("id")
            ->get()
            ->map(function ($i) {
                $i->url = self::mediaUrl($i->path);
                return $i;
            });
        $fallback = "images/" . $p->image . ".jpg";
        if (!file_exists(public_path($fallback))) {
            $fallback = "https://images.unsplash.com/" . $p->image . "?w=1000&fit=crop";
        }
        $p->cover = $p->images->first()?->url ?? self::mediaUrl($fallback);
        $p->second = $p->images->skip(1)->first()?->url;
        $p->variants = DB::table("product_variants")
            ->where("product_id", $p->id)
            ->where("active", true)
            ->get();
        $p->sizes = is_string($p->sizes) ? json_decode($p->sizes, true) : $p->sizes;
        $p->specifications = is_string($p->specifications ?? null)
            ? json_decode($p->specifications, true)
            : $p->specifications ?? [];
        $p->highlights = is_string($p->highlights ?? null)
            ? json_decode($p->highlights, true)
            : $p->highlights ?? [];
        $p->display_price = $p->variants->count()
            ? $p->variants->min(fn($v) => $v->price ?? $p->price)
            : $p->price;
        $r = DB::table("reviews")->where("product_id", $p->id)->where("status", "approved");
        $p->review_count = (clone $r)->count();
        $p->review_average = $p->review_count ? round($r->avg("rating"), 1) : null;
        $p->seller_name = $p->seller_id
            ? DB::table("sellers")->where("id", $p->seller_id)->value("shop_name")
            : "Avelle";
        $p->url = url("/products/" . ($p->slug ?: $p->id));
        return $p;
    }
    public static function one(string $slug)
    {
        $q = self::productQuery();
        ctype_digit($slug) ? $q->where("id", (int) $slug) : $q->where("slug", $slug);
        $p = $q->first();
        abort_unless($p, 404);
        return self::decorate($p);
    }
}
