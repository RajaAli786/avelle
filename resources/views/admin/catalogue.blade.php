@php($catalogBase = $sellerMode ?? false ? '/seller' : '/admin')
<section class="admin-page" id="products">
    <div class="page-section-heading">
        <div>
            <h2>Product catalogue</h2>
            <p>Descriptions, specifications, galleries and size / colour inventory.</p>
        </div><button class="btn btn-primary" data-open-editor="product-new">+ Add product</button>
    </div>
    <div data-admin-list="products"></div>
@foreach ($products->concat([null]) as $p)
    <details class="editor-card" id="product-{{ $p?->id ?? 'new' }}" data-search-row data-admin-record='{{ json_encode(['group' => 'products', 'title' => $p?->name ?? 'Add product', 'create' => !$p, 'name' => $p?->name, 'category' => $p?->category, 'brand' => $p?->brand ?: 'Unbranded', 'price' => $p ? '₹'.number_format($p->price) : '', 'stock' => $p?->stock, 'status' => $p ? ($p->seller_status !== 'approved' ? ucfirst($p->seller_status) : ($p->active ? 'Published' : 'Hidden')) : '']) }}'>
        <summary class="editor-summary">
            {{ $p ? '#' . $p->id . ' · ' . $p->name . ' · ₹' . $p->price . ' · Stock ' . $p->stock : '+ Add a product' }}
        </summary>
        @if ($p && $p->seller_id)
        <p class="alert alert-light">Review status: <strong>{{ $p->seller_status }}</strong> ·
            {{ $p->moderation_note }}@if ($sellerMode ?? false)
            <br>Product edits, variants and gallery changes require approval again.
            @endif
        </p>
        @endif
        <form method="POST" enctype="multipart/form-data" action="{{ url($catalogBase . '/products') }}">
            @csrf<input type="hidden" name="_section" value="products"><input type="hidden" name="id"
                value="{{ $p?->id }}">
            <div class="editor">
                @foreach (['name' => 'Product name', 'slug' => 'URL slug (optional)', 'sku' => 'SKU (optional)', 'price' => 'Selling price ₹', 'old' => 'Original price ₹', 'color' => 'Colour', 'tag' => 'Badge', 'sizes' => 'Sizes separated by commas', 'stock' => 'Stock (calculated when variants exist)'] as $key => $label)
                <label>{{ $label }}<input class="form-control" name="{{ $key }}"
                        value="{{ $key === 'sizes' ? ($p ? implode(', ', json_decode($p->sizes, true)) : 'S, M, L') : $p?->$key ?? '' }}"
                        @required(in_array($key, ['name', 'price' , 'old' , 'color' , 'sizes' , 'stock' ]))
                        @if (in_array($key, ['price', 'old' , 'stock' ])) type="number" min="0" @endif></label>
                @endforeach
                <label>Brand<select class="form-select" name="brand_id">
                    <option value="">Unbranded / no brand</option>
                    @foreach ($catalogBrands as $brand)
                        @if ($brand->active || (int) $p?->brand_id === (int) $brand->id)
                            <option value="{{ $brand->id }}" @selected((int) $p?->brand_id === (int) $brand->id)>{{ $brand->name }}{{ $brand->active ? '' : ' (inactive — current brand)' }}</option>
                        @endif
                    @endforeach
                </select><small>{{ ($sellerMode ?? false) ? 'Missing a brand? Ask the store administrator to add it.' : 'Manage this dropdown in the Brands section.' }}</small></label>
                <label>Category<select class="form-select" name="category_id" required>
                        @foreach ($catalogCategories as $c)
                        <option value="{{ $c->id }}" @selected($p?->category_id === $c->id)>
                            {{ $c->name }}{{ $c->active ? '' : ' (hidden)' }}
                        </option>
                        @endforeach
                    </select>
                </label><label>Collection<select class="form-select" name="audience">
                        @foreach (['women', 'girls', 'both'] as $v)
                        <option @selected(($p?->audience ?? 'women') === $v)>{{ $v }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="d-block my-3">Full product description
                <textarea class="form-control" name="description" rows="5" required minlength="20" maxlength="20000">{{ $p?->description }}</textarea>
            </label><label class="d-block my-3">Highlights (one per line)
                <textarea class="form-control" name="highlights" rows="3">{{ implode(
                        "
                    ",
                        json_decode($p?->highlights ?? '[]', true) ?? [],
                    ) }}</textarea>
            </label>
            <h4>Additional information</h4>
            <p class="text-secondary">For example: Fabric → Cotton, Fit → Regular, Care → Hand wash.</p>
            <div data-spec-list>
                @foreach (array_merge(json_decode($p?->specifications ?? '[]', true) ?? [], [['name' => '', 'value' => '']]) as $s)
                <div class="d-flex gap-2 mb-2"><input class="form-control" name="spec_keys[]"
                        placeholder="Attribute name" value="{{ $s['name'] }}"><input class="form-control"
                        name="spec_values[]" placeholder="Attribute value" value="{{ $s['value'] }}"><button
                        type="button" class="btn btn-light" data-remove-row>×</button></div>
                @endforeach
            </div>
            <button type="button" class="btn btn-light mb-3" data-add-spec>+ Add attribute</button>
            @include('admin.seo-fields', ['seoRecord' => $p])<label class="d-block my-3">Upload multiple product images<input
                    class="form-control" type="file" name="images[]"
                    accept="image/jpeg,image/png,image/webp,image/avif" multiple><small>Up to 10 per save, 20 total.
                    JPG, PNG, WebP or AVIF, maximum 5 MB each.</small></label>
            <div class="upload-preview"></div>
            <div class="d-flex gap-4 mb-3"><label><input type="checkbox" name="active" value="1"
                        @checked($p?->active ?? true)> Visible in store</label>
                @if (!($sellerMode ?? false))
                <label><input type="checkbox" name="featured" value="1" @checked($p?->featured ?? false)>
                    Featured on home</label>
                @endif
            </div>
            <button type="submit" class="btn btn-primary">Save product</button>
        </form>
        @if ($p)
        @include('admin.gallery', [
        'kind' => 'product',
        'owner' => $p,
        'gallery' => $productImages[$p->id] ?? collect(),
        ]) @include('admin.variants', ['p' => $p])
        @endif
    </details>
    @endforeach
</section>