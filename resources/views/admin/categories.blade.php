<section class="admin-page" id="categories">
    <div class="page-section-heading">
        <div>
            <h2>Categories</h2>
            <p>Create categories and subcategories with multiple images.</p>
        </div><button class="btn btn-primary" data-open-editor="category-new">+ Add category</button>
    </div>
    <div data-admin-list="categories"></div>
@foreach ($catalogCategories->concat([null]) as $c)
        <details class="editor-card" id="category-{{ $c?->id ?? 'new' }}" data-search-row data-admin-record='{{ json_encode(['group' => 'categories', 'title' => $c?->name ?? 'Add category', 'create' => !$c, 'name' => $c?->name, 'parent' => $catalogCategories->firstWhere('id', $c?->parent_id)?->name ?? 'Top level', 'images' => $c ? ($categoryImages[$c->id] ?? collect())->count() : 0, 'status' => $c?->active ? 'Published' : 'Hidden']) }}'>
            <summary class="editor-summary">{{ $c?->name ?? '+ Add category' }}{{ $c && !$c->active ? ' · Hidden' : '' }}
            </summary>
            <form method="POST" enctype="multipart/form-data" action="{{ url('/admin/categories') }}">@csrf<input
                    type="hidden" name="id" value="{{ $c?->id }}">
                <div class="editor"><label>Name<input class="form-control" name="name" required
                            value="{{ $c?->name }}"></label><label>URL slug<input class="form-control"
                            name="slug" value="{{ $c?->slug }}"></label><label>Parent category<select
                            class="form-select" name="parent_id">
                            <option value="">None</option>
                            @foreach ($catalogCategories as $parent)
                                @if ($parent->id !== $c?->id)
                                    <option value="{{ $parent->id }}" @selected($c?->parent_id === $parent->id)>{{ $parent->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </label><label>Sort position<input class="form-control" type="number" name="sort_order"
                            min="0" value="{{ $c?->sort_order ?? 0 }}" required></label></div><label
                    class="d-block my-3">Description
                    <textarea class="form-control" name="description" rows="3">{{ $c?->description }}</textarea>
                </label>@include('admin.seo-fields', ['seoRecord' => $c])<label class="d-block my-3">Category images<input
                        class="form-control" name="images[]" type="file" multiple
                        accept="image/jpeg,image/png,image/webp,image/avif"><small>Up to 10 per save, 20 total; 5 MB per
                        image.</small></label>
                <div class="upload-preview"></div><label class="d-block my-3"><input type="checkbox" name="active"
                        value="1" @checked($c?->active ?? true)> Visible (hiding also hides child
                    categories)</label><button type="submit" class="btn btn-primary">Save category</button>
            </form>
            @if ($c)
                @include('admin.gallery', [
                    'kind' => 'category',
                    'owner' => $c,
                    'gallery' => $categoryImages[$c->id] ?? collect(),
                ])
            @endif
        </details>
    @endforeach
</section>
