<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class RegistroCodigoVerificacionMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly Carbon $expiresAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Codigo de verificacion para tu registro',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registro_codigo_verificacion',
        );
    }
}
