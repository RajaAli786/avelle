<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\Seo;

class SearchIndexing
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if (!config("seo.indexable") || Seo::privatePath() || $response->getStatusCode() >= 400) {
            $response->headers->set("X-Robots-Tag", "noindex, nofollow");
        }
        return $response;
    }
}
