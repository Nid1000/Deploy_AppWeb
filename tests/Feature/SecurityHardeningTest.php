<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_public_storage_requires_user_session(): void
    {
        $this->get('/storage')
            ->assertRedirect(route('web.login'));
    }

    public function test_cart_redirect_to_external_host_is_rejected(): void
    {
        config()->set('services.backend.url', 'https://api.saborcentral.com');

        Http::fake([
            '*/api/productos/10' => Http::response([
                'id' => 10,
                'nombre' => 'Producto de prueba',
                'precio' => 12.5,
                'stock' => 8,
            ]),
        ]);

        $this->post('/carrito/agregar', [
            'producto_id' => 10,
            'cantidad' => 1,
            'redirect_to' => 'https://example.com/phishing',
        ])->assertRedirect(route('web.products'));
    }

    public function test_tickets_endpoint_requires_configured_token(): void
    {
        config()->set('services.tickets.token', 'secret-token');

        $this->postJson('/api/tickets', [
            'total' => 10,
        ])->assertUnauthorized();
    }

    public function test_tickets_endpoint_accepts_matching_token(): void
    {
        config()->set('services.tickets.token', 'secret-token');
        config()->set('services.backend.url', 'https://api.saborcentral.com');

        Http::fake([
            '*/api/tickets' => Http::response([
                'message' => 'OK',
                'comprobante' => [
                    'serie' => 'B001',
                    'numero' => '1',
                    'total' => 10,
                    'cliente' => ['nombre' => 'Cliente'],
                ],
            ], 201),
        ]);

        $this->withHeader('X-Tickets-Token', 'secret-token')
            ->postJson('/api/tickets', [
                'total' => 10,
                'cliente' => 'Cliente',
            ])
            ->assertCreated()
            ->assertJsonPath('comprobante.total', 10);
    }
}
