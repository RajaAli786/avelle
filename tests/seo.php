<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Support\Seo;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\CatalogAdminController;
function check($yes, $message)
{
    if (!$yes) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}
$app["config"]->set("session.driver", "array");
$session = $app["session"]->driver();
$session->start();
view()->share("errors", new Illuminate\Support\ViewErrorBag());
function req(string $path, array $data = [], string $method = "GET")
{
    global $app, $session;
    $r = Request::create($path, $method, $data);
    $r->setLaravelSession($session);
    $app->instance("request", $r);
    $app["url"]->setRequest($r);
    return $r;
}
DB::beginTransaction();
try {
    $admin = App\Models\User::where("role", "admin")->firstOrFail();
    Auth::login($admin);
    $catalog = new CatalogAdminController();
    $page = DB::table("pages")->where("slug", "about")->first();
    $catalog->page(
        req(
            "/admin/pages/" . $page->id,
            [
                "title" => $page->title,
                "body" => $page->body,
                "active" => 1,
                "seo_title" => 'QA unique title " & test',
                "seo_description" => "QA custom description <script>alert(1)</script>",
                "seo_keywords" => "qa, custom page",
            ],
            "POST",
        ),
        $page->id,
    );
    check(
        DB::table("pages")->where("id", $page->id)->value("seo_title") === 'QA unique title " & test',
        "Admin page SEO fields persist",
    );
    config(["seo.indexable" => true]);
    req("/pages/about");
    $html = (new MarketplaceController())->page("about")->render();
    check(!str_contains($html, "current() }}"), "Shared head contains no template remnants");
    check(
        str_contains($html, "QA unique title &quot; &amp; test") &&
            str_contains($html, 'name="keywords" content="qa, custom page"'),
        "Custom title and keywords render with escaped attributes",
    );
    check(
        !str_contains($html, "<script>alert(1)</script>") && str_contains($html, "&lt;script&gt;"),
        "Descriptions cannot inject HTML",
    );
    check(
        str_contains($html, "noindex, follow"),
        "Demo policies remain noindex even with public indexing enabled",
    );
    $catId = DB::table("categories")->insertGetId([
        "name" => "QA SEO category",
        "slug" => "qa-seo-category",
        "active" => 1,
        "sort_order" => 0,
        "description" => "A unique category description for the test fixture.",
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    $catalog->category(
        req(
            "/admin/categories",
            [
                "id" => $catId,
                "name" => "QA SEO category",
                "slug" => "qa-seo-category",
                "active" => 1,
                "sort_order" => 0,
                "seo_title" => "QA category SEO",
                "seo_description" => "A category-specific search description.",
                "seo_keywords" => "category, qa",
            ],
            "POST",
        ),
    );
    $cat = DB::table("categories")->find($catId);
    req("/category/qa-seo-category");
    $m = Seo::meta(["category" => $cat], "fallback", "fallback");
    check(
        $m["title"] === "QA category SEO" && $m["description"] === "A category-specific search description.",
        "Category SEO saves and overrides fallback",
    );
    $p = DB::table("products")->first();
    $data = [
        "id" => $p->id,
        "name" => $p->name,
        "category_id" => $p->category_id,
        "slug" => $p->slug,
        "sku" => $p->sku,
        "brand" => $p->brand,
        "audience" => $p->audience,
        "description" => $p->description,
        "price" => $p->price,
        "old" => $p->old,
        "color" => $p->color,
        "image" => $p->image,
        "tag" => $p->tag,
        "sizes" => implode(", ", json_decode($p->sizes, true)),
        "stock" => $p->stock,
        "active" => 1,
        "seo_title" => "QA product SEO",
        "seo_description" => "A product-specific search description.",
        "seo_keywords" => "product, qa",
    ];
    $catalog->product(req("/admin/products", $data, "POST"));
    $p = App\Support\Shop::one($p->slug);
    req("/products/" . $p->id);
    $m = Seo::meta(["product" => $p], "fallback", "fallback");
    check(
        $m["title"] === "QA product SEO" && $m["canonical"] === $p->url,
        "Product SEO persists and numeric URL canonicals use slug",
    );
    check(
        $m["schema"][0]["@type"] === "Product" && $m["schema"][0]["offers"]["priceCurrency"] === "INR",
        "Product structured data contains real INR offers",
    );
    check(
        $p->review_count > 0 === isset($m["schema"][0]["aggregateRating"]),
        "Structured rating appears only for approved reviews",
    );
    req("/shop", ["page" => 2, "utm_source" => "qa"]);
    $m = Seo::meta([], "Shop", "fallback");
    check(
        str_ends_with($m["canonical"], "/shop?page=2") && !str_contains($m["canonical"], "utm_"),
        "Pagination canonical preserves page and removes tracking",
    );
    req("/shop", ["q" => "dress"]);
    check(
        str_starts_with(Seo::meta([], "Shop", "fallback")["robots"], "noindex"),
        "Search/filter results are noindex",
    );
    req("/account/security");
    $middleware = new App\Http\Middleware\SearchIndexing();
    $response = $middleware->handle(request(), fn() => response("Private"));
    check(
        $response->headers->get("X-Robots-Tag") === "noindex, nofollow",
        "Private account endpoints carry noindex response header",
    );
    $seo = new SeoController();
    req("/sitemap.xml");
    $index = $seo->index()->getContent();
    check(
        str_contains($index, "sitemaps/products/1.xml") && !str_contains($index, "/account"),
        "Sitemap index includes public catalogue only",
    );
    $xml = $seo->sitemap("products", 1)->getContent();
    check(
        simplexml_load_string($xml) !== false && str_contains($xml, $p->slug),
        "Product sitemap is valid XML with canonical slug",
    );
    DB::table("products")
        ->where("id", $p->id)
        ->update(["active" => 0]);
    check(
        !str_contains($seo->sitemap("products", 1)->getContent(), $p->slug),
        "Hidden products are excluded from sitemap",
    );
    check(
        !str_contains($seo->sitemap("pages", 1)->getContent(), "/pages/about"),
        "Demo policy pages are excluded from sitemap",
    );
    check(
        str_contains($seo->robots()->getContent(), "Sitemap:") &&
            str_contains($seo->robots()->getContent(), "Disallow: /account"),
        "Public robots file identifies sitemap and private routes",
    );
    config(["seo.indexable" => false]);
    check(
        str_contains($seo->robots()->getContent(), "Disallow: /") &&
            !str_contains($seo->index()->getContent(), "<sitemap>"),
        "Development indexing guard blocks crawling and empties sitemap",
    );
    try {
        $catalog->page(
            req(
                "/admin/pages/" . $page->id,
                [
                    "title" => $page->title,
                    "body" => "Test",
                    "active" => 1,
                    "seo_title" => str_repeat("x", 151),
                ],
                "POST",
            ),
            $page->id,
        );
        throw new RuntimeException("Oversized SEO title accepted");
    } catch (Illuminate\Validation\ValidationException $e) {
        check(isset($e->errors()["seo_title"]), "SEO field limits are enforced");
    }
    $seller = App\Models\User::create([
        "name" => "QA SEO Seller",
        "email" => "qa-seo-" . uniqid() . "@example.test",
        "password" => bin2hex(random_bytes(16)),
    ]);
    $seller->role = "seller";
    $seller->save();
    $sid = DB::table("sellers")->insertGetId([
        "user_id" => $seller->id,
        "shop_name" => "QA SEO Shop",
        "slug" => "qa-seo-" . uniqid(),
        "phone" => "9876543210",
        "pickup_address" => "QA fixture pickup address",
        "city" => "Delhi",
        "state" => "Delhi",
        "pincode" => "110001",
        "description" => "Temporary fixture",
        "status" => "approved",
        "commission_rate" => 10,
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    DB::table("products")
        ->where("id", $p->id)
        ->update(["seller_id" => $sid, "active" => 1, "seller_status" => "approved"]);
    Auth::login($seller);
    $data["seo_title"] = "Seller own SEO title";
    (new CatalogAdminController())->asSeller()->product(req("/seller/products", $data, "POST"));
    $saved = DB::table("products")->find($p->id);
    check(
        $saved->seo_title === "Seller own SEO title" && $saved->seller_status === "pending",
        "Seller edits own SEO and product returns to moderation",
    );
    $other = DB::table("products")->where("id", "!=", $p->id)->first();
    try {
        $data["id"] = $other->id;
        (new CatalogAdminController())->asSeller()->product(req("/seller/products", $data, "POST"));
        throw new RuntimeException("Seller changed another product");
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        check($e->getStatusCode() === 403, "Seller cannot edit another product SEO");
    }
    $sellerHtml = (new App\Http\Controllers\SellerController())->index()->render();
    check(
        str_contains($sellerHtml, 'name="seo_title"') && str_contains($sellerHtml, "Seller own SEO title"),
        "Seller catalogue renders SEO fields and saved values",
    );
    echo "SEO checks passed. Fixtures rolled back.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
