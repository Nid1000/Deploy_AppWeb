<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactoRecibidoAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $contacto)
    {
    }

    public function build()
    {
        $nombre = (string) ($this->contacto['nombre'] ?? 'Cliente');
        return $this->subject("Nuevo mensaje de contacto: {$nombre}")
            ->view('emails.contacto_admin')
            ->with(['c' => $this->contacto]);
    }
}

