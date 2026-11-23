<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AddressBook
{
    public static function save(int $userId, array $data)
    {
        return DB::transaction(function () use ($userId, $data) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $id = $data['id'] ?? null;
            $values = collect($data)->only(['label', 'name', 'phone', 'address', 'city', 'state', 'pincode'])->all();
            $values['state'] = $values['state'] ?? '';
            $owned = DB::table('addresses')->where('user_id', $userId);
            if ($id) {
                $current = (clone $owned)->where('id', $id)->lockForUpdate()->first();
                abort_unless($current, 404);
            } else {
                $match = clone $owned;
                foreach (['label', 'name', 'phone', 'address', 'city', 'state', 'pincode'] as $field) {
                    $match->where($field, $values[$field]);
                }
                $current = $match->first();
                $id = $current?->id;
            }

            $default = !$owned->exists() || !empty($data['is_default']) || (bool) $current?->is_default;
            if ($default) {
                (clone $owned)->update(['is_default' => false]);
            }
            $values += ['is_default' => $default, 'updated_at' => now()];
            if ($id) {
                (clone $owned)->where('id', $id)->update($values);
            } else {
                $id = DB::table('addresses')->insertGetId($values + ['user_id' => $userId, 'created_at' => now()]);
            }
            return (clone $owned)->where('id', $id)->first();
        });
    }
}
