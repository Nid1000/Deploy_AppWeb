<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PedidoConfirmacionUsuario extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $pedido, public readonly array $detalles)
    {
    }

    public function build()
    {
        $id = (int) ($this->pedido['id'] ?? 0);
        return $this->subject("Confirmación de pedido #{$id} — Delicias Bakery")
            ->view('emails.pedido_usuario')
            ->with(['p' => $this->pedido, 'd' => $this->detalles]);
    }
}

