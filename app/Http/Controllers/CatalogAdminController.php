<?php
namespace App\Http\Controllers;
use App\Support\MediaUpload;
use App\Support\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class CatalogAdminController
{
    private bool $sellerMode = false;
    public function asSeller(): self
    {
        \App\Support\SellerAccess::profile(true);
        $this->sellerMode = true;
        return $this;
    }
    private function productGuard(?int $id = null): void
    {
        if (!$this->sellerMode) {
            $this->guard();
            return;
        }
        \App\Support\SellerAccess::profile(true);
        if ($id) {
            \App\Support\SellerAccess::product($id);
        }
    }
    private function markPending(int $id): void
    {
        if ($this->sellerMode) {
            DB::table("products")
                ->where("id", $id)
                ->update(["seller_status" => "pending", "moderation_note" => null]);
        }
    }
    private function destination(string $section): string
    {
        return url($this->sellerMode ? "/seller" : "/admin") . "#" . $section;
    }
    private function guard()
    {
        abort_unless(Auth::user()?->role === "admin" && Auth::user()->is_active, 403);
    }
    private function slug(string $name, string $table, ?int $id = null): string
    {
        $base = Str::slug($name) ?: "item";
        $slug = $base;
        $n = 2;
        while (
            DB::table($table)->where("slug", $slug)->when($id, fn($q) => $q->where("id", "!=", $id))->exists()
        ) {
            $slug = $base . "-" . $n++;
        }
        return $slug;
    }
    public function product(Request $r)
    {
        $this->productGuard($r->filled("id") ? (int) $r->id : null);
        $d = $r->validate([
            "id" => "nullable|integer|exists:products,id",
            "name" => "required|string|max:150",
            "category_id" => "required|integer|exists:categories,id",
            "slug" => [
                "nullable",
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                "max:180",
                Rule::unique("products")->ignore($r->id),
            ],
            "sku" => ["nullable", "string", "max:100", Rule::unique("products")->ignore($r->id)],
            "brand_id" => "nullable|integer|exists:brands,id",
            "brand" => "nullable|string|max:100",
            "audience" => "required|in:women,girls,both",
            "seo_title" => "nullable|string|max:150",
            "seo_description" => "nullable|string|max:320",
            "seo_keywords" => "nullable|string|max:500",
            "description" => "required|string|min:20|max:20000",
            "highlights" => "nullable|string|max:3000",
            "spec_keys" => "nullable|array|max:50",
            "spec_keys.*" => "nullable|string|max:60",
            "spec_values" => "nullable|array|max:50",
            "spec_values.*" => "nullable|string|max:500",
            "price" => "required|integer|min:1|max:1000000",
            "old" => "required|integer|gte:price|max:1000000",
            "color" => "required|string|max:100",
            "image" => ["nullable", 'regex:/^photo-[a-zA-Z0-9-]+$/'],
            "tag" => "nullable|string|max:60",
            "sizes" => "required|string|max:200",
            "stock" => "required|integer|min:0|max:1000000",
            "active" => "nullable|boolean",
            "featured" => "nullable|boolean",
            "images" => "nullable|array|max:10",
            "images.*" => "image|mimes:jpg,jpeg,png,webp,avif|max:5120",
        ]);
        $id = $d["id"] ?? null;
        $existing = $id ? DB::table("products")->find($id) : null;
        $brandId = $r->has('brand_id') ? ($d['brand_id'] ?? null) : ($existing?->brand_id ?? null);
        // Old clients may still post the text label. It must resolve to a managed brand.
        if (!$r->has('brand_id') && $r->has('brand')) {
            $name = trim($d['brand'] ?? '');
            $brandId = $name !== '' ? DB::table('brands')->where('name', $name)->value('id') : null;
            if ($name !== '' && !$brandId) {
                throw ValidationException::withMessages(['brand_id' => 'Select a brand from the list. Ask the administrator to add a missing brand.']);
            }
        }
        $brand = $brandId ? DB::table('brands')->find($brandId) : null;
        if ($brand && !$brand->active && (int) $brand->id !== (int) ($existing?->brand_id ?? 0)) {
            throw ValidationException::withMessages(['brand_id' => 'This brand is inactive. Select an active brand.']);
        }
        if (!$id && !$r->hasFile("images") && !$r->filled("image")) {
            throw ValidationException::withMessages(["images" => "Add at least one product image."]);
        }
        $specs = [];
        foreach ($d["spec_keys"] ?? [] as $n => $key) {
            $key = trim($key ?? "");
            $value = trim($d["spec_values"][$n] ?? "");
            if ($key && $value) {
                $specs[] = ["name" => $key, "value" => $value];
            }
        }
        $values = collect($d)
            ->only([
                "seo_title",
                "seo_description",
                "seo_keywords",
                "name",
                "category_id",
                "brand_id",
                "audience",
                "description",
                "price",
                "old",
                "color",
                "tag",
                "stock",
            ])
            ->all();
        $values['brand_id'] = $brand?->id;
        $values['brand'] = $brand?->name;
        if ($this->sellerMode) {
            $values["seller_id"] = \App\Support\SellerAccess::profile(true)->id;
            $values["seller_status"] = "pending";
            $values["moderation_note"] = null;
        }
        $values["category"] = DB::table("categories")->where("id", $d["category_id"])->value("name");
        $values["slug"] = $d["slug"] ?? ($existing?->slug ?? $this->slug($d["name"], "products", $id));
        $values["sku"] = $d["sku"] ?? ($existing?->sku ?? "AVL-" . strtoupper(Str::random(8)));
        $values["image"] = $d["image"] ?? ($existing?->image ?? "photo-1515372039744-b8f02a3ae446");
        $values["tag"] = $values["tag"] ?? "NEW";
        $values["sizes"] = json_encode(
            array_values(array_filter(array_unique(array_map("trim", explode(",", $d["sizes"]))))),
        );
        $values["highlights"] = json_encode(
            array_values(array_filter(array_map("trim", preg_split("/\R/", $d["highlights"] ?? "")))),
        );
        $values["specifications"] = json_encode($specs);
        $values["active"] = $r->boolean("active");
        $values["featured"] = $this->sellerMode ? false : $r->boolean("featured");
        $values["updated_at"] = now();
        DB::transaction(function () use ($r, $id, $values) {
            $productId = $id;
            if ($id) {
                DB::table("products")->where("id", $id)->lockForUpdate()->first();
                if (
                    DB::table("product_variants")->where("product_id", $id)->where("active", true)->exists()
                ) {
                    $values["stock"] = DB::table("product_variants")
                        ->where("product_id", $id)
                        ->where("active", true)
                        ->sum("stock");
                }
                DB::table("products")->where("id", $id)->update($values);
            } else {
                $productId = DB::table("products")->insertGetId(
                    $values + ["rating" => 0, "created_at" => now()],
                );
            }
            MediaUpload::add($r, "product", $productId);
        });
        return redirect($this->destination("products"))->with(
            "success",
            "Product details, specifications and images saved.",
        );
    }
    public function category(Request $r)
    {
        $this->guard();
        $d = $r->validate([
            "id" => "nullable|integer|exists:categories,id",
            "name" => ["required", "string", "max:80", Rule::unique("categories")->ignore($r->id)],
            "slug" => [
                "nullable",
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                "max:100",
                Rule::unique("categories")->ignore($r->id),
            ],
            "parent_id" => "nullable|integer|exists:categories,id",
            "seo_title" => "nullable|string|max:150",
            "seo_description" => "nullable|string|max:320",
            "seo_keywords" => "nullable|string|max:500",
            "description" => "nullable|string|max:5000",
            "active" => "nullable|boolean",
            "sort_order" => "required|integer|min:0|max:10000",
            "images" => "nullable|array|max:10",
            "images.*" => "image|mimes:jpg,jpeg,png,webp,avif|max:5120",
        ]);
        $id = $d["id"] ?? null;
        $existing = $id ? DB::table("categories")->find($id) : null;
        if ($id && !empty($d["parent_id"])) {
            if (in_array((int) $d["parent_id"], \App\Support\Shop::descendants($id))) {
                throw ValidationException::withMessages([
                    "parent_id" => "A category cannot be placed inside itself or its children.",
                ]);
            }
        }
        $values = collect($d)
            ->only([
                "seo_title",
                "seo_description",
                "seo_keywords",
                "name",
                "parent_id",
                "description",
                "sort_order",
            ])
            ->all();
        $values["slug"] = $d["slug"] ?? ($existing?->slug ?? $this->slug($d["name"], "categories", $id));
        $values["active"] = $r->boolean("active");
        $values["updated_at"] = now();
        DB::transaction(function () use ($r, $id, $values, $existing) {
            if ($id) {
                DB::table("categories")->where("id", $id)->update($values);
                DB::table("products")
                    ->where("category_id", $id)
                    ->update(["category" => $values["name"]]);
                if ($existing->name !== $values["name"]) {
                    foreach (DB::table("promotions")->where("scope", "category")->get() as $offer) {
                        $targets = json_decode($offer->targets ?? "[]", true);
                        $targets = array_map(
                            fn($v) => $v === $existing->name ? $values["name"] : $v,
                            $targets,
                        );
                        DB::table("promotions")
                            ->where("id", $offer->id)
                            ->update(["targets" => json_encode($targets)]);
                    };
                }
                $categoryId = $id;
            } else {
                $categoryId = DB::table("categories")->insertGetId($values + ["created_at" => now()]);
            }
            MediaUpload::add($r, "category", $categoryId);
        });
        return redirect(url("/admin") . "#categories")->with("success", "Category and image gallery saved.");
    }
    public function variants(Request $r, int $id)
    {
        $this->productGuard($id);
        $d = $r->validate([
            "variants" => "nullable|array|max:100",
            "variants.*.id" => "nullable|integer",
            "variants.*.size" => "required|string|max:30",
            "variants.*.color" => "required|string|max:80",
            "variants.*.sku" => "nullable|string|max:100",
            "variants.*.price" => "nullable|integer|min:1|max:1000000",
            "variants.*.stock" => "required|integer|min:0|max:1000000",
            "variants.*.active" => "required|boolean",
        ]);
        DB::transaction(function () use ($id, $d) {
            abort_unless(DB::table("products")->where("id", $id)->lockForUpdate()->first(), 404);
            $seen = [];
            $rows = $d["variants"] ?? [];
            foreach ($rows as $row) {
                $key = mb_strtolower($row["size"] . "|" . $row["color"]);
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        "variants" => "Duplicate size/colour combination.",
                    ]);
                }
                $seen[$key] = true;
                $v = !empty($row["id"])
                    ? DB::table("product_variants")
                        ->where("id", $row["id"])
                        ->where("product_id", $id)
                        ->first()
                    : DB::table("product_variants")
                        ->where("product_id", $id)
                        ->where("size", $row["size"])
                        ->where("color", $row["color"])
                        ->first();
                if (!empty($row["id"]) && !$v) {
                    abort(404);
                }
                if (
                    !empty($row["sku"]) &&
                    DB::table("product_variants")
                        ->where("sku", $row["sku"])
                        ->when($v, fn($q) => $q->where("id", "!=", $v->id))
                        ->exists()
                ) {
                    throw ValidationException::withMessages(["variants" => "Variant SKU already exists."]);
                }
            }
            DB::table("product_variants")
                ->where("product_id", $id)
                ->update(["active" => false]);
            foreach ($rows as $row) {
                $v = !empty($row["id"])
                    ? DB::table("product_variants")
                        ->where("id", $row["id"])
                        ->where("product_id", $id)
                        ->first()
                    : DB::table("product_variants")
                        ->where("product_id", $id)
                        ->where("size", $row["size"])
                        ->where("color", $row["color"])
                        ->first();
                unset($row["id"]);
                $row["sku"] = $row["sku"] ?? null;
                $row["price"] = isset($row["price"]) && $row["price"] !== "" ? (int) $row["price"] : null;
                $row["updated_at"] = now();
                if ($v) {
                    DB::table("product_variants")->where("id", $v->id)->update($row);
                } else {
                    DB::table("product_variants")->insert(
                        $row + ["product_id" => $id, "created_at" => now()],
                    );
                }
            }
            $active = DB::table("product_variants")->where("product_id", $id)->where("active", true);
            DB::table("products")
                ->where("id", $id)
                ->update([
                    "stock" => (clone $active)->sum("stock"),
                    "sizes" => json_encode((clone $active)->pluck("size")->unique()->values()->all()),
                    "updated_at" => now(),
                ]);
        });
        $this->markPending($id);
        return redirect($this->destination("products"))->with("success", "Size/colour inventory saved.");
    }
    private function mediaOwner(string $kind, int $id): array
    {
        if ($this->sellerMode) {
            abort_unless($kind === "product", 403);
            $this->productGuard($id);
        } else {
            $this->guard();
        }
        abort_unless(in_array($kind, ["product", "category"]), 404);
        $table = $kind === "product" ? "product_images" : "category_images";
        $parent = $kind === "product" ? "products" : "categories";
        abort_unless(DB::table($parent)->where("id", $id)->exists(), 404);
        return [$table, $kind . "_id"];
    }
    public function mediaOrder(Request $r, string $kind, int $id)
    {
        [$table, $key] = $this->mediaOwner($kind, $id);
        $d = $r->validate([
            "order" => "required|array|max:20",
            "order.*" => "required|integer|distinct",
            "alts" => "nullable|array",
            "alts.*" => "nullable|string|max:150",
        ]);
        $actual = DB::table($table)->where($key, $id)->pluck("id")->map(fn($v) => (int) $v)->all();
        $desired = array_map("intval", $d["order"]);
        $a = $actual;
        $b = $desired;
        sort($a);
        sort($b);
        if ($a !== $b) {
            throw ValidationException::withMessages(["images" => "Image order does not match this item."]);
        }
        DB::transaction(function () use ($desired, $table, $d) {
            foreach ($desired as $position => $imageId) {
                DB::table($table)
                    ->where("id", $imageId)
                    ->update([
                        "position" => $position,
                        "alt" => $d["alts"][$imageId] ?? null,
                        "updated_at" => now(),
                    ]);
            }
        });
        if ($kind === "product") {
            $this->markPending($id);
        }
        return redirect($this->destination($kind === "product" ? "products" : "categories"))->with(
            "success",
            "Image order saved. First image is the cover.",
        );
    }
    public function removeMedia(string $kind, int $id, int $image)
    {
        [$table, $key] = $this->mediaOwner($kind, $id);
        $m = DB::table($table)->where($key, $id)->where("id", $image)->first();
        abort_unless($m, 404);
        if ($kind === "product") {
            $this->markPending($id);
        }
        DB::table($table)->where("id", $image)->delete();
        if (
            str_starts_with($m->path, "uploads/") &&
            !DB::table("product_images")->where("path", $m->path)->exists() &&
            !DB::table("category_images")->where("path", $m->path)->exists()
        ) {
            $file = realpath(public_path($m->path));
            $root = realpath(public_path("uploads"));
            if ($file && $root && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
                unlink($file);
            }
        }
        return redirect($this->destination($kind === "product" ? "products" : "categories"))->with(
            "success",
            "Image removed.",
        );
    }
    public function review(Request $r, int $id)
    {
        $this->guard();
        $d = $r->validate([
            "status" => "required|in:pending,approved,rejected",
            "reply" => "nullable|string|max:2000",
        ]);
        DB::table("reviews")
            ->where("id", $id)
            ->update($d + ["updated_at" => now()]);
        return redirect(url("/admin") . "#reviews")->with("success", "Review moderation saved.");
    }
    public function returns(Request $r, int $id)
    {
        $this->guard();
        $d = $r->validate([
            "status" => "required|in:requested,approved,rejected,received,completed",
            "admin_note" => "nullable|string|max:3000",
            "refund_amount" => "required|integer|min:0",
            "refund_reference" => "nullable|required_if:status,completed|string|max:200",
            "restock" => "nullable|boolean",
        ]);
        DB::transaction(function () use ($r, $id, $d) {
            $ret = DB::table("return_requests")->where("id", $id)->lockForUpdate()->first();
            abort_unless($ret, 404);
            $order = DB::table("orders")->where("id", $ret->order_id)->lockForUpdate()->first();
            $allowed = [
                "requested" => ["requested", "approved", "rejected"],
                "approved" => ["approved", "received"],
                "received" => ["received", "completed"],
                "rejected" => ["rejected"],
                "completed" => ["completed"],
            ];
            if (!in_array($d["status"], $allowed[$ret->status] ?? [])) {
                throw ValidationException::withMessages(["returns" => "Invalid return status transition."]);
            }
            if (
                $d["refund_amount"] +
                    DB::table("return_requests")
                        ->where("order_id", $order->id)
                        ->where("id", "!=", $id)
                        ->where("status", "!=", "rejected")
                        ->sum("refund_amount") >
                $order->total
            ) {
                throw ValidationException::withMessages([
                    "refund_amount" => "Refund cannot exceed the order total.",
                ]);
            }
            $values = collect($d)->except("restock")->all();
            $values["updated_at"] = now();
            if (
                in_array($d["status"], ["received", "completed"]) &&
                $r->boolean("restock") &&
                !$ret->restocked_at
            ) {
                foreach (json_decode($ret->items, true) as $line) {
                    Inventory::restore($line);
                }
                $values["restocked_at"] = now();
            }
            DB::table("return_requests")->where("id", $id)->update($values);
            \App\Support\ReferralWallet::sync($order->id);
        });
        return redirect(url("/admin") . "#returns")->with(
            "success",
            "Return record updated. Refund references record payments processed separately; no money is transferred by this form.",
        );
    }
    public function settings(Request $r)
    {
        $this->guard();
        $seoRules = [];
        foreach (["home", "shop", "categories", "offers"] as $page) {
            foreach (["title" => 150, "description" => 320, "keywords" => 500] as $field => $max) {
                $seoRules["seo_" . $page . "_" . $field] = "nullable|string|max:" . $max;
            };
        }
        $d = $r->validate(
            [
                "store_name" => "required|string|max:80",
                "shipping_fee" => "required|integer|between:0,10000",
                "free_shipping_minimum" => "required|integer|between:0,1000000",
                "return_days" => "required|integer|between:1,30",
                "cod_enabled" => "required|boolean",
                "support_email" => "nullable|email|max:200",
                "support_phone" => "nullable|string|max:30",
                "business_address" => "nullable|string|max:1000",
            ] + $seoRules,
        );
        foreach ($d as $key => $value) {
            DB::table("store_settings")->updateOrInsert(["key" => $key], ["value" => (string) $value]);
        }
        return redirect(url("/admin") . "#store-settings")->with("success", "Store settings saved.");
    }
    public function page(Request $r, int $id)
    {
        $this->guard();
        $d = $r->validate([
            "seo_title" => "nullable|string|max:150",
            "seo_description" => "nullable|string|max:320",
            "seo_keywords" => "nullable|string|max:500",
            "title" => "required|string|max:150",
            "body" => "required|string|max:30000",
            "active" => "required|boolean",
        ]);
        DB::table("pages")
            ->where("id", $id)
            ->update($d + ["updated_at" => now()]);
        return redirect(url("/admin") . "#pages")->with("success", "Content page saved.");
    }
    public function inquiry(Request $r, int $id)
    {
        $this->guard();
        $d = $r->validate(["status" => "required|in:open,resolved"]);
        DB::table("inquiries")
            ->where("id", $id)
            ->update($d + ["updated_at" => now()]);
        return redirect(url("/admin") . "#support")->with("success", "Support request status updated.");
    }
}
