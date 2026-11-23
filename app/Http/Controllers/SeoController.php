<?php

namespace App\Http\Controllers;

use App\Support\Shop;
use Illuminate\Support\Facades\DB;

class SeoController
{
    private function query(string $kind)
    {
        return match ($kind) {
            "products" => Shop::productQuery(),
            "categories" => DB::table("categories")->whereIn("id", Shop::activeCategoryIds()),
            "pages" => DB::table("pages")
                ->where("active", true)
                ->where("body", "not like", "[DEMO CONTENT]%"),
            default => abort(404),
        };
    }
    public function robots()
    {
        if (!config("seo.indexable")) {
            return response("User-agent: *\nDisallow: /\n", 200, [
                "Content-Type" => "text/plain; charset=UTF-8",
            ]);
        }
        $base = parse_url(url("/"), PHP_URL_PATH) ?: "";
        $base = rtrim($base, "/");
        $text = "User-agent: *\n";
        foreach (
            [
                "admin",
                "seller",
                "account",
                "cart",
                "checkout",
                "wishlist",
                "api",
                "track-order",
                "forgot-password",
                "reset-password",
            ]
            as $path
        ) {
            $text .= "Disallow: $base/$path\n";
        }
        return response($text . "Sitemap: " . url("/sitemap.xml") . "\n", 200, [
            "Content-Type" => "text/plain; charset=UTF-8",
        ]);
    }
    private function xml(string $root, array $entries)
    {
        return response(
            '<?xml version="1.0" encoding="UTF-8"?><' .
                $root .
                ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
                implode("", $entries) .
                "</" .
                $root .
                ">",
            200,
            ["Content-Type" => "application/xml; charset=UTF-8"],
        );
    }
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, "UTF-8");
    }
    public function index()
    {
        if (!config("seo.indexable")) {
            return $this->xml("sitemapindex", []);
        }
        $entries = ["<sitemap><loc>" . $this->esc(url("/sitemaps/static/1.xml")) . "</loc></sitemap>"];
        foreach (["products", "categories", "pages"] as $kind) {
            $count = $this->query($kind)->count();
            for ($page = 1; $page <= ceil($count / 1000); $page++) {
                $entries[] =
                    "<sitemap><loc>" .
                    $this->esc(url("/sitemaps/" . $kind . "/" . $page . ".xml")) .
                    "</loc></sitemap>";
            }
        }
        return $this->xml("sitemapindex", $entries);
    }
    public function sitemap(string $kind, int $page)
    {
        abort_unless($page > 0, 404);
        if (!config("seo.indexable")) {
            return $this->xml("urlset", []);
        }
        $entries = [];
        if ($kind === "static") {
            abort_unless($page === 1, 404);
            foreach (["/", "/shop", "/categories", "/offers"] as $path) {
                $entries[] = "<url><loc>" . $this->esc(url($path)) . "</loc></url>";
            }
        } else {
            $query = $this->query($kind);
            abort_if($page > max(1, (int) ceil((clone $query)->count() / 1000)), 404);
            foreach ($query->orderBy("id")->forPage($page, 1000)->get() as $row) {
                $path = match ($kind) {
                    "products" => "products/" . ($row->slug ?: $row->id),
                    "categories" => "category/" . $row->slug,
                    "pages" => "pages/" . $row->slug,
                };
                $modified = $row->updated_at
                    ? "<lastmod>" . date("c", strtotime($row->updated_at)) . "</lastmod>"
                    : "";
                $entries[] = "<url><loc>" . $this->esc(url("/" . $path)) . "</loc>" . $modified . "</url>";
            }
        }
        return $this->xml("urlset", $entries);
    }
}
