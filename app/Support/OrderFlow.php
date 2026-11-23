<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class OrderFlow
{
    public static function cancel(int $id, string $note = "Cancelled"): void
    {
        DB::transaction(function () use ($id, $note) {
            $o = DB::table("orders")->where("id", $id)->lockForUpdate()->first();
            abort_unless($o, 404);
            if ($o->status === "cancelled") {
                return;
            }
            if (!in_array($o->status, ["placed", "confirmed"])) {
                throw ValidationException::withMessages([
                    "order" => "This order has already been dispatched and cannot be cancelled.",
                ]);
            }
            if (
                DB::table("seller_orders")
                    ->where("order_id", $id)
                    ->whereIn("status", ["shipped", "delivered"])
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    "order" =>
                        "A shipment has already been dispatched. Request a return after delivery instead.",
                ]);
            }
            foreach (json_decode($o->items, true) as $i) {
                Inventory::restore($i);
            }
            if ($o->code) {
                DB::table("promotions")->where("code", $o->code)->where("used", ">", 0)->decrement("used");
            }
            DB::table("seller_orders")
                ->where("order_id", $id)
                ->update(["status" => "cancelled", "updated_at" => now()]);
            DB::table("orders")
                ->where("id", $id)
                ->update(["status" => "cancelled", "updated_at" => now()]);
            DB::table("order_events")->insert([
                "order_id" => $id,
                "status" => "cancelled",
                "note" => $note,
                "created_at" => now(),
                "updated_at" => now(),
            ]);
            DB::afterCommit(fn() => StoreMail::status($o->reference, "cancelled"));
        });
    }
}
