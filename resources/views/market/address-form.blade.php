<form method="POST" action="{{ url('/account/addresses') }}">
    @csrf @if ($a)
        <input type="hidden" name="id" value="{{ $a->id }}">
    @endif
    <div class="two-fields"><label>Address label<input name="label" value="{{ $a?->label ?? 'Home' }}" required
                maxlength="30"></label><label>Full name<input name="name" value="{{ $a?->name ?? auth()->user()->name }}"
                required maxlength="100"></label></div><label>Mobile number<input name="phone"
            value="{{ $a?->phone ?? auth()->user()->phone }}" required pattern="[6-9][0-9]{9}"></label><label>Full address
        <textarea name="address" required maxlength="500">{{ $a?->address }}</textarea>
    </label>
    <div class="two-fields"><label>City<input name="city" value="{{ $a?->city }}" required
                maxlength="100"></label><label>State<input name="state" value="{{ $a?->state }}" required
                maxlength="100"></label></div><label>PIN code<input name="pincode" value="{{ $a?->pincode }}" required
            pattern="[1-9][0-9]{5}"></label><label class="check-label"><input name="is_default" type="checkbox"
            value="1" @checked($a?->is_default)>Make this my default address</label><button
        class="pink-button">Save address</button>
</form>
