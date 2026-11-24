<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController
{
    public function save(Request $request)
    {
        abort_unless(Auth::user()?->role === 'admin' && Auth::user()->is_active, 403);
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }
        $data = $request->validate([
            'id' => 'nullable|integer|exists:brands,id',
            'name' => ['required', 'string', 'max:100', Rule::unique('brands')->ignore($request->id)],
            'slug' => ['nullable', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands')->ignore($request->id)],
            'description' => 'nullable|string|max:5000',
            'sort_order' => 'required|integer|min:0|max:100000',
            'active' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:2048',
        ]);
        $newLogo = null;
        if ($request->hasFile('logo')) {
            $directory = public_path('uploads/brands');
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            $filename = Str::uuid() . '.' . $request->file('logo')->extension();
            $request->file('logo')->move($directory, $filename);
            $newLogo = 'uploads/brands/' . $filename;
        }
        try {
            DB::transaction(function () use ($request, $data, $newLogo) {
                $existing = !empty($data['id'])
                    ? DB::table('brands')->where('id', $data['id'])->lockForUpdate()->firstOrFail() : null;
                $slug = $data['slug'] ?? $existing?->slug;
                if (!$slug) {
                    $base = Str::slug($data['name']) ?: 'brand';
                    $slug = $base;
                    $suffix = 2;
                    while (DB::table('brands')->where('slug', $slug)->exists()) {
                        $slug = $base . '-' . $suffix++;
                    }
                }
                $logo = $newLogo ?? ($request->boolean('remove_logo') ? null : $existing?->logo);
                $values = [
                    'name' => $data['name'], 'slug' => $slug, 'description' => $data['description'] ?? null,
                    'sort_order' => $data['sort_order'], 'active' => $request->boolean('active'),
                    'logo' => $logo, 'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('brands')->where('id', $existing->id)->update($values);
                    DB::table('products')->where('brand_id', $existing->id)->update(['brand' => $data['name'], 'updated_at' => now()]);
                    if ($existing->logo && $existing->logo !== $logo) {
                        DB::afterCommit(fn () => self::removeLogo($existing->logo));
                    }
                } else {
                    DB::table('brands')->insert($values + ['created_at' => now()]);
                }
            });
        } catch (\Throwable $exception) {
            if ($newLogo) {
                self::removeLogo($newLogo);
            }
            throw $exception;
        }
        return redirect(url('/admin') . '#brands')->with('success', 'Brand saved. It is available in the admin and seller product dropdowns when active.');
    }

    private static function removeLogo(string $path): void
    {
        $root = realpath(public_path('uploads/brands'));
        $file = realpath(public_path($path));
        if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
            unlink($file);
        }
    }
}
