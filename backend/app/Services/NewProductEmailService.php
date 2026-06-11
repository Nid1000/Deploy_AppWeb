<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewProductEmailService
{
    private const BATCH_SIZE = 100;

    public function send(object $product): array
    {
        if (!(bool) config('services.product_notifications.email_enabled', true)) {
            return [
                'enabled' => false,
                'sent' => 0,
                'failed' => 0,
                'message' => 'Las notificaciones por correo de productos nuevos estan desactivadas.',
            ];
        }

        $apiKey = trim((string) config('services.resend.key'));
        $fromAddress = trim((string) config('mail.from.address'));
        $fromName = trim((string) config('mail.from.name', 'Delicias del centro'));

        if ($apiKey === '' || $fromAddress === '' || str_contains($fromAddress, 'example.com')) {
            return $this->failure('El envio de correo no esta configurado en el API.');
        }

        $users = DB::table('usuarios')
            ->select(['id', 'nombre', 'email'])
            ->where('activo', 1)
            ->whereNotNull('email')
            ->orderBy('id')
            ->get()
            ->filter(fn ($user) => filter_var(trim((string) $user->email), FILTER_VALIDATE_EMAIL) !== false)
            ->unique(fn ($user) => mb_strtolower(trim((string) $user->email)))
            ->values();

        if ($users->isEmpty()) {
            return [
                'enabled' => true,
                'sent' => 0,
                'failed' => 0,
                'message' => 'No hay clientes activos con correo valido.',
            ];
        }

        $sent = 0;
        $failed = 0;

        foreach ($users->chunk(self::BATCH_SIZE) as $chunkIndex => $chunk) {
            $emails = $chunk
                ->map(fn ($user) => $this->messageFor($user, $product, $fromName, $fromAddress))
                ->values()
                ->all();

            try {
                $response = Http::withToken($apiKey)
                    ->withHeaders([
                        'Idempotency-Key' => 'new-product-' . (int) $product->id . '-batch-' . $chunkIndex,
                    ])
                    ->acceptJson()
                    ->timeout(30)
                    ->post('https://api.resend.com/emails/batch', $emails);

                if ($response->successful()) {
                    $sent += count($emails);
                    continue;
                }

                $failed += count($emails);
                Log::error('Resend rejected a new product email batch.', [
                    'product_id' => (int) $product->id,
                    'batch' => $chunkIndex,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            } catch (\Throwable $exception) {
                $failed += count($emails);
                Log::error('Could not send a new product email batch.', [
                    'product_id' => (int) $product->id,
                    'batch' => $chunkIndex,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'enabled' => true,
            'sent' => $sent,
            'failed' => $failed,
            'message' => $failed === 0
                ? "Se notifico por correo a {$sent} clientes."
                : "Se notifico a {$sent} clientes y fallaron {$failed} envios.",
        ];
    }

    private function messageFor(
        object $user,
        object $product,
        string $fromName,
        string $fromAddress
    ): array {
        $clientName = trim((string) ($user->nombre ?? ''));
        $safeClient = htmlspecialchars($clientName !== '' ? $clientName : 'cliente', ENT_QUOTES, 'UTF-8');
        $safeProduct = htmlspecialchars((string) $product->nombre, ENT_QUOTES, 'UTF-8');
        $safeDescription = htmlspecialchars(
            trim((string) ($product->descripcion ?? '')) ?: 'Descubre nuestro nuevo producto.',
            ENT_QUOTES,
            'UTF-8'
        );
        $price = number_format((float) ($product->precio ?? 0), 2, '.', '');
        $productUrl = rtrim((string) config('services.frontend.url'), '/')
            . '/productos/' . (int) $product->id;
        $safeProductUrl = htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8');
        $imageUrl = $this->imageUrl((string) ($product->imagen ?? ''));
        $imageHtml = $imageUrl !== ''
            ? '<p><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $safeProduct . '" style="max-width:520px;width:100%;border-radius:16px"></p>'
            : '';

        $html = <<<HTML
            <div style="font-family:Arial,sans-serif;color:#292524;line-height:1.6">
                <h2>Tenemos un producto nuevo</h2>
                <p>Hola {$safeClient}, ya esta disponible <strong>{$safeProduct}</strong>.</p>
                {$imageHtml}
                <p>{$safeDescription}</p>
                <p><strong>Precio:</strong> S/ {$price}</p>
                <p><a href="{$safeProductUrl}" style="display:inline-block;padding:12px 20px;background:#1c1917;color:#fff;text-decoration:none;border-radius:10px">Ver producto</a></p>
            </div>
            HTML;

        return [
            'from' => $fromName . ' <' . $fromAddress . '>',
            'to' => [trim((string) $user->email)],
            'subject' => "Nuevo producto: {$product->nombre}",
            'html' => $html,
            'tags' => [
                ['name' => 'type', 'value' => 'new_product'],
                ['name' => 'product_id', 'value' => (string) $product->id],
            ],
        ];
    }

    private function imageUrl(string $image): string
    {
        $image = trim(str_replace('\\', '/', $image));
        if ($image === '') {
            return '';
        }

        if (str_starts_with($image, 'https://') || str_starts_with($image, 'http://')) {
            return $image;
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        if (str_starts_with($image, '/uploads/')) {
            return $baseUrl . $image;
        }
        if (str_starts_with($image, 'uploads/')) {
            return $baseUrl . '/' . $image;
        }

        return $baseUrl . '/uploads/' . ltrim($image, '/');
    }

    private function failure(string $message): array
    {
        Log::warning('New product emails were not sent.', ['reason' => $message]);

        return [
            'enabled' => true,
            'sent' => 0,
            'failed' => 0,
            'message' => $message,
        ];
    }
}
