<section class="admin-page" id="brands">
    <div class="page-section-heading">
        <div><h2>Brands</h2><p>One brand list for your store and seller product catalogue.</p></div>
        <button class="btn btn-primary" data-open-editor="brand-new">+ Add brand</button>
    </div>
    <div data-admin-list="brands"></div>
    @foreach ($catalogBrands->concat([null]) as $brand)
        <details class="editor-card" id="brand-{{ $brand?->id ?? 'new' }}" data-search-row
            data-admin-record='{{ json_encode([
                "group" => "brands",
                "title" => $brand?->name ?? "Add brand",
                "create" => !$brand,
                "name" => $brand?->name,
                "slug" => $brand?->slug,
                "products" => $brand?->product_count ?? 0,
                "status" => $brand?->active ? "Active" : "Inactive",
            ]) }}'>
            <summary>{{ $brand?->name ?? '+ Add brand' }}</summary>
            <form method="POST" enctype="multipart/form-data" action="{{ url('/admin/brands') }}">
                @csrf
                <input type="hidden" name="_section" value="brands">
                <input type="hidden" name="id" value="{{ $brand?->id }}">
                <div class="editor">
                    <label>Brand name<input class="form-control" name="name" required maxlength="100" value="{{ $brand?->name }}"></label>
                    <label>URL slug (optional)<input class="form-control" name="slug" maxlength="180" value="{{ $brand?->slug }}" placeholder="Generated from the name"></label>
                    <label>Sort position<input class="form-control" type="number" name="sort_order" min="0" max="100000" required value="{{ $brand?->sort_order ?? 0 }}"></label>
                </div>
                <label class="d-block my-3">About this brand<textarea class="form-control" name="description" rows="4" maxlength="5000">{{ $brand?->description }}</textarea></label>
                @if ($brand?->logo)
                    <img src="{{ asset($brand->logo) }}" alt="{{ $brand->name }} logo" width="140" height="90" class="brand-logo-preview">
                    <label class="d-block my-2"><input type="checkbox" name="remove_logo" value="1"> Remove existing logo</label>
                @endif
                <label class="d-block my-3">Brand logo<input class="form-control" type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/avif"><small>JPG, PNG, WebP or AVIF, up to 2 MB.</small></label>
                <label class="d-block my-3"><input type="checkbox" name="active" value="1" @checked($brand?->active ?? true)> Active — available for new product selections</label>
                <p class="text-secondary">Existing products keep their brand when it is inactive. Renaming updates the brand label on linked products.</p>
                <button type="submit" class="btn btn-primary">Save brand</button>
            </form>
        </details>
    @endforeach
</section>
