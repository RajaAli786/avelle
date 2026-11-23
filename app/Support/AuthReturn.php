<?php

namespace App\Support;

use Illuminate\Http\Request;

class AuthReturn
{
    public static function safe(mixed $target): ?string
    {
        if (!is_string($target) || !$target || preg_match('/[\x00-\x20\x7f\\\\]/', $target)) {
            return null;
        }
        $origin = request()->getSchemeAndHttpHost();
        $absolute = str_starts_with($target, '/') && !str_starts_with($target, '//')
            ? $origin . $target
            : $target;
        $parts = parse_url($absolute);
        $current = parse_url($origin);
        if (!$parts || isset($parts['user']) || isset($parts['pass'])
            || strtolower($parts['scheme'] ?? '') !== strtolower($current['scheme'])
            || strtolower($parts['host'] ?? '') !== strtolower($current['host'])
            || ($parts['port'] ?? null) !== ($current['port'] ?? null)) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        $base = rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/');
        if ($base && $path !== $base && !str_starts_with($path, $base . '/')) {
            return null;
        }
        $route = substr($path, strlen($base));
        if (in_array($route, ['/account/login', '/account/register', '/forgot-password'])
            || str_starts_with($route, '/reset-password')) {
            return null;
        }
        return $origin . $path
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    public static function remember(Request $request): void
    {
        $target = self::safe($request->input('return_to'));
        if ($request->boolean('checkout')) {
            $target = url('/checkout');
        }
        if (!$target && !$request->session()->has('url.intended')) {
            $target = self::safe($request->headers->get('referer'));
        }
        if ($target) {
            $request->session()->put('url.intended', $target);
        }
    }

    public static function login(Request $request, ?string $target = null)
    {
        $target = self::safe($target ?? $request->input('return_to') ?? $request->fullUrl()) ?? url('/account');
        $request->session()->put('url.intended', $target);
        return redirect(url('/account/login'));
    }

    public static function finish(Request $request)
    {
        $fallback = $request->boolean('checkout') ? url('/checkout') : url('/account');
        $target = self::safe($request->session()->pull('url.intended', $fallback)) ?? url('/account');
        return redirect($target);
    }
}
