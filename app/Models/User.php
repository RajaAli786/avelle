<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable
{
    public function sendPasswordResetNotification($token): void
    {
        \Illuminate\Support\Facades\Mail::to($this->email)->send(
            new \App\Mail\StoreMessage(
                "Reset your password",
                "We received a password-reset request for your Avelle account. This link expires in 60 minutes. If you did not request it, you can ignore this email.",
                url("/reset-password/" . $token) . "?email=" . rawurlencode($this->email),
                "Reset password",
            ),
        );
    }
    protected $fillable = ["name", "email", "password", "phone", "address", "city", "pincode"];
    protected $attributes = ["role" => "customer", "is_active" => true, "auth_version" => 1];
    protected $hidden = ["password", "remember_token"];
    protected function casts(): array
    {
        return ["password" => "hashed", "is_active" => "boolean", "auth_version" => "integer"];
    }
}
