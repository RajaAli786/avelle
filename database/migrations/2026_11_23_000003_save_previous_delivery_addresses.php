<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Support\AddressBook;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('orders')->whereColumn('orders.user_id', 'users.id');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('addresses')->whereColumn('addresses.user_id', 'users.id');
            })
            ->orderBy('id')->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $order = DB::table('orders')->where('user_id', $user->id)->latest('id')->first();
                    AddressBook::save($user->id, [
                        'label' => 'Home',
                        'name' => $order->name,
                        'phone' => $order->phone,
                        'address' => $order->address,
                        'city' => $order->city,
                        'state' => $order->state ?? '',
                        'pincode' => $order->pincode,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Saved customer addresses remain available if this data migration is rolled back.
    }
};
