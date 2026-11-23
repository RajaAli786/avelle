@if ($gallery->count())
<hr>
<h4>Image gallery</h4>
<p>First image is the cover. Move images, then save the order.</p>
<form method="POST"
    action="{{ url(($sellerMode ?? false ? '/seller' : '/admin') . '/media/' . $kind . '/' . $owner->id . '/order') }}">@csrf<div
        class="admin-gallery">
        @foreach ($gallery as $im)
        <div class="admin-image"><input type="hidden" name="order[]" value="{{ $im->id }}"><img
                src="{{ \App\Support\Shop::mediaUrl($im->path) }}" alt="{{ $im->alt }}"><input
                class="form-control" name="alts[{{ $im->id }}]" value="{{ $im->alt }}"
                placeholder="Image description">
            <div class="d-flex gap-1 my-2"><button type="button" class="btn btn-sm btn-light"
                    data-image-up>←</button><button type="button" class="btn btn-sm btn-light"
                    data-image-down>→</button><button type="submit"
                    form="delete-{{ $kind }}-{{ $im->id }}"
                    class="btn btn-sm btn-outline-danger">Remove</button></div>
        </div>
        @endforeach
    </div><button type="submit" class="btn btn-secondary mt-2">Save image order</button></form>
@foreach ($gallery as $im)
<form id="delete-{{ $kind }}-{{ $im->id }}" method="POST"
    action="{{ url(($sellerMode ?? false ? '/seller' : '/admin') . '/media/' . $kind . '/' . $owner->id . '/' . $im->id . '/delete') }}">
    @csrf</form>
@endforeach
@endif