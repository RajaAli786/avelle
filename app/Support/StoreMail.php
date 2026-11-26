<?php
namespace App\Support;
use App\Mail\StoreMessage;
use Illuminate\Support\Facades\DB;
class StoreMail
{
    public static function send(string $email, StoreMessage $message): bool
    {
        return MailDelivery::send($email, $message);
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
                "Order placed",
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

    public static function shipment(int $id): void
    {
        $shipment = DB::table('seller_orders')->find($id);
        $order = $shipment ? DB::table('orders')->find($shipment->order_id) : null;
        if (!$order) {
            return;
        }
        $lines = ['Hi ' . $order->name . ', shipment #' . $id . ' is ' . $shipment->status . '.', 'Items in this shipment:'];
        foreach (json_decode($shipment->items, true) ?: [] as $item) {
            $lines[] = $item['name'] . ' · ' . ($item['size'] ?? '') . ' × ' . $item['quantity'];
        }
        if ($shipment->tracking_number) {
            $lines[] = 'Tracking: ' . $shipment->carrier . ' ' . $shipment->tracking_number;
        }
        $lines[] = 'The complete order and all its items are shown below.';
        self::send($order->email, new StoreMessage(
            'Shipment ' . $shipment->status, implode("\n", $lines),
            url('/account/orders/' . $order->reference), 'View order', [], OrderDisplay::email($order),
        ));
    }

    public static function returnStatus(int $id): void
    {
        $return = DB::table('return_requests')->find($id);
        $order = $return ? DB::table('orders')->find($return->order_id) : null;
        if (!$order) {
            return;
        }
        $lines = ['Hi ' . $order->name . ', return #' . $id . ' is ' . $return->status . '.'];
        foreach (json_decode($return->items, true) ?: [] as $item) {
            $lines[] = $item['name'] . ' · Qty ' . $item['quantity'];
        }
        if ($return->status === 'completed') {
            $lines[] = 'Cash refund recorded: ₹' . number_format($return->refund_amount) . '.';
            $lines[] = 'Any eligible wallet share is restored separately to your Avelle wallet.';
        }
        self::send($order->email, new StoreMessage(
            'Return ' . $return->status, implode("\n", $lines),
            url('/account/orders/' . $order->reference), 'View return details', [], OrderDisplay::email($order),
        ));
    }

    public static function detailsUpdated(string $reference): void
    {
        $order = DB::table('orders')->where('reference', $reference)->first();
        if (!$order) {
            return;
        }
        self::send($order->email, new StoreMessage(
            'Order details updated',
            'Hi ' . $order->name . ', the payment or tracking details for your order have been updated. Check the latest details below.',
            url('/account/orders/' . $order->reference), 'View order', [], OrderDisplay::email($order),
        ));
    }
}
