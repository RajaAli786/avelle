<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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
$request = Request::create("/pages/contact", "GET");
$request->setLaravelSession($session);
$app->instance("request", $request);
$app["url"]->setRequest($request);
view()->share("errors", new Illuminate\Support\ViewErrorBag());
DB::beginTransaction();
try {
    $controller = new App\Http\Controllers\MarketplaceController();
    foreach (require database_path("demo-pages.php") as $slug => $body) {
        $page = DB::table("pages")->where("slug", $slug)->first();
        check($page && $page->active && strlen($page->body) > 500, "$slug has published sample content");
        $html = $controller->page($slug)->render();
        check(
            str_contains($html, "information.css") &&
                str_contains($html, "YOUR AVELLE GUIDE") &&
                !str_contains($html, "preparing this information"),
            "$slug renders with themed navigation and content",
        );
    }
    $faq = $controller->page("faq")->render();
    check(
        substr_count($faq, '<details class="info-faq">') === 7,
        "FAQ questions render as accessible native accordions",
    );
    DB::table("pages")
        ->where("slug", "about")
        ->update(["body" => "Owner custom text <script>alert(1)</script>"]);
    $migration = require database_path("migrations/2026_11_04_000001_demo_information_pages.php");
    $migration->up();
    $html = $controller->page("about")->render();
    check(
        str_contains($html, "Owner custom text") &&
            str_contains($html, "&lt;script&gt;") &&
            !str_contains($html, "<script>alert(1)</script>"),
        "Custom content survives demo migration and renders safely",
    );
    $r = Request::create("/contact", "POST", [
        "name" => "Demo QA",
        "email" => "demo-qa@example.test",
        "subject" => "Dummy support check",
        "message" => "This is a temporary local support form verification.",
    ]);
    $r->setLaravelSession($session);
    $app->instance("request", $r);
    $response = $controller->contact($r);
    check(
        DB::table("inquiries")->where("email", "demo-qa@example.test")->exists() &&
            str_contains($session->get("success"), "AVL-SUPPORT-"),
        "Contact submission saves a support request and reference",
    );
    try {
        $r->merge(["website" => "spam"]);
        $controller->contact($r);
        throw new RuntimeException("Honeypot accepted");
    } catch (Illuminate\Validation\ValidationException $e) {
        check(isset($e->errors()["website"]), "Contact honeypot rejects automated submissions");
    }
    echo "Information page checks passed. Fixtures rolled back.\n";
} finally {
    DB::rollBack();
}
