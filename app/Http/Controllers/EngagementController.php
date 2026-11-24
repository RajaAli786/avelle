<?php

namespace App\Http\Controllers;

use App\Support\MailDelivery;
use App\Support\ReferralWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EngagementController
{
    private function admin(): void
    {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === 'admin', 403);
    }

    public function settings(Request $request)
    {
        $this->admin();
        $data = $request->validate([
            'referral_enabled' => 'required|boolean',
            'wallet_enabled' => 'required|boolean',
            'referral_reward_type' => 'required|in:fixed,percent',
            'referral_reward_value' => 'required|integer|min:0|max:10000',
            'referral_reward_max' => 'required|integer|min:0|max:10000',
            'referral_minimum' => 'required|integer|min:1|max:1000000',
            'referral_friend_discount' => 'required|integer|min:0|max:10000',
        ]);
        if ($data['referral_reward_type'] === 'percent' && $data['referral_reward_value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['referral_reward_value' => 'Percentage cannot exceed 100.']);
        }
        if ($data['referral_friend_discount'] >= $data['referral_minimum']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['referral_friend_discount' => 'Friend discount must be smaller than the qualifying minimum.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('store_settings')->updateOrInsert(['key' => $key], ['value' => (string) $value]);
            }
        });
        return redirect(url('/admin') . '#referral-wallet')->with('success', 'Referral rules saved. Existing rewards retain their original amounts.');
    }

    public function retry(Request $request, int $id)
    {
        $this->admin();
        abort_unless(DB::table('store_mail_deliveries')->where('id', $id)->exists(), 404);
        $success = MailDelivery::attempt($id, true);
        return redirect(url('/admin') . '#email-delivery')->with('success', $success
            ? 'Email processed. Check its delivery status below.'
            : 'Email could not be resent or is already processed. Check the status and SMTP configuration.');
    }

    public function process(Request $request)
    {
        $this->admin();
        $count = ReferralWallet::processDue();
        return redirect(url('/admin') . '#referral-wallet')->with('success', $count . ' pending rewards checked against delivery, payment and return eligibility.');
    }
}
