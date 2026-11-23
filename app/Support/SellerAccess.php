<?php
namespace App\Support;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class SellerAccess
{
    public static function profile(bool $approved = false)
    {
        $u = Auth::user();
        abort_unless($u && $u->is_active, 403);
        $seller = DB::table("sellers")->where("user_id", $u->id)->first();
        abort_unless($seller, 403);
        if ($approved) {
            abort_unless(
                $seller->status === "approved",
                403,
                "Your seller account must be approved and active.",
            );
        }
        return $seller;
    }
    public static function admin(): void
    {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === "admin", 403);
    }
    public static function product(int $id)
    {
        $s = self::profile(true);
        $p = DB::table("products")->find($id);
        abort_unless($p && (int) $p->seller_id === (int) $s->id, 403);
        return $p;
    }
}
