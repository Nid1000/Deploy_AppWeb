<?php

namespace Tests\Unit;

use App\Services\CustomerLifecycleEmailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerLifecycleEmailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.customer_lifecycle.enabled' => true,
            'services.customer_lifecycle.welcome_enabled' => true,
            'services.customer_lifecycle.welcome_offer' => '',
            'services.customer_lifecycle.welcome_retry_days' => 7,
            'services.customer_lifecycle.dormant_enabled' => true,
            'services.customer_lifecycle.dormant_days' => 30,
            'services.customer_lifecycle.dormant_offer' => '',
            'services.customer_lifecycle.review_enabled' => true,
            'services.customer_lifecycle.review_delay_days' => 1,
            'services.resend.key' => 're_test_key',
            'services.frontend.url' => 'https://delicias.saborcentral.com',
            'mail.from.address' => 'no-reply@saborcentral.com',
            'mail.from.name' => 'Delicias del centro',
        ]);

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('apellido')->nullable();
            $table->string('email');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pedidos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->decimal('total', 10, 2)->default(0);
            $table->string('estado');
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->decimal('precio', 10, 2);
        });

        Schema::create('pedido_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pedido_id');
            $table->unsignedBigInteger('producto_id');
            $table->integer('cantidad');
            $table->decimal('subtotal', 10, 2);
        });

        Schema::create('customer_email_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('type');
            $table->string('event_key')->unique();
            $table->string('provider_id')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
        });

        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'email_123']),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customer_email_events');
        Schema::dropIfExists('pedido_detalles');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('usuarios');

        parent::tearDown();
    }

    public function test_welcome_email_is_sent_only_once(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'nombre' => 'Maria',
            'apellido' => 'Lopez',
            'email' => 'maria@example.com',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = DB::table('usuarios')->where('id', $userId)->first();
        $service = app(CustomerLifecycleEmailService::class);

        $this->assertTrue($service->sendWelcome($user));
        $this->assertFalse($service->sendWelcome($user));
        $this->assertSame(1, DB::table('customer_email_events')->where('type', 'welcome')->count());

        Http::assertSentCount(1);
    }

    public function test_review_request_is_sent_for_an_old_delivered_order(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'nombre' => 'Jose',
            'apellido' => 'Rojas',
            'email' => 'jose@example.com',
            'activo' => true,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        $productId = DB::table('productos')->insertGetId([
            'nombre' => 'Pan artesanal',
            'precio' => 8.5,
        ]);
        $orderId = DB::table('pedidos')->insertGetId([
            'usuario_id' => $userId,
            'total' => 8.5,
            'estado' => 'entregado',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(2),
        ]);
        DB::table('pedido_detalles')->insert([
            'pedido_id' => $orderId,
            'producto_id' => $productId,
            'cantidad' => 1,
            'subtotal' => 8.5,
        ]);

        $sent = app(CustomerLifecycleEmailService::class)->processReviewRequests();

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('customer_email_events', [
            'event_key' => 'review-order-' . $orderId,
            'type' => 'review',
        ]);

        Http::assertSent(fn ($request): bool => str_contains(
            (string) data_get($request->data(), 'html'),
            'Pan artesanal'
        ));
    }
}
