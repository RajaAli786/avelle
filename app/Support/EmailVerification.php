<?php

namespace App\Support;

use App\Mail\StoreMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class EmailVerification
{
    public static function url(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    public static function send(User $user): bool
    {
        if (!$user->is_active || $user->hasVerifiedEmail()) {
            return false;
        }
        // Also limits automatic resends outside the explicitly throttled endpoint.
        $key = 'verification-mail:' . $user->id . ':' . sha1($user->email);
        if (!Cache::add($key, true, 60)) {
            return false;
        }
        return StoreMail::send($user->email, new StoreMessage(
            'Verify your email address',
            'Hi ' . $user->name . ', confirm your email to place orders and activate referral rewards. This secure link expires in 60 minutes. If you did not create this account, ignore this email.',
            self::url($user), 'Verify my email',
        ));
    }
}
