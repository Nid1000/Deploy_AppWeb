<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactoRecibidoAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $contacto)
    {
    }

    public function envelope(): Envelope
    {
        $nombre = (string) ($this->contacto['nombre'] ?? 'Cliente');

        return new Envelope(
            subject: "Nuevo mensaje de contacto: {$nombre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contacto_admin',
            with: [
                'c' => $this->contacto,
            ],
        );
    }
}
