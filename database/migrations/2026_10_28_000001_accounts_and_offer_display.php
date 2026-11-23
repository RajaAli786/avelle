<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        Schema::create("users", function (Blueprint $t) {
            $t->id();
            $t->string("name");
            $t->string("email")->unique();
            $t->string("password");
            $t->string("role")->default("customer")->index();
            $t->string("phone")->nullable();
            $t->text("address")->nullable();
            $t->string("city")->nullable();
            $t->string("pincode")->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::table("orders", function (Blueprint $t) {
            $t->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
        });
        Schema::table("promotions", function (Blueprint $t) {
            $t->boolean("show_banner")->default(true);
            $t->string("banner_text", 180)->nullable();
            $t->string("banner_mode")->default("both");
        });
        $hash = config("store.admin_password_hash");
        if ($hash) {
            DB::table("users")->insert([
                "name" => "Store owner",
                "email" => strtolower(env("ADMIN_EMAIL", "owner@avelle.local")),
                "password" => $hash,
                "role" => "admin",
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }
    }
    public function down(): void
    {
        Schema::table("orders", function (Blueprint $t) {
            $t->dropConstrainedForeignId("user_id");
        });
        Schema::dropIfExists("users");
        Schema::table("promotions", function (Blueprint $t) {
            $t->dropColumn(["show_banner", "banner_text", "banner_mode"]);
        });
    }
};
