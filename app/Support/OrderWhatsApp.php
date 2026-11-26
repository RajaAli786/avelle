<?php

namespace App\Support;

class OrderWhatsApp
{
    public static function phone(?string $phone): ?string
    {
        if (!$phone || !preg_match('/^[+0-9\s().-]+$/', $phone)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (preg_match('/^[6-9][0-9]{9}$/', $digits)) {
            return '91' . $digits;
        }
        return preg_match('/^91[6-9][0-9]{9}$/', $digits) ? $digits : null;
    }

    public static function message($order): string
    {
        $lines = [
            'Hi ' . self::oneLine($order->name) . ',',
            'Here is your Avelle order update.',
            '',
            'Order: ' . OrderDisplay::reference($order),
            'Status: ' . ucfirst($order->status),
            '',
            'Items:',
        ];
        foreach (json_decode($order->items, true) ?: [] as $index => $item) {
            $details = [];
            if (!empty($item['size'])) {
                $details[] = 'Size ' . self::oneLine($item['size']);
            }
            if (!empty($item['color'])) {
                $details[] = self::oneLine($item['color']);
            }
            $details[] = 'Qty ' . (int) $item['quantity'];
            $lines[] = ($index + 1) . '. ' . self::oneLine($item['name']);
            $lines[] = implode(' · ', $details) . ' · ₹' . number_format($item['price'] * $item['quantity']);
        }
        $lines[] = '';
        $lines[] = 'Subtotal: ₹' . number_format($order->subtotal);
        $lines[] = 'Delivery: ₹' . number_format($order->shipping);
        if ($order->discount) {
            $lines[] = 'Coupon savings: ₹' . number_format($order->discount);
        }
        if ($order->wallet_spent ?? 0) {
            $lines[] = 'Wallet credit: ₹' . number_format($order->wallet_spent);
        }
        $lines[] = 'Total: ₹' . number_format($order->total);
        $lines[] = 'Payment: ' . ($order->payment_method === 'cod' ? 'Cash on delivery' : self::oneLine(ucfirst($order->payment_method)))
            . ' · ' . ucfirst($order->payment_status ?? 'unpaid');
        if (!empty($order->carrier) || !empty($order->tracking_number)) {
            $lines[] = 'Tracking: ' . trim(self::oneLine($order->carrier ?? '') . ' ' . self::oneLine($order->tracking_number ?? ''));
        }
        $lines[] = '';
        $lines[] = 'Thank you for shopping with Avelle.';
        return implode("\n", $lines);
    }

    public static function url($order): ?string
    {
        $phone = self::phone($order->phone ?? null);
        return $phone ? 'https://wa.me/' . $phone . '?text=' . rawurlencode(self::message($order)) : null;
    }

    public static function shipmentUrl($shipment): ?string
    {
        $phone = self::phone($shipment->customer_phone ?? null);
        if (!$phone) {
            return null;
        }
        $lines = [
            'Hi ' . self::oneLine($shipment->customer_name) . ',',
            'Your Avelle shipment update.',
            'Order: ' . OrderDisplay::reference((object) ['id' => $shipment->order_id]),
            'Shipment #' . $shipment->id . ': ' . ucfirst($shipment->status),
            '',
            'Items in this shipment:',
        ];
        foreach (json_decode($shipment->items, true) ?: [] as $item) {
            $lines[] = self::oneLine($item['name']) . ' · ' . self::oneLine($item['size'] ?? '') . ' · Qty ' . (int) $item['quantity'];
        }
        if ($shipment->tracking_number) {
            $lines[] = 'Tracking: ' . self::oneLine($shipment->carrier ?? '') . ' ' . self::oneLine($shipment->tracking_number);
        }
        $lines[] = 'Please check your Avelle account for the complete order.';
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
    }

    public static function returnUrl($return): ?string
    {
        $phone = self::phone($return->customer_phone ?? null);
        if (!$phone) {
            return null;
        }
        $lines = [
            'Hi ' . self::oneLine($return->customer) . ',',
            'Your Avelle return update.',
            'Order: ' . OrderDisplay::reference((object) ['id' => $return->order_id]),
            'Return #' . $return->id . ': ' . ucfirst($return->status),
        ];
        foreach (json_decode($return->items, true) ?: [] as $item) {
            $lines[] = self::oneLine($item['name']) . ' · Qty ' . (int) $item['quantity'];
        }
        if ($return->status === 'completed') {
            $lines[] = 'Cash refund recorded: ₹' . number_format($return->refund_amount);
        }
        $lines[] = 'Please check your Avelle account for the latest details.';
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
    }

    private static function oneLine(string $text): string
    {
        return trim(preg_replace('/[\r\n\t\x00-\x1F\x7F]+/', ' ', $text));
    }
}
