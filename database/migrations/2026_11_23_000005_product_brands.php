<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
        });

        // Preserve existing brand names and connect all older products to the master list.
        $names = DB::table('products')->whereNotNull('brand')->distinct()->pluck('brand');
        foreach ($names as $original) {
            $name = trim($original);
            if ($name === '') {
                continue;
            }
            $brand = DB::table('brands')->where('name', $name)->first();
            if (!$brand) {
                $base = Str::slug($name) ?: 'brand';
                $slug = $base;
                $suffix = 2;
                while (DB::table('brands')->where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $suffix++;
                }
                $id = DB::table('brands')->insertGetId([
                    'name' => $name, 'slug' => $slug, 'active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $brand = DB::table('brands')->find($id);
            }
            DB::table('products')->where('brand', $original)->update(['brand_id' => $brand->id, 'brand' => $brand->name]);
        }
    }

    public function down(): void
    {
        // The existing text label stays intact for backwards compatibility.
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('brand_id'));
        Schema::dropIfExists('brands');
    }
};
