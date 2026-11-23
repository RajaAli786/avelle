<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        foreach (require database_path("demo-pages.php") as $slug => $body) {
            DB::table("pages")
                ->where("slug", $slug)
                ->where(
                    "body",
                    "The store owner is preparing this information. Please check back before placing an order.",
                )
                ->update(["body" => $body, "updated_at" => now()]);
        }
    }
    public function down(): void
    {
        /* Preserve owner edits and content on rollback. */
    }
};
