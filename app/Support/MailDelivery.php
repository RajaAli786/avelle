<?php

namespace App\Support;

use App\Mail\StoreMessage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailDelivery
{
    public const MAX_ATTEMPTS = 5;

    public static function send(string $email, StoreMessage $message): bool
    {
        $id = DB::table('store_mail_deliveries')->insertGetId([
            'recipient' => $email,
            'subject' => $message->heading,
            // Encrypt reset/verification links and private address data at rest.
            'payload' => Crypt::encryptString(json_encode([
                'heading' => $message->heading,
                'messageText' => $message->messageText,
                'actionUrl' => $message->actionUrl,
                'actionLabel' => $message->actionLabel,
                'lines' => $message->lines,
                'orderDetails' => $message->orderDetails,
            ], JSON_THROW_ON_ERROR)),
            'status' => 'pending',
            'next_attempt_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return self::attempt($id);
    }

    public static function attempt(int $id, bool $manual = false): bool
    {
        $token = Str::random(40);
        $row = DB::transaction(function () use ($id, $manual, $token) {
            $row = DB::table('store_mail_deliveries')->where('id', $id)->lockForUpdate()->first();
            if (!$row || !$row->payload || in_array($row->status, ['sent', 'logged']) ||
                ($row->locked_at && now()->diffInSeconds($row->locked_at, true) < 600) ||
                (!$manual && ($row->attempts >= self::MAX_ATTEMPTS ||
                    ($row->next_attempt_at && now()->lessThan($row->next_attempt_at))))) {
                return null;
            }
            DB::table('store_mail_deliveries')->where('id', $id)->update([
                'status' => 'sending', 'locked_at' => now(), 'lock_token' => $token,
                'attempts' => min(255, $row->attempts + 1), 'updated_at' => now(),
            ]);
            return $row;
        });
        if (!$row) {
            return false;
        }
        try {
            $data = json_decode(Crypt::decryptString($row->payload), true, 512, JSON_THROW_ON_ERROR);
            // Regenerate expiring links for the same active account; discard changed emails.
            $url = $data['actionUrl'] ?? '';
            if (str_contains($url, '/email/verify/')) {
                $user = \App\Models\User::where('email', $row->recipient)->where('is_active', true)->first();
                $path = parse_url($url, PHP_URL_PATH);
                preg_match('~/email/verify/(\d+)/([a-f0-9]{40})$~', $path ?? '', $parts);
                if (!$user || $user->hasVerifiedEmail() || (int) ($parts[1] ?? 0) !== (int) $user->id ||
                    !hash_equals(sha1($user->email), $parts[2] ?? '')) {
                    self::discard($id, $token, 'Verification no longer required');
                    return false;
                }
                $data['actionUrl'] = EmailVerification::url($user);
            }
            // Password reset tokens cannot be recreated: never deliver an expired reset link.
            if (str_contains($url, '/reset-password/')) {
                $resetToken = rawurldecode(basename(parse_url($url, PHP_URL_PATH) ?? ''));
                $user = \App\Models\User::where('email', $row->recipient)->where('is_active', true)->first();
                if (!$user || !\Illuminate\Support\Facades\Password::broker()->tokenExists($user, $resetToken)) {
                    self::discard($id, $token, 'Reset link expired or replaced; request a new reset email');
                    return false;
                }
            }
            Mail::to($row->recipient)->send(new StoreMessage(...$data));
            $logged = in_array(config('mail.default'), ['log', 'array']);
            DB::table('store_mail_deliveries')->where('id', $id)->where('lock_token', $token)->update([
                'status' => $logged ? 'logged' : 'sent', 'sent_at' => now(),
                'payload' => null, 'error_type' => null, 'locked_at' => null,
                'lock_token' => null, 'next_attempt_at' => null, 'updated_at' => now(),
            ]);
            return true;
        } catch (\Throwable $exception) {
            $attempt = $row->attempts + 1;
            $minutes = [1, 5, 15, 60, 240][min(4, $attempt - 1)];
            DB::table('store_mail_deliveries')->where('id', $id)->where('lock_token', $token)->update([
                'status' => $attempt >= self::MAX_ATTEMPTS ? 'failed' : 'retry',
                'error_type' => substr(get_class($exception), 0, 200),
                'next_attempt_at' => $attempt >= self::MAX_ATTEMPTS ? null : now()->addMinutes($minutes),
                'locked_at' => null, 'lock_token' => null, 'updated_at' => now(),
            ]);
            return false;
        }
    }

    private static function discard(int $id, string $token, string $reason): void
    {
        DB::table('store_mail_deliveries')->where('id', $id)->where('lock_token', $token)->update([
            'status' => 'failed', 'payload' => null, 'error_type' => $reason,
            'locked_at' => null, 'lock_token' => null, 'next_attempt_at' => null, 'updated_at' => now(),
        ]);
    }

    public static function retryDue(int $limit = 50): int
    {
        DB::table('store_mail_deliveries')->where('status', 'sending')
            ->where('attempts', '>=', self::MAX_ATTEMPTS)->where('locked_at', '<', now()->subMinutes(10))
            ->update([
                'status' => 'failed', 'locked_at' => null, 'lock_token' => null,
                'error_type' => 'Worker interrupted; check delivery before retrying', 'updated_at' => now(),
            ]);
        $ids = DB::table('store_mail_deliveries')->where('attempts', '<', self::MAX_ATTEMPTS)
            ->whereNotNull('payload')->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereIn('status', ['pending', 'retry'])->where('next_attempt_at', '<=', now());
                })->orWhere(function ($query) {
                    $query->where('status', 'sending')->where('locked_at', '<', now()->subMinutes(10));
                });
            })->orderBy('id')->limit($limit)->pluck('id');
        $sent = 0;
        foreach ($ids as $id) {
            $sent += (int) self::attempt($id);
        }
        return $sent;
    }
}
