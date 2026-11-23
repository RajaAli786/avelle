<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create("sellers", function (Blueprint $t) {
            $t->id();
            $t->foreignId("user_id")->unique()->constrained()->restrictOnDelete();
            $t->string("shop_name", 120);
            $t->string("slug")->unique();
            $t->string("phone", 15);
            $t->text("pickup_address");
            $t->string("city", 100);
            $t->string("state", 100);
            $t->string("pincode", 6);
            $t->text("description")->nullable();
            $t->string("status")->default("pending")->index();
            $t->decimal("commission_rate", 5, 2)->default(10);
            $t->text("admin_note")->nullable();
            $t->timestamps();
        });
        Schema::table("products", function (Blueprint $t) {
            $t->foreignId("seller_id")->nullable()->constrained()->restrictOnDelete();
            $t->string("seller_status")->default("approved")->index();
            $t->text("moderation_note")->nullable();
        });
        Schema::create("seller_orders", function (Blueprint $t) {
            $t->id();
            $t->foreignId("order_id")->constrained()->cascadeOnDelete();
            $t->foreignId("seller_id")->nullable()->constrained()->restrictOnDelete();
            $t->string("fulfilment_key")->unique();
            $t->json("items");
            $t->unsignedInteger("subtotal");
            $t->unsignedInteger("discount")->default(0);
            $t->unsignedInteger("net_amount");
            $t->decimal("commission_rate", 5, 2)->default(0);
            $t->unsignedInteger("commission_amount")->default(0);
            $t->unsignedInteger("seller_amount")->default(0);
            $t->string("status")->default("placed");
            $t->string("carrier")->nullable();
            $t->string("tracking_number")->nullable();
            $t->string("tracking_url", 2048)->nullable();
            $t->timestamp("delivered_at")->nullable();
            $t->timestamp("settled_at")->nullable();
            $t->string("payout_reference")->nullable();
            $t->text("payout_note")->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists("seller_orders");
        Schema::table("products", function (Blueprint $t) {
            $t->dropConstrainedForeignId("seller_id");
            $t->dropColumn(["seller_status", "moderation_note"]);
        });
        Schema::dropIfExists("sellers");
    }
};
