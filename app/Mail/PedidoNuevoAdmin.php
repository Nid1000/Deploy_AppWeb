<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PedidoNuevoAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $pedido, public readonly array $detalles)
    {
    }

    public function envelope(): Envelope
    {
        $id = (int) ($this->pedido['id'] ?? 0);

        return new Envelope(
            subject: "Nuevo pedido #{$id} - Delicias Bakery",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pedido_admin',
            with: [
                'p' => $this->pedido,
                'd' => $this->detalles,
            ],
        );
    }
}
