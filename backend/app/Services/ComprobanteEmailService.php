<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ComprobanteEmailService
{
    public function send(
        object $usuario,
        object $pedido,
        string $tipo,
        string $numeroFormateado,
        string $pdfPath,
        int $comprobanteId
    ): array {
        $email = trim((string) ($usuario->email ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->failure('El usuario no tiene un correo valido registrado.');
        }

        $apiKey = trim((string) config('services.resend.key'));
        $fromAddress = trim((string) config('mail.from.address'));
        $fromName = trim((string) config('mail.from.name', 'Delicias del centro'));

        if ($apiKey === '' || $fromAddress === '' || str_contains($fromAddress, 'example.com')) {
            return $this->failure('El envio de correo no esta configurado en el API.');
        }

        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            return $this->failure('No se encontro el PDF del comprobante.');
        }

        $pdfContent = file_get_contents($pdfPath);
        if ($pdfContent === false) {
            return $this->failure('No se pudo leer el PDF del comprobante.');
        }

        $cliente = trim((string) ($usuario->nombre ?? '') . ' ' . (string) ($usuario->apellido ?? ''));
        $safeName = htmlspecialchars($cliente !== '' ? $cliente : 'cliente', ENT_QUOTES, 'UTF-8');
        $safeNumber = htmlspecialchars($numeroFormateado, ENT_QUOTES, 'UTF-8');
        $safeType = htmlspecialchars(strtoupper($tipo), ENT_QUOTES, 'UTF-8');
        $pedidoId = (int) ($pedido->id ?? 0);
        $total = number_format((float) ($pedido->total ?? 0), 2, '.', '');

        $html = <<<HTML
            <div style="font-family:Arial,sans-serif;color:#292524;line-height:1.6">
                <h2>Tu comprobante de compra</h2>
                <p>Hola {$safeName}, adjuntamos el comprobante de tu pedido #{$pedidoId}.</p>
                <p><strong>Tipo:</strong> {$safeType}<br>
                <strong>Numero:</strong> {$safeNumber}<br>
                <strong>Total:</strong> S/ {$total}</p>
                <p>Gracias por comprar en Delicias del centro.</p>
            </div>
            HTML;

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'Idempotency-Key' => "comprobante-{$comprobanteId}",
                ])
                ->acceptJson()
                ->timeout(30)
                ->post('https://api.resend.com/emails', [
                    'from' => $fromName . ' <' . $fromAddress . '>',
                    'to' => [$email],
                    'subject' => "Comprobante {$numeroFormateado} - Delicias del centro",
                    'html' => $html,
                    'attachments' => [[
                        'filename' => basename($pdfPath),
                        'content' => base64_encode($pdfContent),
                    ]],
                    'tags' => [
                        ['name' => 'type', 'value' => 'receipt'],
                        ['name' => 'receipt_id', 'value' => (string) $comprobanteId],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('Resend rejected the receipt email.', [
                    'comprobante_id' => $comprobanteId,
                    'pedido_id' => $pedidoId,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return $this->failure('El comprobante fue emitido, pero el correo no pudo enviarse.');
            }

            return [
                'enviado' => true,
                'message' => 'El comprobante fue enviado al correo registrado.',
                'provider_id' => (string) $response->json('id', ''),
            ];
        } catch (\Throwable $exception) {
            Log::error('Could not send receipt email.', [
                'comprobante_id' => $comprobanteId,
                'pedido_id' => $pedidoId,
                'error' => $exception->getMessage(),
            ]);

            return $this->failure('El comprobante fue emitido, pero el correo no pudo enviarse.');
        }
    }

    private function failure(string $message): array
    {
        Log::warning('Receipt email was not sent.', ['reason' => $message]);

        return [
            'enviado' => false,
            'message' => $message,
        ];
    }
}
