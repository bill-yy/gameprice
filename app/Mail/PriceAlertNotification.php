<?php

namespace App\Mail;

use App\Models\PriceAlert;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceAlertNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PriceAlert $alert,
        public Product $product,
    ) {}

    public function envelope(): Envelope
    {
        $price = number_format($this->product->current_price, 2);

        return new Envelope(
            subject: "🚨 ¡Bajó de precio! {$this->alert->game->title} ahora desde {$price}€",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.price-alert',
        );
    }
}
