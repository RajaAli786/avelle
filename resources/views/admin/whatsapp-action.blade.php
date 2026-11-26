<div class="admin-whatsapp-action">
    @if ($whatsappUrl)
        <a class="admin-whatsapp-button" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">
            <i class="bi bi-whatsapp" aria-hidden="true"></i> Send on WhatsApp
        </a>
        <p>Opens a message with this record's current status. Review it and press Send in WhatsApp. Email updates continue automatically.</p>
    @else
        <p>WhatsApp is unavailable: this order needs a valid Indian mobile number. Email updates continue automatically.</p>
    @endif
</div>
