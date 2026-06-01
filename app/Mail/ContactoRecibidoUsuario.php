<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactoRecibidoUsuario extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $contacto)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibimos tu mensaje - Delicias Bakery',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contacto_usuario',
            with: [
                'c' => $this->contacto,
            ],
        );
    }
}
