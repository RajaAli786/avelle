<?php
namespace App\Support;
use Illuminate\Support\Str;
class Seo
{
    public static function clean(?string $value, int $limit = 160): string
    {
        return Str::limit(
            trim(
                preg_replace(
                    "/\s+/u",
                    " ",
                    strip_tags(str_replace(["[DEMO CONTENT]", "##"], "", $value ?? "")),
                ),
            ),
            $limit,
            "…",
        );
    }
    public static function privatePath(): bool
    {
        return request()->is(
            "admin",
            "admin/*",
            "seller",
            "seller/*",
            "account",
            "account/*",
            "cart",
            "checkout",
            "wishlist",
            "orders",
            "orders/*",
            "receipt",
            "receipt/*",
            "track-order",
            "forgot-password",
            "reset-password",
            "reset-password/*",
            "api/*",
        );
    }
    public static function meta(array $vars, string $fallbackTitle, string $fallbackDescription): array
    {
        $record = $vars["product"] ?? ($vars["category"] ?? ($vars["page"] ?? null));
        $filtered =
            count(
                array_diff(array_keys(request()->query()), [
                    "page",
                    "utm_source",
                    "utm_medium",
                    "utm_campaign",
                    "utm_term",
                    "utm_content",
                    "gclid",
                    "fbclid",
                ]),
            ) > 0;
        $demo = isset($vars["page"]) && str_starts_with($vars["page"]->body, "[DEMO CONTENT]");
        $staticKey = match (request()->path()) {
            "/" => "home",
            "shop" => "shop",
            "categories" => "categories",
            "offers" => "offers",
            default => null,
        };
        $settings = $vars["settings"] ?? [];
        $title =
            trim($record->seo_title ?? ($staticKey ? $settings["seo_" . $staticKey . "_title"] ?? "" : "")) ?:
            $fallbackTitle;
        $staticDescription = match ($staticKey) {
            "home"
                => "Discover fashion, beauty and everyday favourites for women and girls. Explore the Avelle collection and find a little more you.",
            "shop"
                => "Browse women’s and girls’ clothing, beauty, footwear and accessories. Compare available sizes and find your everyday favourites at Avelle.",
            "categories"
                => "Explore Avelle categories: ethnic and western clothing, beauty, jewellery, footwear and accessories for women and girls.",
            "offers"
                => "Explore active Avelle offers and coupon codes. Check eligible products, minimum spend and expiry before applying a code at checkout.",
            default => $fallbackDescription,
        };
        $description =
            trim(
                $record->seo_description ??
                    ($staticKey ? $settings["seo_" . $staticKey . "_description"] ?? "" : ""),
            ) ?:
            self::clean(trim($record->description ?? ($record->body ?? "")) ?: $staticDescription);
        $canonical = isset($vars["product"]) ? $vars["product"]->url : url()->current();
        if (!isset($vars["product"]) && request()->integer("page") > 1) {
            $canonical .= "?page=" . request()->integer("page");
        }
        $noindex = !config("seo.indexable") || self::privatePath() || $filtered || $demo;
        $meta = [
            "title" => $title,
            "description" => $description,
            "keywords" => trim(
                $record->seo_keywords ??
                    ($staticKey ? $settings["seo_" . $staticKey . "_keywords"] ?? "" : ""),
            ),
            "canonical" => $canonical,
            "robots" => $noindex ? "noindex, follow" : "index, follow, max-image-preview:large",
            "image" => $vars["product"]->cover ?? null,
            "type" => isset($vars["product"]) ? "product" : "website",
            "schema" => [],
        ];
        if (isset($vars["product"])) {
            $p = $vars["product"];
            $prices = $p->variants->map(fn($v) => (int) ($v->price ?? $p->price));
            $offer = [
                "@type" => "Offer",
                "priceCurrency" => "INR",
                "price" => (int) $p->display_price,
                "availability" => "https://schema.org/" . ($p->stock > 0 ? "InStock" : "OutOfStock"),
                "url" => $p->url,
            ];
            if ($p->variants->count() > 1) {
                $offer = [
                    "@type" => "AggregateOffer",
                    "priceCurrency" => "INR",
                    "lowPrice" => $prices->min(),
                    "highPrice" => $prices->max(),
                    "offerCount" => $prices->count(),
                    "availability" => "https://schema.org/" . ($p->stock > 0 ? "InStock" : "OutOfStock"),
                    "url" => $p->url,
                ];
            }
            $schema = [
                "@context" => "https://schema.org",
                "@type" => "Product",
                "name" => $p->name,
                "description" => self::clean($p->description, 5000),
                "image" => [$p->cover],
                "sku" => $p->sku,
                "url" => $p->url,
                "offers" => $offer,
            ];
            if ($p->brand) {
                $schema["brand"] = ["@type" => "Brand", "name" => $p->brand];
            }
            if ($p->review_count > 0) {
                $schema["aggregateRating"] = [
                    "@type" => "AggregateRating",
                    "ratingValue" => $p->review_average,
                    "reviewCount" => $p->review_count,
                ];
            }
            $meta["schema"][] = $schema;
        }
        if ($record) {
            $crumbs = [["name" => "Home", "item" => url("/")]];
            if (isset($vars["product"])) {
                $crumbs[] = ["name" => "All products", "item" => url("/shop")];
            }
            if (isset($vars["category"])) {
                $crumbs[] = ["name" => "Categories", "item" => url("/categories")];
            }
            $crumbs[] = ["name" => $record->name ?? $record->title, "item" => $canonical];
            $meta["schema"][] = [
                "@context" => "https://schema.org",
                "@type" => "BreadcrumbList",
                "itemListElement" => array_map(
                    fn($c, $n) => ["@type" => "ListItem", "position" => $n + 1] + $c,
                    $crumbs,
                    array_keys($crumbs),
                ),
            ];
        }
        return $meta;
    }
}
