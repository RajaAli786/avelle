<?php
namespace App\Support;
use App\Mail\StoreMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class StoreMail
{
    public static function send(string $email, StoreMessage $message): bool
    {
        try {
            Mail::to($email)->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::warning("Store email failed", ["type" => get_class($e), "subject" => $message->heading]);
            return false;
        }
    }
    public static function welcome($user): void
    {
        self::send(
            $user->email,
            new StoreMessage(
                "Welcome to Avelle",
                "Hi " .
                    $user->name .
                    ", your account is ready. Keep your orders, saved finds and delivery details together.",
                url("/account"),
                "My account",
            ),
        );
    }
    public static function status(string $reference, string $status): void
    {
        $o = DB::table("orders")->where("reference", $reference)->first();
        if (!$o) {
            return;
        }
        self::send(
            $o->email,
            new StoreMessage(
                "Order " . $status,
                "Hi " . $o->name . ", your order status is now " . $status . ".",
                url("/account/orders/" . $o->reference),
                "View order",
                ["Order: " . $o->reference],
                OrderDisplay::email($o),
            ),
        );
    }
    public static function order(string $reference): void
    {
        $o = DB::table("orders")->where("reference", $reference)->first();
        if (!$o) {
            return;
        }
        $lines = ["Order reference: " . $o->reference];
        foreach (json_decode($o->items) as $i) {
            $lines[] = $i->name . " · " . $i->size . " × " . $i->quantity . " — ₹" . $i->price * $i->quantity;
        }
        $lines[] = "Total: ₹" . $o->total . " · Cash on delivery";
        self::send(
            $o->email,
            new StoreMessage(
                "Order confirmed",
                "Hi " .
                    $o->name .
                    ", we have received your order. You can view its latest status in your account.",
                url("/account/orders/" . $o->reference),
                "View order",
                $lines,
                OrderDisplay::email($o),
            ),
        );
        if (config("mail.admin_address")) {
            self::send(
                config("mail.admin_address"),
                new StoreMessage(
                    "New store order",
                    "A new order has been placed. Sign in to the admin panel to manage it.",
                    url("/admin") . "#orders",
                    "Manage orders",
                    ["Order: " . $o->reference, "Total: ₹" . $o->total],
                ),
            );
        }
    }
}
