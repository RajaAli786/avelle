<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class OrderDisplay
{
    public static function reference($order): string
    {
        return 'AVL-' . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);
    }

    public static function referenceId(string $reference): ?int
    {
        return preg_match('/^AVL-(\d{8,20})$/i', trim($reference), $match) ? (int) $match[1] : null;
    }

    public static function items($order): array
    {
        $items = json_decode($order->items, true) ?: [];
        $ids = array_values(array_filter(array_column($items, 'id')));
        $products = DB::table('products')->whereIn('id', $ids)->get()->keyBy('id');
        $images = DB::table('product_images')->whereIn('product_id', $ids)
            ->orderBy('position')->orderBy('id')->get()->groupBy('product_id');

        foreach ($items as &$item) {
            $id = $item['id'] ?? null;
            $product = $products->get($id);
            $path = $images->get($id)?->first()?->path;
            if (!$path && $product) {
                $path = 'images/' . $product->image . '.jpg';
            }
            $item['image_path'] = null;
            if ($path && !str_starts_with($path, 'https://')) {
                $resolved = realpath(public_path($path));
                foreach (['images', 'uploads'] as $directory) {
                    $root = realpath(public_path($directory));
                    if ($resolved && $root && str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
                        && is_file($resolved)
                        && in_array(strtolower(pathinfo($resolved, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'avif'])) {
                        $item['image_path'] = $resolved;
                    }
                }
            }
            $item['image_url'] = $item['image_path']
                ? asset(str_replace('\\', '/', ltrim(substr($item['image_path'], strlen(realpath(public_path()))), '/\\')))
                : asset('images/placeholder.svg');
            if (!$item['image_path'] && $path && str_starts_with($path, 'https://')) {
                $item['image_url'] = $path;
            }
            $item['product_url'] = $product && $product->active && $product->seller_status === 'approved'
                ? url('/products/' . ($product->slug ?: $product->id))
                : null;
        }
        unset($item);
        return $items;
    }

    public static function email($order): array
    {
        return [
            'reference' => self::reference($order),
            'status' => $order->status,
            'items' => self::items($order),
            'subtotal' => $order->subtotal,
            'shipping' => $order->shipping,
            'discount' => $order->discount,
            'wallet_spent' => $order->wallet_spent ?? 0,
            'total' => $order->total,
            'payment' => ($order->payment_method === 'cod' ? 'Cash on delivery' : ucfirst($order->payment_method))
                . ' · ' . ucfirst($order->payment_status ?? 'unpaid'),
            'name' => $order->name,
            'address' => implode(', ', array_filter([$order->address, $order->city, $order->state ?? '', $order->pincode])),
            'carrier' => $order->carrier ?? '',
            'tracking_number' => $order->tracking_number ?? '',
        ];
    }
}
