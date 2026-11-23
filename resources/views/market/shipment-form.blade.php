<form method="POST" action="{{ $action }}">
    @csrf<div class="editor"><label>Shipment status<select class="form-select" name="status">
                @foreach (['placed', 'confirmed', 'shipped', 'delivered'] as $step)
                    <option @selected($shipment->status === $step)>{{ $step }}</option>
                @endforeach
            </select></label><label>Carrier<input class="form-control" name="carrier" maxlength="100"
                value="{{ $shipment->carrier }}"></label><label>Tracking number<input class="form-control"
                name="tracking_number" maxlength="150" value="{{ $shipment->tracking_number }}"></label><label>Tracking
            URL<input class="form-control" type="url" name="tracking_url"
                value="{{ $shipment->tracking_url }}"></label></div><button type="submit"
        class="btn btn-primary mt-3">Update shipment</button><small class="d-block mt-2">Move one step at a time.
        Carrier and tracking number are required before shipping.</small></form>
