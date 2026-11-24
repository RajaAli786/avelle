<?php

namespace App\Http\Controllers;

use App\Support\Shop;
use App\Support\StoreOffers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MarketplaceController
{
    public function home()
    {
        return view("market.home", [
            "featured" => Shop::productQuery()
                ->orderByDesc("featured")
                ->orderByDesc("id")
                ->limit(12)
                ->get()
                ->map(fn($p) => Shop::decorate($p)),
        ]);
    }
    public function categories()
    {
        return view("market.categories", [
            "categories" => Shop::categories()->whereIn("id", Shop::activeCategoryIds()),
        ]);
    }
    public function category(Request $r, string $slug)
    {
        $c = Shop::categories()->firstWhere("slug", $slug);
        abort_unless($c && in_array($c->id, Shop::activeCategoryIds()), 404);
        return $this->listing($r, $c);
    }
    public function listing(Request $r, $category = null)
    {
        $r->validate([
            "q" => "nullable|string|max:150",
            "min" => "nullable|integer|min:0",
            "max" => "nullable|integer|min:0",
            "size" => "nullable|string|max:30",
            "color" => "nullable|string|max:80",
            "brand" => "nullable|string|max:100",
            "audience" => "nullable|in:women,girls,both",
            "sort" => "nullable|in:recommended,newest,price_asc,price_desc,rating,discount",
        ]);
        $q = Shop::productQuery();
        if ($category) {
            $q->whereIn("category_id", Shop::descendants($category->id));
        }
        if ($r->filled("q")) {
            $word = $r->string("q")->toString();
            $q->where(
                fn($s) => $s
                    ->where("name", "like", "%" . $word . "%")
                    ->orWhere("category", "like", "%" . $word . "%")
                    ->orWhere("brand", "like", "%" . $word . "%"),
            );
        }
        if ($r->filled("audience")) {
            $q->whereIn("audience", [$r->audience, "both"]);
        }
        $price =
            "COALESCE((SELECT MIN(COALESCE(pv.price, products.price)) FROM product_variants pv WHERE pv.product_id=products.id AND pv.active=1),products.price)";
        if ($r->filled("min")) {
            $q->whereRaw($price . " >= ?", [(int) $r->min]);
        }
        if ($r->filled("max")) {
            $q->whereRaw($price . " <= ?", [(int) $r->max]);
        }
        if ($r->filled("brand")) {
            $q->where("brand", $r->brand);
        }
        if ($r->filled("size")) {
            $size = $r->size;
            $q->where(
                fn($s) => $s
                    ->whereExists(
                        fn($v) => $v
                            ->selectRaw("1")
                            ->from("product_variants")
                            ->whereColumn("product_id", "products.id")
                            ->where("active", true)
                            ->where("size", $size),
                    )
                    ->orWhereJsonContains("sizes", $size),
            );
        }
        if ($r->filled("color")) {
            $color = $r->color;
            $q->where(
                fn($s) => $s
                    ->where("color", "like", "%" . $color . "%")
                    ->orWhereExists(
                        fn($v) => $v
                            ->selectRaw("1")
                            ->from("product_variants")
                            ->whereColumn("product_id", "products.id")
                            ->where("active", true)
                            ->where("color", $color),
                    ),
            );
        }
        match ($r->input("sort", "recommended")) {
            "price_asc" => $q->orderByRaw($price . " ASC"),
            "price_desc" => $q->orderByRaw($price . " DESC"),
            "newest" => $q->orderByDesc("id"),
            "rating" => $q->orderByRaw(
                "(SELECT AVG(rating) FROM reviews WHERE product_id=products.id AND status='approved') DESC",
            ),
            "discount" => $q->orderByRaw("(old-price)/GREATEST(old,1) DESC"),
            default => $q->orderByDesc("featured")->orderByDesc("id"),
        };
        $products = $q->paginate(24)->withQueryString();
        $products->getCollection()->transform(fn($p) => Shop::decorate($p));
        return view("market.shop", [
            "products" => $products,
            "category" => $category,
            "brands" => Shop::productQuery()->whereIn('brand_id', DB::table('brands')->where('active', true)->select('id'))
                ->whereNotNull('brand')->orderBy('brand')->distinct()->pluck('brand'),
            "sizes" => DB::table("products")
                ->where("active", true)
                ->pluck("sizes")
                ->flatMap(fn($s) => json_decode($s, true) ?? [])
                ->unique()
                ->values(),
            "colors" => DB::table("product_variants")->where("active", true)->distinct()->pluck("color"),
        ]);
    }
    public function product(string $slug)
    {
        $p = Shop::one($slug);
        $reviews = DB::table("reviews")
            ->join("users", "users.id", "=", "reviews.user_id")
            ->where("product_id", $p->id)
            ->where("reviews.status", "approved")
            ->select("reviews.*", "users.name as customer_name")
            ->latest("reviews.id")
            ->paginate(10);
        return view("market.product", [
            "reviewEligible" => Auth::check()
                ? \App\Support\ReviewEligibility::canReview(Auth::id(), $p->id)
                : false,
            "ownReview" => Auth::check()
                ? DB::table("reviews")->where("product_id", $p->id)->where("user_id", Auth::id())->first()
                : null,
            "product" => $p,
            "reviews" => $reviews,
            "related" => Shop::productQuery()
                ->where("id", "!=", $p->id)
                ->where("category_id", $p->category_id)
                ->limit(4)
                ->get()
                ->map(fn($v) => Shop::decorate($v)),
        ]);
    }
    public function productApi(Request $r)
    {
        $r->validate(["ids" => "nullable|string|max:1500"]);
        $q = Shop::productQuery();
        if ($r->filled("ids")) {
            $ids = array_slice(array_filter(explode(",", $r->ids), "ctype_digit"), 0, 100);
            $q->whereIn("id", $ids);
        }
        return response()->json($q->limit(500)->get()->map(fn($p) => Shop::decorate($p)));
    }
    public function cart()
    {
        return view("market.cart");
    }
    public function checkout()
    {
        if (!Auth::check()) {
            $target = request()->is('checkout') ? request()->fullUrl() : url('/checkout');
            return \App\Support\AuthReturn::login(request(), $target);
        }
        if (!Auth::user()->hasVerifiedEmail()) {
            request()->session()->put('url.intended', url('/checkout'));
            return redirect(url('/email/verify'));
        }
        return view("market.checkout", [
            'walletBalance' => max(0, (int) \App\Support\ReferralWallet::account(Auth::id())->balance),
            'walletEnabled' => (bool) \App\Support\ReferralWallet::settings()['wallet_enabled'],
            "addresses" => Auth::check()
                ? DB::table("addresses")->where("user_id", Auth::id())->orderByDesc("is_default")->get()
                : collect(),
        ]);
    }
    public function wishlist()
    {
        return view("market.wishlist");
    }
    public function offers()
    {
        return view("market.offers", ["offers" => StoreOffers::visible()]);
    }
    public function page(string $slug)
    {
        $p = DB::table("pages")->where("slug", $slug)->where("active", true)->first();
        abort_unless($p, 404);
        return view("market.page", ["page" => $p]);
    }
    public function contact(Request $r)
    {
        $d = $r->validate([
            "name" => "required|string|max:100",
            "email" => "required|email|max:200",
            "subject" => "required|string|max:150",
            "message" => "required|string|min:10|max:5000",
            "website" => "nullable|max:0",
        ]);
        unset($d["website"]);
        $id = DB::table("inquiries")->insertGetId($d + ["created_at" => now(), "updated_at" => now()]);
        return back()->with("success", "Message saved. Your reference is AVL-SUPPORT-" . $id . ".");
    }
}
