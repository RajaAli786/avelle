<?php
namespace App\Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class MediaUpload
{
    public static function add(Request $r, string $kind, int $id): void
    {
        $r->validate([
            "images" => "nullable|array|max:10",
            "images.*" => "file|image|mimes:jpg,jpeg,png,webp,avif|max:5120",
        ]);
        $table = $kind === "product" ? "product_images" : "category_images";
        $key = $kind . "_id";
        $files = $r->file("images", []);
        if (!$files) {
            return;
        }
        if (DB::table($table)->where($key, $id)->count() + count($files) > 20) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "images" => "Maximum 20 images per item.",
            ]);
        }
        $dir = "uploads/" . $kind;
        if (!is_dir(public_path($dir))) {
            mkdir(public_path($dir), 0775, true);
        }
        $position = (int) DB::table($table)->where($key, $id)->max("position") + 1;
        foreach ($files as $file) {
            $name = Str::uuid() . "." . $file->extension();
            $file->move(public_path($dir), $name);
            DB::table($table)->insert([
                $key => $id,
                "path" => $dir . "/" . $name,
                "position" => $position++,
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }
    }
}
