<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
return new class extends Migration {
    public function up(): void
    {
        Schema::create("categories", function (Blueprint $t) {
            $t->id();
            $t->foreignId("parent_id")->nullable()->constrained("categories")->nullOnDelete();
            $t->string("name")->unique();
            $t->string("slug")->unique();
            $t->text("description")->nullable();
            $t->boolean("active")->default(true);
            $t->unsignedInteger("sort_order")->default(0);
            $t->timestamps();
        });
        Schema::table("products", function (Blueprint $t) {
            $t->foreignId("category_id")->nullable()->constrained()->nullOnDelete();
            $t->string("slug")->nullable()->unique();
            $t->string("sku")->nullable()->unique();
            $t->string("brand")->nullable();
            $t->string("audience")->default("women");
            $t->text("description")->nullable();
            $t->json("highlights")->nullable();
            $t->json("specifications")->nullable();
            $t->boolean("active")->default(true);
            $t->boolean("featured")->default(false);
        });
        foreach (DB::table("products")->get() as $p) {
            $category = DB::table("categories")->where("name", $p->category)->first();
            if (!$category) {
                $id = DB::table("categories")->insertGetId([
                    "name" => $p->category,
                    "slug" => Str::slug($p->category),
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            } else {
                $id = $category->id;
            }
            DB::table("products")
                ->where("id", $p->id)
                ->update([
                    "category_id" => $id,
                    "slug" => Str::slug($p->name) . "-" . $p->id,
                    "sku" => "AVL-" . str_pad($p->id, 5, "0", STR_PAD_LEFT),
                    "description" =>
                        "Discover " .
                        $p->name .
                        ". Choose your preferred size and review the available product details before ordering.",
                    "highlights" => "[]",
                    "specifications" => "[]",
                    "featured" => true,
                ]);
        }
        foreach (["product_images" => "product", "category_images" => "category"] as $table => $owner) {
            Schema::create($table, function (Blueprint $t) use ($owner) {
                $t->id();
                $t->foreignId($owner . "_id")
                    ->constrained()
                    ->cascadeOnDelete();
                $t->string("path", 2048);
                $t->string("alt")->nullable();
                $t->unsignedInteger("position")->default(0);
                $t->timestamps();
            });
        }
        foreach (DB::table("products")->get() as $p) {
            $path = file_exists(public_path("images/" . $p->image . ".jpg"))
                ? "images/" . $p->image . ".jpg"
                : "https://images.unsplash.com/" . $p->image . "?w=1000&fit=crop";
            DB::table("product_images")->insert([
                "product_id" => $p->id,
                "path" => $path,
                "alt" => $p->name,
                "position" => 0,
                "created_at" => now(),
                "updated_at" => now(),
            ]);
            if (!DB::table("category_images")->where("category_id", $p->category_id)->exists()) {
                DB::table("category_images")->insert([
                    "category_id" => $p->category_id,
                    "path" => $path,
                    "alt" => $p->category,
                    "position" => 0,
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }
        }
        Schema::create("product_variants", function (Blueprint $t) {
            $t->id();
            $t->foreignId("product_id")->constrained()->cascadeOnDelete();
            $t->string("size", 30);
            $t->string("color", 80);
            $t->string("sku")->nullable()->unique();
            $t->unsignedInteger("price")->nullable();
            $t->unsignedInteger("stock")->default(0);
            $t->boolean("active")->default(true);
            $t->timestamps();
            $t->unique(["product_id", "size", "color"]);
        });
        Schema::create("addresses", function (Blueprint $t) {
            $t->id();
            $t->foreignId("user_id")->constrained()->cascadeOnDelete();
            $t->string("label", 30)->default("Home");
            $t->string("name", 100);
            $t->string("phone", 15);
            $t->text("address");
            $t->string("city", 100);
            $t->string("state", 100);
            $t->string("pincode", 6);
            $t->boolean("is_default")->default(false);
            $t->timestamps();
        });
        Schema::create("wishlists", function (Blueprint $t) {
            $t->id();
            $t->foreignId("user_id")->constrained()->cascadeOnDelete();
            $t->foreignId("product_id")->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(["user_id", "product_id"]);
        });
        Schema::create("carts", function (Blueprint $t) {
            $t->id();
            $t->foreignId("user_id")->unique()->constrained()->cascadeOnDelete();
            $t->json("items");
            $t->timestamps();
        });
        Schema::table("orders", function (Blueprint $t) {
            $t->string("state", 100)->nullable();
            $t->string("payment_status")->default("unpaid");
            $t->string("gateway_order_id")->nullable()->unique();
            $t->string("gateway_payment_id")->nullable()->unique();
            $t->string("carrier")->nullable();
            $t->string("tracking_number")->nullable();
            $t->string("tracking_url", 2048)->nullable();
            $t->timestamp("delivered_at")->nullable();
            $t->uuid("checkout_key")->nullable()->unique();
        });
        Schema::create("order_events", function (Blueprint $t) {
            $t->id();
            $t->foreignId("order_id")->constrained()->cascadeOnDelete();
            $t->string("status");
            $t->text("note")->nullable();
            $t->timestamps();
        });
        Schema::create("reviews", function (Blueprint $t) {
            $t->id();
            $t->foreignId("product_id")->constrained()->cascadeOnDelete();
            $t->foreignId("user_id")->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger("rating");
            $t->string("title", 120);
            $t->text("body");
            $t->string("status")->default("pending");
            $t->text("reply")->nullable();
            $t->timestamps();
            $t->unique(["product_id", "user_id"]);
        });
        Schema::create("return_requests", function (Blueprint $t) {
            $t->id();
            $t->foreignId("order_id")->constrained()->cascadeOnDelete();
            $t->foreignId("user_id")->constrained()->cascadeOnDelete();
            $t->json("items");
            $t->string("reason");
            $t->text("notes")->nullable();
            $t->string("status")->default("requested");
            $t->text("admin_note")->nullable();
            $t->unsignedInteger("refund_amount")->default(0);
            $t->string("refund_reference")->nullable();
            $t->timestamp("restocked_at")->nullable();
            $t->timestamps();
        });
        Schema::create("store_settings", function (Blueprint $t) {
            $t->string("key")->primary();
            $t->text("value")->nullable();
        });
        foreach (
            [
                "store_name" => "AVELLE",
                "shipping_fee" => "79",
                "free_shipping_minimum" => "1499",
                "return_days" => "7",
                "cod_enabled" => "1",
                "support_email" => "",
                "support_phone" => "",
                "business_address" => "",
            ]
            as $key => $value
        ) {
            DB::table("store_settings")->insert(["key" => $key, "value" => $value]);
        }
        Schema::create("pages", function (Blueprint $t) {
            $t->id();
            $t->string("slug")->unique();
            $t->string("title");
            $t->text("body");
            $t->boolean("active")->default(true);
            $t->timestamps();
        });
        foreach (
            [
                "about" => "About Avelle",
                "contact" => "Contact us",
                "shipping" => "Shipping & delivery",
                "returns" => "Returns & exchanges",
                "privacy" => "Privacy policy",
                "terms" => "Terms & conditions",
                "faq" => "Frequently asked questions",
            ]
            as $slug => $title
        ) {
            DB::table("pages")->insert([
                "slug" => $slug,
                "title" => $title,
                "body" =>
                    "The store owner is preparing this information. Please check back before placing an order.",
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }
        Schema::create("inquiries", function (Blueprint $t) {
            $t->id();
            $t->string("name", 100);
            $t->string("email", 200);
            $t->string("subject", 150);
            $t->text("message");
            $t->string("status")->default("open");
            $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (
            [
                "inquiries",
                "pages",
                "store_settings",
                "return_requests",
                "reviews",
                "order_events",
                "carts",
                "wishlists",
                "addresses",
                "product_variants",
                "category_images",
                "product_images",
            ]
            as $table
        ) {
            Schema::dropIfExists($table);
        }
        Schema::table("orders", function (Blueprint $t) {
            $t->dropColumn([
                "state",
                "payment_status",
                "gateway_order_id",
                "gateway_payment_id",
                "carrier",
                "tracking_number",
                "tracking_url",
                "delivered_at",
                "checkout_key",
            ]);
        });
        Schema::table("products", function (Blueprint $t) {
            $t->dropConstrainedForeignId("category_id");
            $t->dropColumn([
                "slug",
                "sku",
                "brand",
                "audience",
                "description",
                "highlights",
                "specifications",
                "active",
                "featured",
            ]);
        });
        Schema::dropIfExists("categories");
    }
};
