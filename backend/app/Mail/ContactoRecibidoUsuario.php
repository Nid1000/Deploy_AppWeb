<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactoRecibidoUsuario extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $contacto)
    {
    }

    public function build()
    {
        return $this->subject('Recibimos tu mensaje — Delicias Bakery')
            ->view('emails.contacto_usuario')
            ->with(['c' => $this->contacto]);
    }
}

