@php
    $seo = \App\Support\Seo::meta(
        get_defined_vars(),
        trim($__env->yieldContent('title', 'Avelle — Women & girls, everyday favourites')),
        trim(
            $__env->yieldContent(
                'description',
                'Discover clothing, beauty, accessories and everyday favourites for women and girls at Avelle.',
            ),
        ),
    );
@endphp
<title>
    {{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
@if ($seo['keywords'])
    <meta name="keywords" content="{{ $seo['keywords'] }}">
@endif
<meta name="robots" content="{{ $seo['robots'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:site_name" content="Avelle">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@if ($seo['image'])
    <meta property="og:image" content="{{ $seo['image'] }}">
@endif
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
@if ($seo['image'])
    <meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
@foreach ($seo['schema'] as $schema)
    <script type="application/ld+json">{!! json_encode($schema,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
