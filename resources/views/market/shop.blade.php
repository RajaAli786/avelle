@extends('layouts.market')
@section('title', ($category?->name ?? (request('q') ? 'Search: ' . request('q') : 'All products')) . ' · Avelle')
@section('content')
    <div class="shell breadcrumbs">
        <a href="{{ url('/') }}">Home</a><span>/</span><a
            href="{{ url('/categories') }}">Collections</a><span>/</span><span>{{ $category?->name ?? 'All products' }}</span>
    </div>
    @if ($category && $category->images->count())
        <section class="category-banner shell">
            <div><span class="eyebrow">THE AVELLE COLLECTION</span>
                <h1>{{ $category->name }}</h1>
                <p>{{ $category->description ?: 'Explore your next everyday favourites.' }}</p>
                <div class="category-gallery-thumbs">
                    @foreach ($category->images as $ci)
                        <button data-category-image="{{ \App\Support\Shop::mediaUrl($ci->path) }}"
                            aria-label="View {{ $category->name }} image {{ $loop->iteration }}"><img
                                src="{{ \App\Support\Shop::mediaUrl($ci->path) }}" alt=""></button>
                    @endforeach
                </div>
            </div><img id="categoryMainImage" src="{{ $category->cover }}" alt="{{ $category->name }}">
        </section>
    @endif
    <section class="shell catalogue">
        <div class="catalogue-heading">
            <div>
                <h1 class="page-title">
                    {{ request('q') ? 'Results for “' . request('q') . '”' : $category?->name ?? (request('audience') === 'girls' ? 'Girls’ collection' : 'All products') }}
                </h1><span>{{ $products->total() }} products</span>
            </div>
            <form method="GET" class="sort-form">
                @foreach (request()->except('sort', 'page') as $key => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label>
                    Sort by <select name="sort" data-submit-on-change>
                        @foreach (['recommended' => 'Recommended', 'newest' => 'Newest first', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'rating' => 'Customer rating', 'discount' => 'Best discount'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('sort', 'recommended') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label><noscript><button>Sort</button></noscript>
            </form>
        </div>
        <div class="catalogue-layout">
            <details class="filters-panel" id="filtersPanel" open>
                <summary>☷ Filter products <span>⌄</span></summary>
                <form method="GET">
                    <div class="filters-title"><b>Filters</b><a href="{{ url()->current() }}">Clear all</a></div>
                    @if (request('q'))
                        <input type="hidden" name="q" value="{{ request('q') }}">
                    @endif
                    <input type="hidden" name="sort" value="{{ request('sort', 'recommended') }}">
                    <div class="filter-block">
                        <h3>Shop for</h3>
                        @foreach (['' => 'Everyone', 'women' => 'Women', 'girls' => 'Girls'] as $value => $label)
                            <label><input type="radio" name="audience" value="{{ $value }}"
                                    @checked(request('audience', '') === $value)>{{ $label }}</label>
                        @endforeach
                    </div>
                    <div class="filter-block">
                        <h3>Categories</h3>
                        @foreach ($navCategories as $c)
                            <a class="filter-category {{ $category?->id === $c->id ? 'active' : '' }}"
                                href="{{ url('/category/' . $c->slug) }}">{{ $c->name }} <span>›</span></a>
                        @endforeach
                    </div>
                    <div class="filter-block">
                        <h3>Price range</h3>
                        <div class="price-filter"><label><span>Min ₹</span><input name="min" type="number"
                                    min="0" value="{{ request('min') }}" placeholder="0"></label><label><span>Max
                                    ₹</span><input name="max" type="number" min="0"
                                    value="{{ request('max') }}" placeholder="Any"></label></div>
                    </div>
                    <div class="filter-block">
                        <h3>Size</h3><select name="size">
                            <option value="">All sizes</option>
                            @foreach ($sizes as $size)
                                <option @selected(request('size') === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($colors->count())
                        <div class="filter-block">
                            <h3>Colour</h3><select name="color">
                                <option value="">All colours</option>
                                @foreach ($colors as $color)
                                    <option @selected(request('color') === $color)>{{ $color }}</option>
                                @endforeach
                            </select>
                        </div>@endif @if ($brands->count())
                            <div class="filter-block">
                                <h3>Brand</h3><select name="brand">
                                    <option value="">All brands</option>
                                    @foreach ($brands as $brand)
                                        <option @selected(request('brand') === $brand)>{{ $brand }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <button class="pink-button full">
                            Apply filters</button>
                </form>
            </details>
            <div>
                <div class="shop-product-grid catalogue-grid">
                    @forelse($products as $p)
                        @include('market.card', ['p' => $p])@empty
                        <div class="empty-state"><span>⌕</span>
                            <h2>No matches just yet.</h2>
                            <p>Try a different filter or explore the full collection.</p><a href="{{ url('/shop') }}"
                                class="pink-button">See all products ↗</a>
                        </div>
                    @endforelse
                </div>
                @if ($products->hasPages())
                    <nav class="pagination" aria-label="Product pages">
                        @if (!$products->onFirstPage())
                            <a href="{{ $products->previousPageUrl() }}">← Previous</a>
                            @endif @for ($i = max(1, $products->currentPage() - 2); $i <= min($products->lastPage(), $products->currentPage() + 2); $i++)
                                <a class="{{ $i === $products->currentPage() ? 'current' : '' }}"
                                    href="{{ $products->url($i) }}"
                                    @if ($i === $products->currentPage()) aria-current="page" @endif>{{ $i }}</a>
                                @endfor @if ($products->hasMorePages())
                                    <a href="{{ $products->nextPageUrl() }}">Next →</a>
                                @endif
                    </nav>
                @endif
            </div>
        </div>
    </section>
@endsection
