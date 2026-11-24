<?php
namespace App\Http\Controllers;
use App\Support\SellerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
class SellerAccountController
{
    public function update(Request $r)
    {
        SellerAccess::profile();
        $u = Auth::user();
        $r->merge(["email" => strtolower(trim($r->email ?? ""))]);
        $rules = [
            "name" => "required|string|max:100",
            "email" => ["required", "email", "max:200", Rule::unique("users")->ignore($u->id)],
        ];
        if ($r->email !== $u->email) {
            $rules["current_password"] = "required|current_password:web";
        }
        $d = $r->validate($rules);
        $old = $u->email;
        DB::transaction(function () use ($u, $d, $old) {
            $u->name = $d["name"];
            $u->email = $d["email"];
            if ($old !== $d["email"]) {
                $u->auth_version++;
                $u->remember_token = Str::random(60);
                DB::table("password_reset_tokens")
                    ->whereIn("email", [$old, $d["email"]])
                    ->delete();
            }
            if ($old !== $u->email) {
                $u->email_verified_at = null;
            }
            $u->save();
            if ($old !== $u->email) {
                DB::afterCommit(fn () => $u->sendEmailVerificationNotification());
            }
        });
        if ($old !== $u->email) {
            $r->session()->regenerate();
        }
        $r->session()->put("auth_version", $u->auth_version);
        return redirect(url("/seller") . "#seller-account")->with(
            "success",
            "Your seller account details have been updated.",
        );
    }
    public function password(Request $r)
    {
        SellerAccess::profile();
        $d = $r->validate([
            "current_password" => "required|current_password:web",
            "password" => ["required", "confirmed", Password::min(12)],
        ]);
        $u = Auth::user();
        $u->password = $d["password"];
        $u->auth_version++;
        $u->remember_token = Str::random(60);
        $u->save();
        DB::table("password_reset_tokens")->where("email", $u->email)->delete();
        $r->session()->regenerate();
        $r->session()->put("auth_version", $u->auth_version);
        return redirect(url("/seller") . "#seller-account")->with(
            "success",
            "Password updated. Other sessions have been signed out.",
        );
    }
}
