<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralWallet
{
    public static function settings(): array
    {
        return array_merge([
            'referral_enabled' => '0',
            'referral_reward_type' => 'fixed',
            'referral_reward_value' => '50',
            'referral_reward_max' => '100',
            'referral_minimum' => '499',
            'referral_friend_discount' => '50',
            'wallet_enabled' => '1',
        ], Shop::settings());
    }

    public static function account(int $userId)
    {
        // insertOrIgnore plus a unique user key handles simultaneous first visits.
        DB::table('wallet_accounts')->insertOrIgnore([
            'user_id' => $userId, 'balance' => 0,
            'referral_code' => 'AV' . strtoupper(Str::random(12)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('wallet_accounts')->where('user_id', $userId)->firstOrFail();
    }

    public static function visible(User $user): bool
    {
        if ($user->role !== 'customer') {
            return false;
        }
        return (bool) self::settings()['referral_enabled'] ||
            DB::table('wallet_entries')->where('user_id', $user->id)->exists() ||
            DB::table('referral_rewards')->where('referrer_id', $user->id)->exists();
    }

    public static function referral(string $code, ?User $customer, bool $checkFirst = true): ?array
    {
        $wallet = DB::table('wallet_accounts')->where('referral_code', strtoupper(trim($code)))->first();
        if (!$wallet) {
            return null;
        }
        $settings = self::settings();
        $owner = User::find($wallet->user_id);
        if (!$settings['referral_enabled'] || !$owner?->is_active || !$owner->hasVerifiedEmail() || $owner->role !== 'customer') {
            throw ValidationException::withMessages(['code' => 'This referral code is unavailable.']);
        }
        if ($customer) {
            if ($customer->role !== 'customer') {
                throw ValidationException::withMessages(['code' => 'Referral offers are for customer accounts.']);
            }
            if ((int) $customer->id === (int) $wallet->user_id) {
                throw ValidationException::withMessages(['code' => 'You cannot use your own referral code.']);
            }
            if ($checkFirst && DB::table('orders')->where(function ($query) use ($customer) {
                $query->where('user_id', $customer->id)->orWhere('email', $customer->email);
            })->exists()) {
                throw ValidationException::withMessages(['code' => 'Referral offers are for first orders only.']);
            }
        }
        return ['owner' => $owner, 'code' => $wallet->referral_code, 'settings' => $settings];
    }

    public static function capture(int $orderId, $promo, array $items, int $subtotal, int $discount): void
    {
        if (!$promo || $promo->kind !== 'referral') {
            return;
        }
        $order = DB::table('orders')->find($orderId);
        if (User::find($order->user_id)?->role !== 'customer') {
            return;
        }
        $owner = User::where('email', $promo->owner_email)->where('is_active', true)->where('role', 'customer')->first();
        $settings = self::settings();
        if (!$settings['referral_enabled'] || !$owner?->hasVerifiedEmail() || (int) $owner->id === (int) $order->user_id) {
            return;
        }
        $eligible = 0;
        foreach ($items as $item) {
            if (StoreOffers::matches($item, $promo)) {
                $eligible += $item['price'] * $item['quantity'];
            }
        }
        $net = max(0, $eligible - $discount);
        if ($net < (int) $settings['referral_minimum']) {
            return;
        }
        $value = (int) $settings['referral_reward_value'];
        $amount = $settings['referral_reward_type'] === 'percent' ? intdiv($net * $value, 100) : $value;
        $amount = min($amount, (int) $settings['referral_reward_max'], $net);
        if ($amount < 1) {
            return;
        }
        DB::table('referral_rewards')->insert([
            'order_id' => $orderId, 'referrer_id' => $owner->id, 'customer_id' => $order->user_id,
            'code' => $promo->code, 'eligible_amount' => $net,
            'reward_type' => $settings['referral_reward_type'], 'reward_value' => $value,
            'amount' => $amount, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function entry(int $userId, int $amount, string $key, string $kind, string $note, ?int $orderId = null): void
    {
        DB::transaction(function () use ($userId, $amount, $key, $kind, $note, $orderId) {
        // All mutations lock the account. Unique entry keys make retries idempotent.
        self::account($userId);
        $wallet = DB::table('wallet_accounts')->where('user_id', $userId)->lockForUpdate()->first();
        if (DB::table('wallet_entries')->where('entry_key', $key)->exists()) {
            return;
        }
        $balance = $wallet->balance + $amount;
        DB::table('wallet_entries')->insert([
            'user_id' => $userId, 'order_id' => $orderId, 'entry_key' => $key,
            'kind' => $kind, 'amount' => $amount, 'balance_after' => $balance,
            'note' => $note, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('wallet_accounts')->where('user_id', $userId)->update(['balance' => $balance, 'updated_at' => now()]);
        });
    }

    public static function spend(int $userId, int $orderId, int $limit): int
    {
        if (!self::settings()['wallet_enabled']) {
            throw ValidationException::withMessages(['use_wallet' => 'Wallet redemption is temporarily unavailable.']);
        }
        self::account($userId);
        $wallet = DB::table('wallet_accounts')->where('user_id', $userId)->lockForUpdate()->first();
        $amount = min(max(0, (int) $wallet->balance), max(0, $limit));
        if ($amount) {
            self::entry($userId, -$amount, 'order-spend:' . $orderId, 'purchase', 'Used on ' . OrderDisplay::reference((object) ['id' => $orderId]), $orderId);
        }
        return $amount;
    }

    public static function withWalletShares(array $items, int $discount, $promo, int $walletSpent): array
    {
        $eligible = [];
        foreach ($items as $index => $item) {
            $eligible[$index] = !$promo || StoreOffers::matches($item, $promo)
                ? $item['price'] * $item['quantity'] : 0;
        }
        $discounts = self::allocate($discount, $eligible);
        $net = [];
        foreach ($items as $index => $item) {
            $net[$index] = max(0, $item['price'] * $item['quantity'] - ($discounts[$index] ?? 0));
        }
        $shares = self::allocate($walletSpent, $net);
        foreach ($items as $index => &$item) {
            $item['wallet_share'] = $shares[$index] ?? 0;
        }
        unset($item);
        return $items;
    }

    private static function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        $shares = $remainders = [];
        foreach ($weights as $index => $weight) {
            $shares[$index] = $total ? intdiv($amount * $weight, $total) : 0;
            $remainders[$index] = $total ? ($amount * $weight) % $total : 0;
        }
        $left = $amount - array_sum($shares);
        arsort($remainders);
        foreach ($remainders as $index => $remainder) {
            if ($left < 1 || !$weights[$index]) {
                continue;
            }
            $shares[$index]++;
            $left--;
        }
        return $shares;
    }

    public static function sync(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                return;
            }
            $reward = DB::table('referral_rewards')->where('order_id', $orderId)->lockForUpdate()->first();
            $returns = DB::table('return_requests')->where('order_id', $orderId)->where('status', '!=', 'rejected')->get();
            if ($reward) {
                $void = $order->status === 'cancelled' || $returns->contains(fn ($row) => $row->status === 'completed');
                $owner = User::find($reward->referrer_id);
                $available = $order->delivered_at
                    ? \Illuminate\Support\Carbon::parse($order->delivered_at)->addDays((int) Shop::settings()['return_days'])
                    : null;
                $ready = !$void && $returns->isEmpty() && $order->status === 'delivered' &&
                    $order->payment_status === 'paid' && $available && now()->greaterThan($available) &&
                    $owner?->is_active && $owner->hasVerifiedEmail();
                $status = $reward->status;
                if ($void && $status !== 'reversed') {
                    if ($status === 'credited') {
                        self::entry($reward->referrer_id, -$reward->amount, 'reward-reverse:' . $reward->id,
                            'reversal', 'Referral reversed after cancellation/return', $orderId);
                    }
                    $status = 'reversed';
                } elseif ($ready && $status === 'pending') {
                    self::entry($reward->referrer_id, $reward->amount, 'reward-credit:' . $reward->id,
                        'referral', 'Referral reward · ' . $reward->code, $orderId);
                    $status = 'credited';
                }
                DB::table('referral_rewards')->where('id', $reward->id)->update([
                    'status' => $status, 'available_at' => $available,
                    'credited_at' => $status === 'credited' ? ($reward->credited_at ?? now()) : $reward->credited_at,
                    'updated_at' => now(),
                ]);
            }
            if ($order->wallet_spent && $order->user_id) {
                if ($order->status === 'cancelled') {
                    self::entry($order->user_id, $order->wallet_spent, 'order-restore:' . $orderId,
                        'refund', 'Wallet restored after cancellation', $orderId);
                } else {
                    $merchandise = max(1, (int) $order->subtotal);
                    $restored = (int) DB::table('wallet_entries')->where('order_id', $orderId)
                        ->where('kind', 'refund')->where('user_id', $order->user_id)->sum('amount');
                    $returnedValue = 0;
                    $returnedQuantities = [];
                    $originalItems = json_decode($order->items, true);
                    $hasShares = collect($originalItems)->every(fn ($item) => array_key_exists('wallet_share', $item));
                    foreach ($returns->where('status', 'completed')->sortBy('id') as $return) {
                        foreach (json_decode($return->items, true) as $item) {
                            $returnedValue += $item['price'] * $item['quantity'];
                            $line = $item['line'] ?? 0;
                            $returnedQuantities[$line] = ($returnedQuantities[$line] ?? 0) + $item['quantity'];
                        }
                        $key = 'return-restore:' . $return->id;
                        if (DB::table('wallet_entries')->where('entry_key', $key)->exists()) {
                            continue;
                        }
                        $target = $hasShares ? 0 : (int) floor($order->wallet_spent * min($returnedValue, $merchandise) / $merchandise);
                        if ($hasShares) {
                            foreach ($originalItems as $line => $item) {
                                $quantity = min($item['quantity'], $returnedQuantities[$line] ?? 0);
                                $target += intdiv($item['wallet_share'] * $quantity, max(1, $item['quantity']));
                            }
                        }
                        $amount = max(0, min($order->wallet_spent, $target) - $restored);
                        if ($amount) {
                            self::entry($order->user_id, $amount, $key, 'refund', 'Wallet share restored for return #' . $return->id, $orderId);
                            $restored += $amount;
                        }
                    }
                }
            }
        });
    }

    public static function processDue(): int
    {
        $count = 0;
        DB::table('referral_rewards')->where('status', 'pending')->orderBy('id')->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                self::sync($row->order_id);
                $count++;
            }
        });
        return $count;
    }
}
