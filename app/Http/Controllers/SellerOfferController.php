<?php

namespace App\Http\Controllers;

use App\Support\SellerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerOfferController
{
    public function save(Request $r)
    {
        $s = SellerAccess::profile(true);
        $existing = null;
        if ($r->filled("id")) {
            $existing = DB::table("promotions")
                ->where("id", (int) $r->id)
                ->where("seller_id", $s->id)
                ->first();
            abort_unless($existing, 403);
        }
        $r->merge(["code" => strtoupper(trim($r->code ?? ""))]);
        $d = $r->validate([
            "id" => "nullable|integer",
            "code" => [
                "required",
                'regex:/^[A-Z0-9_-]{3,40}$/',
                Rule::unique("promotions")->ignore($existing?->id),
            ],
            "type" => "required|in:percent,fixed",
            "value" => "required|integer|min:1|max:1000000",
            "minimum" => "required|integer|min:0|max:1000000",
            "maximum" => "nullable|integer|min:1|max:1000000",
            "scope" => "required|in:store,category,product",
            "targets" => "nullable|required_unless:scope,store|string|max:1000",
            "usage_limit" => "nullable|integer|min:1|max:1000000",
            "expires_at" => "nullable|date",
            "active" => "nullable|boolean",
            "show_banner" => "nullable|boolean",
            "banner_text" => "nullable|string|max:180",
            "banner_mode" => "required|in:both,marquee,pulse,static",
        ]);
        if ($d["type"] === "percent" && $d["value"] > 100) {
            throw ValidationException::withMessages(["value" => "Percentage cannot exceed 100."]);
        }
        if ($existing && $existing->used > 0 && $d["code"] !== $existing->code) {
            throw ValidationException::withMessages([
                "code" => "A used coupon code cannot be renamed. Pause it and create another code.",
            ]);
        }
        $targets = array_values(
            array_unique(array_filter(array_map("trim", explode(",", $d["targets"] ?? "")))),
        );
        if ($d["scope"] === "product") {
            if (
                count($targets) !==
                DB::table("products")
                ->where("seller_id", $s->id)
                ->whereIn("id", array_filter($targets, "ctype_digit"))
                ->count()
            ) {
                throw ValidationException::withMessages([
                    "targets" => "Choose only product IDs belonging to your shop.",
                ]);
            }
        } elseif ($d["scope"] === "category") {
            if (
                count($targets) !==
                DB::table("products")
                ->where("seller_id", $s->id)
                ->whereIn("category", $targets)
                ->distinct()
                ->count("category")
            ) {
                throw ValidationException::withMessages([
                    "targets" => "Choose categories used by your own products.",
                ]);
            }
        } else {
            $targets = [];
        }
        $values = collect($d)
            ->except(["id", "targets", "active", "show_banner"])
            ->all() + [
                "targets" => json_encode($targets),
                "active" => $r->boolean("active"),
                "show_banner" => $r->boolean("show_banner"),
                "kind" => "coupon",
                "owner_email" => null,
                "seller_id" => $s->id,
                "updated_at" => now(),
            ];
        DB::transaction(function () use ($existing, $values) {
            if ($existing) {
                $current = DB::table("promotions")->where("id", $existing->id)->lockForUpdate()->first();
                if ($current->admin_locked && $values["active"]) {
                    throw ValidationException::withMessages([
                        "offer" => "Admin paused this offer. Ask admin to unlock it before activation.",
                    ]);
                }
                if ($current->used > 0 && $current->code !== $values["code"]) {
                    throw ValidationException::withMessages([
                        "code" => "A used coupon code cannot be renamed.",
                    ]);
                }
                DB::table("promotions")->where("id", $current->id)->update($values);
            } else {
                DB::table("promotions")->insert($values + ["created_at" => now(), "admin_locked" => false]);
            }
        });
        return redirect(url("/seller") . "#seller-offers")->with(
            "success",
            "Seller offer saved. It applies only to your shop’s eligible products.",
        );
    }
    public function toggle(int $id)
    {
        $s = SellerAccess::profile(true);
        DB::transaction(function () use ($id, $s) {
            $p = DB::table("promotions")
                ->where("id", $id)
                ->where("seller_id", $s->id)
                ->lockForUpdate()
                ->first();
            abort_unless($p, 403);
            if ($p->admin_locked) {
                throw ValidationException::withMessages(["offer" => "This coupon is locked by admin."]);
            }
            DB::table("promotions")
                ->where("id", $id)
                ->update(["active" => !$p->active, "updated_at" => now()]);
        });
        return redirect(url("/seller") . "#seller-offers")->with("success", "Offer visibility updated.");
    }
}
