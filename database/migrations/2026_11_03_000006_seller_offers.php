<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table("promotions", function (Blueprint $t) {
            $t->foreignId("seller_id")->nullable()->constrained()->restrictOnDelete();
            $t->boolean("admin_locked")->default(false);
        });
    }
    public function down(): void
    {
        Schema::table("promotions", function (Blueprint $t) {
            $t->dropConstrainedForeignId("seller_id");
            $t->dropColumn("admin_locked");
        });
    }
};
