<?php

namespace App\Http\Controllers;

use App\Support\Shop;
use App\Support\OrderFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrdersController
{
    private function own(string $reference)
    {
        abort_unless(Auth::check(), 403);
        $o = DB::table("orders")->where("reference", $reference)->where("user_id", Auth::id())->first();
        abort_unless($o, 404);
        return $o;
    }
    public function show(string $reference)
    {
        if (!Auth::check()) {
            return redirect(url("/account/login"));
        }
        $o = $this->own($reference);
        return view("market.order", [
            "order" => $o,
            "shipments" => DB::table("seller_orders")->where("order_id", $o->id)->get(),
            "events" => DB::table("order_events")->where("order_id", $o->id)->orderBy("id")->get(),
            "returns" => DB::table("return_requests")->where("order_id", $o->id)->latest()->get(),
        ]);
    }
    public function receipt(string $reference)
    {
        $o = $this->own($reference);
        return view("market.receipt", ["order" => $o, "settings" => Shop::settings()]);
    }
    public function cancel(string $reference)
    {
        $o = $this->own($reference);
        OrderFlow::cancel($o->id, "Cancelled by customer");
        return back()->with(
            "success",
            "Your order was cancelled. If a payment was already collected, contact support for the refund.",
        );
    }
    public function requestReturn(Request $r, string $reference)
    {
        $o = $this->own($reference);
        $d = $r->validate([
            "reason" => "required|in:Size or fit,Item damaged,Wrong item,Quality issue,Other",
            "notes" => "required|string|min:10|max:2000",
            "quantities" => "required|array",
            "quantities.*" => "nullable|integer|min:0|max:10",
        ]);
        DB::transaction(function () use ($o, $d) {
            $locked = DB::table("orders")->where("id", $o->id)->lockForUpdate()->first();
            $delivered = $locked->delivered_at ?? $locked->updated_at;
            $days = (int) Shop::settings()["return_days"];
            if (
                $locked->status !== "delivered" ||
                now()->greaterThan(\Illuminate\Support\Carbon::parse($delivered)->addDays($days))
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "return" => "This order is outside the return window.",
                ]);
            }
            $lines = json_decode($locked->items, true);
            $previous = DB::table("return_requests")
                ->where("order_id", $locked->id)
                ->whereNotIn("status", ["rejected"])
                ->get();
            $used = [];
            foreach ($previous as $p) {
                foreach (json_decode($p->items, true) as $item) {
                    $used[$item["line"]] = ($used[$item["line"]] ?? 0) + $item["quantity"];
                };
            }
            $selected = [];
            foreach ($d["quantities"] as $line => $qty) {
                $qty = (int) $qty;
                if (!$qty) {
                    continue;
                }
                if (
                    !ctype_digit((string) $line) ||
                    !isset($lines[$line]) ||
                    $qty + ($used[$line] ?? 0) > $lines[$line]["quantity"]
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "return" => "Return quantities exceed eligible purchased quantities.",
                    ]);
                }
                $selected[] = array_merge($lines[$line], ["line" => (int) $line, "quantity" => $qty]);
            }
            if (!$selected) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "return" => "Select at least one item to return.",
                ]);
            }
            DB::table("return_requests")->insert([
                "order_id" => $locked->id,
                "user_id" => Auth::id(),
                "items" => json_encode($selected),
                "reason" => $d["reason"],
                "notes" => $d["notes"],
                "status" => "requested",
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        });
        return back()->with("success", "Return request saved. You can follow its status on this order page.");
    }
    public function tracking()
    {
        return view("market.tracking", ["result" => null]);
    }
    public function track(Request $r)
    {
        $d = $r->validate(["reference" => "required|uuid", "email" => "required|email|max:200"]);
        $o = DB::table("orders")
            ->where("reference", $d["reference"])
            ->whereRaw("lower(email)=?", [strtolower($d["email"])])
            ->first();
        if (!$o) {
            return back()
                ->withErrors(["tracking" => "We could not find an order matching those details."])
                ->withInput();
        }
        return view("market.tracking", [
            "result" => collect($o)
                ->only(["reference", "status", "created_at", "carrier", "tracking_number", "tracking_url"])
                ->all(),
        ]);
    }
}
