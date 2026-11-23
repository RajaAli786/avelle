<dialog id="checkoutAddressDialog" class="checkout-address-dialog">
    <form id="checkoutAddressEditor" method="POST" action="{{ url('/account/addresses') }}">
        @csrf
        <div class="saved-address-heading"><h2 id="addressEditorTitle">Add delivery address</h2><button type="button" class="address-dialog-close" data-close-address aria-label="Close address form">×</button></div>
        <input type="hidden" name="id">
        <div class="two-fields"><label>Address label<input name="label" value="Home" maxlength="30" required></label><label>Full name<input name="name" maxlength="100" autocomplete="name" required></label></div>
        <label>Mobile number<input name="phone" pattern="[6-9][0-9]{9}" autocomplete="tel" required></label>
        <label>House, street and area<textarea name="address" maxlength="500" autocomplete="street-address" required></textarea></label>
        <div class="two-fields"><label>City<input name="city" maxlength="100" autocomplete="address-level2" required></label><label>State<input name="state" maxlength="100" autocomplete="address-level1" required></label></div>
        <label>PIN code<input name="pincode" pattern="[1-9][0-9]{5}" autocomplete="postal-code" required></label>
        <label class="check-label"><input type="checkbox" name="is_default" value="1">Make this my default address</label>
        <p id="addressEditorError" role="alert" class="inline-error"></p>
        <button type="submit" class="pink-button">Save and use address</button>
    </form>
</dialog>
