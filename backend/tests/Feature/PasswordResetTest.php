<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.jwt.secret' => 'test-jwt-secret-with-enough-random-characters',
            'services.frontend.url' => 'https://delicias.saborcentral.com',
            'services.password_reset.ttl_minutes' => 30,
            'services.resend.key' => 're_test_key',
            'mail.from.address' => 'cuentas@saborcentral.com',
            'mail.from.name' => 'Delicias del centro',
        ]);

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::table('usuarios')->insert([
            'nombre' => 'Maria',
            'apellido' => 'Perez',
            'email' => 'maria@example.com',
            'password' => Hash::make('Anterior1'),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('usuarios');

        parent::tearDown();
    }

    public function test_active_user_receives_a_password_reset_link(): void
    {
        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'email-id'], 200),
        ]);

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'maria@example.com',
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            $html = (string) $request['html'];

            return $request->url() === 'https://api.resend.com/emails'
                && in_array('maria@example.com', $request['to'], true)
                && str_contains($html, 'https://delicias.saborcentral.com/password/reset?token=');
        });
    }

    public function test_unknown_email_returns_the_same_generic_response_without_sending_mail(): void
    {
        Http::fake();

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'unknown@example.com',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Si el correo pertenece a una cuenta activa, recibiras un enlace para restablecer tu contrasena.'
            );

        Http::assertNothingSent();
    }

    public function test_valid_link_changes_password_and_cannot_be_reused(): void
    {
        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'email-id'], 200),
        ]);

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'maria@example.com',
        ])->assertOk();

        $token = null;
        Http::assertSent(function ($request) use (&$token): bool {
            preg_match('/password\/reset\?token=([^"&]+)/', (string) $request['html'], $matches);
            $token = isset($matches[1]) ? urldecode($matches[1]) : null;

            return is_string($token) && $token !== '';
        });

        $payload = [
            'token' => $token,
            'password' => 'NuevaClave1',
            'password_confirmation' => 'NuevaClave1',
        ];

        $this->postJson('/api/auth/password/reset', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Tu contrasena fue actualizada. Ya puedes iniciar sesion.');

        $storedPassword = DB::table('usuarios')->where('email', 'maria@example.com')->value('password');
        $this->assertTrue(Hash::check('NuevaClave1', $storedPassword));

        $this->postJson('/api/auth/password/reset', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Enlace invalido');
    }
}
