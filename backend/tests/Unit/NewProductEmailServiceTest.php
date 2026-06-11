<?php

namespace Tests\Unit;

use App\Services\NewProductEmailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NewProductEmailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.product_notifications.email_enabled' => true,
            'services.resend.key' => 're_test_key',
            'services.frontend.url' => 'https://delicias.saborcentral.com',
            'mail.from.address' => 'no-reply@saborcentral.com',
            'mail.from.name' => 'Delicias del centro',
            'app.url' => 'https://api.saborcentral.com',
        ]);

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('usuarios');

        parent::tearDown();
    }

    public function test_it_sends_an_individual_batch_message_to_each_active_client(): void
    {
        DB::table('usuarios')->insert([
            ['nombre' => 'Maria', 'email' => 'maria@example.com', 'activo' => true],
            ['nombre' => 'Jose', 'email' => 'jose@example.com', 'activo' => true],
            ['nombre' => 'Inactivo', 'email' => 'inactive@example.com', 'activo' => false],
        ]);

        Http::fake([
            'https://api.resend.com/emails/batch' => Http::response([
                'data' => [['id' => 'one'], ['id' => 'two']],
            ]),
        ]);

        $result = app(NewProductEmailService::class)->send((object) [
            'id' => 55,
            'nombre' => 'Torta de chocolate',
            'descripcion' => 'Nueva receta artesanal.',
            'precio' => 55,
            'imagen' => 'productos/torta.jpg',
        ]);

        $this->assertSame(2, $result['sent']);
        $this->assertSame(0, $result['failed']);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.resend.com/emails/batch'
                && $request->hasHeader('Idempotency-Key', 'new-product-55-batch-0')
                && count($payload) === 2
                && $payload[0]['to'] === ['maria@example.com']
                && $payload[1]['to'] === ['jose@example.com']
                && !str_contains(json_encode($payload), 'inactive@example.com');
        });
    }

    public function test_it_can_be_disabled_from_configuration(): void
    {
        config()->set('services.product_notifications.email_enabled', false);
        Http::fake();

        $result = app(NewProductEmailService::class)->send((object) [
            'id' => 55,
            'nombre' => 'Torta de chocolate',
        ]);

        $this->assertFalse($result['enabled']);
        Http::assertNothingSent();
    }
}
