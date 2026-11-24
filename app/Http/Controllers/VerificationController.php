<?php

namespace App\Http\Controllers;

use App\Support\AuthReturn;
use App\Support\EmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationController
{
    public function notice(Request $request)
    {
        if (!Auth::check()) {
            return AuthReturn::login($request);
        }
        if (Auth::user()->hasVerifiedEmail()) {
            return AuthReturn::finish($request);
        }
        return view('market.verify-email');
    }

    public function resend(Request $request)
    {
        abort_unless(Auth::check(), 403);
        EmailVerification::send(Auth::user());
        return back()->with('success', 'Verification email requested. Check your inbox and spam folder. Please wait a minute before requesting another link.');
    }

    public function verify(Request $request, int $id, string $hash)
    {
        abort_unless($request->hasValidSignature(), 403);
        // The route's signed middleware rejects modified and expired links before login.
        if (!Auth::check()) {
            return AuthReturn::login($request);
        }
        $user = Auth::user();
        abort_unless((int) $user->id === $id && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }
        return AuthReturn::finish($request)->with('success', 'Email verified. You are ready to shop.');
    }
}
