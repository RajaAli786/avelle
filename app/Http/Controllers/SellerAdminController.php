<?php

namespace App\Http\Controllers;

use App\Support\SellerAccess;
use App\Support\SellerOrders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerAdminController
{
    public function review(Request $r, int $id)
    {
        SellerAccess::admin();
        $d = $r->validate([
            "status" => "required|in:pending,approved,rejected,suspended",
            "commission_rate" => "required|numeric|min:0|max:100|decimal:0,2",
            "admin_note" => "nullable|string|max:3000",
        ]);
        DB::transaction(function () use ($id, $d) {
            $s = DB::table("sellers")->where("id", $id)->lockForUpdate()->first();
            abort_unless($s, 404);
            if (
                $d["status"] === "approved" &&
                !DB::table("users")->where("id", $s->user_id)->where("is_active", true)->exists()
            ) {
                throw ValidationException::withMessages([
                    "seller" => "Enable the user account before approving this seller.",
                ]);
            }
            DB::table("sellers")
                ->where("id", $id)
                ->update($d + ["updated_at" => now()]);
        });
        return redirect(url("/admin") . "#sellers")->with(
            "success",
            "Seller status and commission updated. Existing order commission snapshots stay unchanged.",
        );
    }
    public function product(Request $r, int $id)
    {
        SellerAccess::admin();
        $d = $r->validate([
            "seller_status" => "required|in:pending,approved,rejected",
            "moderation_note" => "nullable|string|max:3000",
        ]);
        abort_unless(DB::table("products")->where("id", $id)->whereNotNull("seller_id")->exists(), 404);
        DB::table("products")
            ->where("id", $id)
            ->update($d + ["updated_at" => now()]);
        return redirect(url("/admin") . "#sellers")->with("success", "Seller product moderation saved.");
    }
    public function shipment(Request $r, int $id)
    {
        SellerAccess::admin();
        $d = $r->validate([
            "status" => "required|in:placed,confirmed,shipped,delivered",
            "carrier" => "nullable|string|max:100",
            "tracking_number" => "nullable|string|max:150",
            "tracking_url" => "nullable|url:http,https|max:2048",
        ]);
        SellerOrders::change($id, $d);
        return redirect(url("/admin") . "#seller-orders")->with("success", "Shipment updated.");
    }
    public function payout(Request $r, int $id)
    {
        SellerAccess::admin();
        $d = $r->validate([
            "payout_reference" => "required|string|max:150",
            "payout_note" => "nullable|string|max:2000",
        ]);
        SellerOrders::settle($id, $d["payout_reference"], $d["payout_note"] ?? null);
        return redirect(url("/admin") . "#seller-orders")->with(
            "success",
            "External payout recorded. No money was transferred by this form.",
        );
    }
}
