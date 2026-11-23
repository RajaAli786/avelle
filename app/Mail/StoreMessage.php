<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
class StoreMessage extends Mailable
{
    public function __construct(
        public string $heading,
        public string $messageText,
        public ?string $actionUrl = null,
        public string $actionLabel = "Open Avelle",
        public array $lines = [],
    ) {}
    public function build()
    {
        return $this->subject($this->heading . " · Avelle")->view("emails.store");
    }
}
