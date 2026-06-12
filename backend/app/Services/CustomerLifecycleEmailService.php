<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CustomerLifecycleEmailService
{
    public function sendWelcome(object $user): bool
    {
        if (!$this->enabled('welcome_enabled')) {
            return false;
        }

        $eventKey = 'welcome-user-' . (int) $user->id;
        $catalogUrl = $this->frontendUrl('/productos');
        $name = $this->safeName($user);
        $offer = htmlspecialchars(
            trim((string) config('services.customer_lifecycle.welcome_offer')),
            ENT_QUOTES,
            'UTF-8'
        );
        $offerHtml = $offer !== ''
            ? '<div style="margin:22px 0;padding:16px;border-radius:14px;background:#fff7ed;color:#9a3412"><strong>Un detalle para ti:</strong> ' . $offer . '</div>'
            : '';

        $html = <<<HTML
            <div style="font-family:Arial,sans-serif;color:#292524;line-height:1.65;max-width:620px;margin:auto">
                <p style="color:#b45309;font-size:13px;letter-spacing:2px;text-transform:uppercase">Bienvenido a Delicias del centro</p>
                <h1 style="font-size:28px;margin:8px 0 18px">Hola {$name}, ya eres parte de nuestra familia</h1>
                <p>Gracias por registrarte. Preparamos nuestros productos con dedicacion, recetas de familia y el cuidado de una panaderia que hornea pensando en cada cliente.</p>
                {$offerHtml}
                <p><a href="{$catalogUrl}" style="display:inline-block;padding:13px 22px;background:#1c1917;color:#fff;text-decoration:none;border-radius:12px;font-weight:bold">Descubrir productos</a></p>
                <p style="margin-top:24px;color:#78716c;font-size:13px">Ahora podras revisar pedidos, guardar tus datos de entrega y recibir tus comprobantes por correo.</p>
            </div>
            HTML;

        return $this->send(
            $user,
            'welcome',
            $eventKey,
            'Bienvenido a Delicias del centro',
            $html,
            null,
            ['source' => 'registration']
        );
    }

    public function processPendingWelcomes(int $limit = 100): int
    {
        if (!$this->enabled('welcome_enabled') || !$this->eventTableExists()) {
            return 0;
        }

        $users = DB::table('usuarios as u')
            ->leftJoin('customer_email_events as e', function ($join): void {
                $join->on('e.user_id', '=', 'u.id')->where('e.type', '=', 'welcome');
            })
            ->where('u.activo', 1)
            ->where('u.created_at', '>=', now()->subDays(
                max(1, (int) config('services.customer_lifecycle.welcome_retry_days', 7))
            ))
            ->whereNull('e.id')
            ->select(['u.id', 'u.nombre', 'u.apellido', 'u.email'])
            ->orderBy('u.id')
            ->limit($limit)
            ->get();

        return $users->sum(fn ($user) => $this->sendWelcome($user) ? 1 : 0);
    }

    public function processDormantCustomers(int $limit = 100): int
    {
        if (!$this->enabled('dormant_enabled') || !$this->eventTableExists()) {
            return 0;
        }

        $days = max(1, (int) config('services.customer_lifecycle.dormant_days', 30));
        $users = DB::table('usuarios as u')
            ->join('pedidos as p', 'p.usuario_id', '=', 'u.id')
            ->where('u.activo', 1)
            ->where('p.estado', '<>', 'cancelado')
            ->groupBy('u.id', 'u.nombre', 'u.apellido', 'u.email')
            ->havingRaw('MAX(p.created_at) <= ?', [now()->subDays($days)])
            ->select([
                'u.id',
                'u.nombre',
                'u.apellido',
                'u.email',
                DB::raw('MAX(p.id) as last_order_id'),
                DB::raw('MAX(p.created_at) as last_order_at'),
            ])
            ->orderBy('last_order_at')
            ->limit($limit)
            ->get();

        $products = $this->topProducts();
        $sent = 0;

        foreach ($users as $user) {
            $eventKey = 'dormant-user-' . (int) $user->id . '-order-' . (int) $user->last_order_id;
            if ($this->eventExists($eventKey)) {
                continue;
            }

            $name = $this->safeName($user);
            $catalogUrl = $this->frontendUrl('/productos');
            $offer = htmlspecialchars(
                trim((string) config('services.customer_lifecycle.dormant_offer')),
                ENT_QUOTES,
                'UTF-8'
            );
            $offerHtml = $offer !== ''
                ? '<p style="padding:14px;border-radius:12px;background:#fff7ed;color:#9a3412"><strong>Tenemos algo para ti:</strong> ' . $offer . '</p>'
                : '';
            $productsHtml = $this->productsHtml($products);

            $html = <<<HTML
                <div style="font-family:Arial,sans-serif;color:#292524;line-height:1.65;max-width:620px;margin:auto">
                    <p style="color:#b45309;font-size:13px;letter-spacing:2px;text-transform:uppercase">Hace tiempo que no te vemos</p>
                    <h1 style="font-size:27px;margin:8px 0 18px">{$name}, te extranamos en Delicias</h1>
                    <p>El horno sigue encendido y tenemos productos que nuestros clientes estan disfrutando mucho.</p>
                    {$offerHtml}
                    {$productsHtml}
                    <p><a href="{$catalogUrl}" style="display:inline-block;padding:13px 22px;background:#1c1917;color:#fff;text-decoration:none;border-radius:12px;font-weight:bold">Ver que hay de nuevo</a></p>
                </div>
                HTML;

            if ($this->send(
                $user,
                'dormant',
                $eventKey,
                'Te extranamos en Delicias del centro',
                $html,
                null,
                ['last_order_id' => (int) $user->last_order_id]
            )) {
                $sent++;
            }
        }

        return $sent;
    }

    public function processReviewRequests(int $limit = 100): int
    {
        if (!$this->enabled('review_enabled') || !$this->eventTableExists()) {
            return 0;
        }

        $delayDays = max(1, (int) config('services.customer_lifecycle.review_delay_days', 1));
        $orders = DB::table('pedidos as p')
            ->join('usuarios as u', 'u.id', '=', 'p.usuario_id')
            ->leftJoin('pedido_detalles as d', 'd.pedido_id', '=', 'p.id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'd.producto_id')
            ->leftJoin('customer_email_events as e', function ($join): void {
                $join->on('e.order_id', '=', 'p.id')->where('e.type', '=', 'review');
            })
            ->where('p.estado', 'entregado')
            ->where('u.activo', 1)
            ->whereNull('e.id')
            ->where('p.updated_at', '<=', now()->subDays($delayDays))
            ->groupBy('p.id', 'p.usuario_id', 'p.updated_at', 'u.nombre', 'u.apellido', 'u.email')
            ->select([
                'p.id',
                'p.usuario_id',
                'p.updated_at',
                'u.nombre',
                'u.apellido',
                'u.email',
                DB::raw('MIN(pr.nombre) as product_name'),
            ])
            ->orderBy('p.updated_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        foreach ($orders as $order) {
            $eventKey = 'review-order-' . (int) $order->id;
            $name = $this->safeName($order);
            $productName = trim((string) ($order->product_name ?? '')) ?: 'tu pedido';
            $product = htmlspecialchars(
                $productName,
                ENT_QUOTES,
                'UTF-8'
            );
            $message = rawurlencode(
                'Quiero dejar mi opinion sobre el pedido #' . (int) $order->id
                . ' y el producto ' . (string) ($order->product_name ?? '')
            );
            $feedbackUrl = $this->frontendUrl('/contacto?mensaje=' . $message);

            $html = <<<HTML
                <div style="font-family:Arial,sans-serif;color:#292524;line-height:1.65;max-width:620px;margin:auto">
                    <p style="color:#b45309;font-size:13px;letter-spacing:2px;text-transform:uppercase">Tu opinion nos ayuda</p>
                    <h1 style="font-size:27px;margin:8px 0 18px">{$name}, que te parecio {$product}?</h1>
                    <p>Queremos saber como estuvo la frescura, el sabor y la atencion de tu pedido #{$order->id}. Tu comentario nos ayuda a mejorar cada horneada.</p>
                    <p><a href="{$feedbackUrl}" style="display:inline-block;padding:13px 22px;background:#1c1917;color:#fff;text-decoration:none;border-radius:12px;font-weight:bold">Dejar mi opinion</a></p>
                    <p style="margin-top:24px;color:#78716c;font-size:13px">Gracias por elegir Delicias del centro.</p>
                </div>
                HTML;

            if ($this->send(
                $order,
                'review',
                $eventKey,
                "Que tal estuvo {$productName}?",
                $html,
                (int) $order->id,
                ['product_name' => (string) ($order->product_name ?? '')]
            )) {
                $sent++;
            }
        }

        return $sent;
    }

    private function send(
        object $user,
        string $type,
        string $eventKey,
        string $subject,
        string $html,
        ?int $orderId,
        array $metadata
    ): bool {
        if (!$this->eventTableExists() || $this->eventExists($eventKey)) {
            return false;
        }

        $email = trim((string) ($user->email ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $apiKey = trim((string) config('services.resend.key'));
        $fromAddress = trim((string) config('mail.from.address'));
        $fromName = trim((string) config('mail.from.name', 'Delicias del centro'));
        if ($apiKey === '' || $fromAddress === '' || str_contains($fromAddress, 'example.com')) {
            Log::warning('Customer lifecycle email is not configured.', ['type' => $type]);
            return false;
        }

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders(['Idempotency-Key' => $eventKey])
                ->acceptJson()
                ->timeout(30)
                ->post('https://api.resend.com/emails', [
                    'from' => $fromName . ' <' . $fromAddress . '>',
                    'to' => [$email],
                    'subject' => $subject,
                    'html' => $html,
                    'tags' => [
                        ['name' => 'type', 'value' => $type],
                        ['name' => 'user_id', 'value' => (string) $user->id],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('Resend rejected a customer lifecycle email.', [
                    'type' => $type,
                    'event_key' => $eventKey,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return false;
            }

            DB::table('customer_email_events')->insert([
                'user_id' => (int) $user->id,
                'order_id' => $orderId,
                'type' => $type,
                'event_key' => $eventKey,
                'provider_id' => (string) $response->json('id', ''),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Could not send a customer lifecycle email.', [
                'type' => $type,
                'event_key' => $eventKey,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private function topProducts(): Collection
    {
        return DB::table('pedido_detalles as d')
            ->join('pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'd.producto_id')
            ->where('p.estado', '<>', 'cancelado')
            ->where('p.created_at', '>=', now()->subMonth())
            ->whereNotNull('pr.id')
            ->groupBy('pr.id')
            ->select([
                'pr.id',
                DB::raw('MAX(pr.nombre) as nombre'),
                DB::raw('MAX(pr.precio) as precio'),
                DB::raw('SUM(d.cantidad) as cantidad'),
            ])
            ->orderByDesc('cantidad')
            ->limit(3)
            ->get();
    }

    private function productsHtml(Collection $products): string
    {
        if ($products->isEmpty()) {
            return '';
        }

        $items = $products->map(function ($product): string {
            $name = htmlspecialchars((string) $product->nombre, ENT_QUOTES, 'UTF-8');
            $price = number_format((float) $product->precio, 2);
            $url = $this->frontendUrl('/productos/' . (int) $product->id);

            return '<li style="margin:8px 0"><a href="' . $url . '" style="color:#b45309;font-weight:bold">'
                . $name . '</a> - S/ ' . $price . '</li>';
        })->implode('');

        return '<div style="margin:20px 0"><strong>Favoritos del mes</strong><ul style="padding-left:20px">' . $items . '</ul></div>';
    }

    private function safeName(object $user): string
    {
        $name = trim((string) ($user->nombre ?? ''));
        return htmlspecialchars($name !== '' ? $name : 'cliente', ENT_QUOTES, 'UTF-8');
    }

    private function frontendUrl(string $path): string
    {
        return htmlspecialchars(
            rtrim((string) config('services.frontend.url'), '/') . '/' . ltrim($path, '/'),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    private function eventExists(string $eventKey): bool
    {
        return DB::table('customer_email_events')->where('event_key', $eventKey)->exists();
    }

    private function eventTableExists(): bool
    {
        return Schema::hasTable('customer_email_events');
    }

    private function enabled(string $key): bool
    {
        return (bool) config('services.customer_lifecycle.enabled', true)
            && (bool) config('services.customer_lifecycle.' . $key, true);
    }
}
