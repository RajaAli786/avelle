<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table("promotions")->updateOrInsert(
            ["code" => "HELLO10"],
            [
                "kind" => "coupon",
                "type" => "percent",
                "value" => 10,
                "minimum" => 499,
                "maximum" => 300,
                "scope" => "store",
                "targets" => "[]",
                "active" => true,
            ],
        );
        foreach (json_decode(file_get_contents(public_path("products.json")), true) as $p) {
            $p["sizes"] = json_encode($p["sizes"]);
            DB::table("products")->updateOrInsert(["id" => $p["id"]], $p);
        }
    }
}
