<div class="seller-fields"><label>Shop name<input name="shop_name" maxlength="120" required
            value="{{ old('shop_name', $seller->shop_name ?? '') }}"></label><label>Business phone<input name="phone"
            pattern="[6-9][0-9]{9}" required value="{{ old('phone', $seller->phone ?? '') }}"></label><label>City<input
            name="city" required value="{{ old('city', $seller->city ?? '') }}"></label><label>State<input name="state"
            required value="{{ old('state', $seller->state ?? '') }}"></label><label>Pincode<input name="pincode"
            pattern="[1-9][0-9]{5}" required value="{{ old('pincode', $seller->pincode ?? '') }}"></label></div>
<label>Pickup address
    <textarea name="pickup_address" rows="3" minlength="10" required>{{ old('pickup_address', $seller->pickup_address ?? '') }}</textarea>
</label><label>About your shop
    <textarea name="description" rows="3" maxlength="3000">{{ old('description', $seller->description ?? '') }}</textarea>
</label>
