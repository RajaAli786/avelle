<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SellerOrders
{
    public static function split(int $orderId, array $items, int $discount, $promo = null): void
    {
        $groups = [];
        foreach ($items as $line) {
            $key = (string) ($line["seller_id"] ?? 0);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    "seller_id" => $line["seller_id"] ?? null,
                    "items" => [],
                    "subtotal" => 0,
                    "eligible" => 0,
                ];
            }
            $amount = $line["price"] * $line["quantity"];
            $groups[$key]["items"][] = $line;
            $groups[$key]["subtotal"] += $amount;
            $targets = $promo ? json_decode($promo->targets ?? "[]", true) : [];
            if (!$promo || StoreOffers::matches($line, $promo)) {
                $groups[$key]["eligible"] += $amount;
            }
        }
        $eligible = array_sum(array_column($groups, "eligible"));
        $allocated = 0;
        $remainders = [];
        foreach ($groups as $key => &$g) {
            $g["discount"] = $eligible ? intdiv($discount * $g["eligible"], $eligible) : 0;
            $allocated += $g["discount"];
            $remainders[$key] = $eligible ? ($discount * $g["eligible"]) % $eligible : 0;
        }
        unset($g);
        arsort($remainders);
        foreach (array_keys($remainders) as $key) {
            if ($allocated >= $discount) {
                break;
            }
            $groups[$key]["discount"]++;
            $allocated++;
        }
        foreach ($groups as $g) {
            $seller = $g["seller_id"] ? DB::table("sellers")->find($g["seller_id"]) : null;
            $rate = $seller ? (float) $seller->commission_rate : 0;
            $net = $g["subtotal"] - $g["discount"];
            $commission = (int) round(($net * $rate) / 100, 0, PHP_ROUND_HALF_UP);
            DB::table("seller_orders")->insert([
                "order_id" => $orderId,
                "seller_id" => $g["seller_id"],
                "fulfilment_key" => $orderId . "-" . ($g["seller_id"] ?? "store"),
                "items" => json_encode($g["items"]),
                "subtotal" => $g["subtotal"],
                "discount" => $g["discount"],
                "net_amount" => $net,
                "commission_rate" => $rate,
                "commission_amount" => $commission,
                "seller_amount" => $net - $commission,
                "status" => "placed",
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }
    }
    public static function change(int $id, array $data, ?int $sellerId = null): void
    {
        DB::transaction(function () use ($id, $data, $sellerId) {
            $hint = DB::table("seller_orders")->find($id);
            abort_unless($hint && ($sellerId === null || (int) $hint->seller_id === $sellerId), 403);
            $order = DB::table("orders")->where("id", $hint->order_id)->lockForUpdate()->first();
            $row = DB::table("seller_orders")->where("id", $id)->lockForUpdate()->first();
            abort_unless($order && $order->status !== "cancelled", 422);
            $rank = ["placed" => 0, "confirmed" => 1, "shipped" => 2, "delivered" => 3];
            if (
                $row->status === "cancelled" ||
                !isset($rank[$data["status"]]) ||
                $rank[$data["status"]] < $rank[$row->status] ||
                $rank[$data["status"]] > $rank[$row->status] + 1
            ) {
                throw ValidationException::withMessages([
                    "status" => "Move the shipment forward one step at a time.",
                ]);
            }
            if (
                $data["status"] === "shipped" &&
                (empty($data["carrier"]) || empty($data["tracking_number"]))
            ) {
                throw ValidationException::withMessages([
                    "tracking" => "Add carrier and tracking number before dispatch.",
                ]);
            }
            $values = collect($data)
                ->only(["status", "carrier", "tracking_number", "tracking_url"])
                ->all();
            $values["updated_at"] = now();
            if ($data["status"] === "delivered" && !$row->delivered_at) {
                $values["delivered_at"] = now();
            }
            DB::table("seller_orders")->where("id", $id)->update($values);
            $rows = DB::table("seller_orders")->where("order_id", $order->id)->get();
            $active = $rows->where("status", "!=", "cancelled");
            $status = $active->sortBy(fn($r) => $rank[$r->status])->first()?->status ?? "cancelled";
            $parent = ["status" => $status, "updated_at" => now()];
            if ($status === "delivered" && !$order->delivered_at) {
                $parent["delivered_at"] = now();
            }
            DB::table("orders")->where("id", $order->id)->update($parent);
            ReferralWallet::sync($order->id);
            if ($data["status"] !== $row->status) {
                DB::table("order_events")->insert([
                    "order_id" => $order->id,
                    "status" => $status,
                    "note" => "Shipment #" . $id . " is " . $data["status"],
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }
            if ($status !== $order->status) {
                DB::afterCommit(fn() => StoreMail::status($order->reference, $status));
            }
        });
    }
    public static function settle(int $id, string $reference, ?string $note = null): void
    {
        DB::transaction(function () use ($id, $reference, $note) {
            $hint = DB::table("seller_orders")->find($id);
            abort_unless($hint && $hint->seller_id, 404);
            $order = DB::table("orders")->where("id", $hint->order_id)->lockForUpdate()->first();
            $row = DB::table("seller_orders")->where("id", $id)->lockForUpdate()->first();
            if ($row->settled_at) {
                throw ValidationException::withMessages([
                    "payout" => "This shipment already has a payout record.",
                ]);
            }
            if (
                $row->status !== "delivered" ||
                $order->status !== "delivered" ||
                $order->payment_status !== "paid"
            ) {
                throw ValidationException::withMessages([
                    "payout" => "Complete delivery and record collected payment first.",
                ]);
            }
            $days = (int) Shop::settings()["return_days"];
            if (
                !$order->delivered_at ||
                now()->lessThanOrEqualTo(
                    \Illuminate\Support\Carbon::parse($order->delivered_at)->addDays($days),
                )
            ) {
                throw ValidationException::withMessages([
                    "payout" => "Wait until the return window has ended.",
                ]);
            }
            if (
                DB::table("return_requests")
                    ->where("order_id", $order->id)
                    ->where("status", "!=", "rejected")
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    "payout" => "This order has a return. Adjusted settlements need separate reconciliation.",
                ]);
            }
            DB::table("seller_orders")
                ->where("id", $id)
                ->update([
                    "settled_at" => now(),
                    "payout_reference" => $reference,
                    "payout_note" => $note,
                    "updated_at" => now(),
                ]);
        });
    }
}
