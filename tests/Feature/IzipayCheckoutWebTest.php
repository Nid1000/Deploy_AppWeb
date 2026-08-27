<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IzipayCheckoutWebTest extends TestCase
{
    public function test_izipay_payment_keeps_receipt_data_for_issuance_after_confirmation(): void
    {
        config()->set('services.backend.url', 'https://api.saborcentral.com');
        config()->set('services.izipay.test_mode', true);

        Http::fake([
            '*/api/auth/verify' => Http::response(['tipo' => 'usuario']),
            '*/api/facturacion/consulta-dni*' => Http::response([
                'validacion_real' => true,
                'data' => ['nombre_completo' => 'CLIENTE DE PRUEBA'],
            ]),
            '*/api/pedidos' => Http::response(['pedido' => ['id' => 123]], 201),
            '*/api/pagos/izipay/crear' => Http::response([
                'formToken' => 'test-form-token',
                'publicKey' => 'test-public-key',
                'successUrl' => 'https://api.saborcentral.com/api/pagos/izipay/confirmar',
                'cancelUrl' => 'https://api.saborcentral.com/api/pagos/izipay/cancelado',
                'testMode' => true,
            ]),
        ]);

        $response = $this
            ->withSession([
                'web_user' => ['id' => 1],
                'auth_token' => 'test-auth-token',
                'auth_tipo' => 'usuario',
                'storefront_cart' => [[
                    'id' => 10,
                    'nombre' => 'Producto de prueba',
                    'precio' => 1,
                    'cantidad' => 1,
                    'stock' => 10,
                ]],
            ])
            ->post('/checkout', [
                'fecha_entrega' => now()->addDays(2)->format('Y-m-d'),
                'direccion_entrega' => 'Calle de prueba 123',
                'distrito_entrega' => 'Huancayo',
                'numero_casa_entrega' => '123',
                'telefono_contacto' => '999999999',
                'comprobante_tipo' => 'boleta',
                'tipo_documento' => 'DNI',
                'numero_documento' => '12345678',
                'metodo_pago' => 'izipay',
                'acepta_pago' => '1',
            ]);

        $response
            ->assertOk()
            ->assertViewHas('izipayPayment')
            ->assertSee('Ambiente de prueba');

        $this->assertSame(1, session('storefront_cart.0.cantidad'));
        $this->assertSame(
            [['id' => 10, 'cantidad' => 1]],
            session('pending_izipay_orders.123')
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.saborcentral.com/api/pagos/izipay/crear'
                && $request['pedido_id'] === 123
                && $request['comprobante_tipo'] === 'boleta'
                && $request['tipo_documento'] === 'DNI'
                && $request['numero_documento'] === '12345678'
                && $request['cliente_nombre'] === 'CLIENTE DE PRUEBA'
                && $request['cliente']['nombre'] === 'CLIENTE DE PRUEBA'
                && $request['client']['rznSocial'] === 'CLIENTE DE PRUEBA'
                && $request['emitir_comprobante_al_confirmar'] === true
                && $request['modo_prueba'] === true;
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.saborcentral.com/api/pedidos'
                && $request['comprobante_tipo'] === 'boleta'
                && $request['tipo_documento'] === 'DNI'
                && $request['numero_documento'] === '12345678'
                && $request['cliente_nombre'] === 'CLIENTE DE PRUEBA'
                && $request['cliente']['nombre'] === 'CLIENTE DE PRUEBA'
                && $request['client']['rznSocial'] === 'CLIENTE DE PRUEBA';
        });
    }

    public function test_paid_order_consumes_only_the_items_saved_for_that_payment(): void
    {
        Http::fake([
            '*/api/auth/verify' => Http::response(['tipo' => 'usuario']),
            '*/api/pedidos/123' => Http::response([
                'pedido' => [
                    'id' => 123,
                    'estado' => 'confirmado',
                    'estado_pago' => 'pagado',
                ],
                'detalles' => [],
            ]),
            '*/api/facturacion/mis-comprobantes' => Http::response(['comprobantes' => []]),
        ]);

        $response = $this
            ->withSession([
                'web_user' => ['id' => 1],
                'auth_token' => 'test-auth-token',
                'auth_tipo' => 'usuario',
                'storefront_cart' => [[
                    'id' => 10,
                    'nombre' => 'Producto de prueba',
                    'precio' => 1,
                    'cantidad' => 3,
                    'stock' => 10,
                ]],
                'pending_izipay_orders' => [
                    '123' => [['id' => 10, 'cantidad' => 1]],
                ],
            ])
            ->get('/orders/123');

        $response->assertOk();
        $this->assertSame(2, session('storefront_cart.0.cantidad'));
        $this->assertSame([], session('pending_izipay_orders'));
    }
}
