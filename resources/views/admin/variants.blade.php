<hr>
<h4>Size / colour variants</h4>
<p>Each active combination has its own stock and optional price. Removing a row archives it. Saving an empty list sets
    stock to zero.</p>
<form method="POST" action="{{ url(($sellerMode ?? false ? '/seller' : '/admin') . '/products/' . $p->id . '/variants') }}">@csrf
    <div data-variant-list>
        @foreach ($variants[$p->id] ?? collect() as $vi => $v)
        <div class="variant-row"><input type="hidden" name="variants[{{ $vi }}][id]"
                value="{{ $v->id }}">
            @foreach (['size', 'color', 'sku', 'price', 'stock'] as $key)
            <label>{{ ucfirst($key) }}<input class="form-control"
                    name="variants[{{ $vi }}][{{ $key }}]" value="{{ $v->$key }}"
                    @required(in_array($key, ['size', 'color' , 'stock' ]))></label>
            @endforeach
            <label>
                Active<select class="form-select" name="variants[{{ $vi }}][active]">
                    <option value="1" @selected($v->active)>Yes</option>
                    <option value="0" @selected(!$v->active)>No</option>
                </select></label><button type="button" class="btn btn-light" data-remove-row>×</button>
        </div>
        @endforeach
    </div><button type="button" class="btn btn-light my-3" data-add-variant>+ Add variant</button><button
        type="submit" class="btn btn-primary">Save variant inventory</button>
</form>