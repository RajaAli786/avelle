<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        foreach (["products", "categories", "pages"] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string("seo_title", 150)->nullable();
                $t->string("seo_description", 320)->nullable();
                $t->string("seo_keywords", 500)->nullable();
            });
        }
    }
    public function down(): void
    {
        foreach (["products", "categories", "pages"] as $table) {
            Schema::table(
                $table,
                fn(Blueprint $t) => $t->dropColumn(["seo_title", "seo_description", "seo_keywords"]),
            );
        }
    }
};
