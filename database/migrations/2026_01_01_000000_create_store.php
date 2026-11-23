<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create("products", function (Blueprint $t) {
            $t->id();
            $t->string("name");
            $t->string("category");
            $t->unsignedInteger("price");
            $t->unsignedInteger("old");
            $t->string("color");
            $t->string("image");
            $t->string("tag");
            $t->json("sizes");
            $t->unsignedInteger("stock");
            $t->decimal("rating", 2, 1);
            $t->timestamps();
        });
        Schema::create("orders", function (Blueprint $t) {
            $t->id();
            $t->uuid("reference")->unique();
            $t->string("name");
            $t->string("email");
            $t->string("phone");
            $t->text("address");
            $t->string("city");
            $t->string("pincode");
            $t->json("items");
            $t->unsignedInteger("subtotal");
            $t->unsignedInteger("shipping");
            $t->unsignedInteger("total");
            $t->string("status")->default("placed");
            $t->string("payment_method")->default("cod");
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists("orders");
        Schema::dropIfExists("products");
    }
};
