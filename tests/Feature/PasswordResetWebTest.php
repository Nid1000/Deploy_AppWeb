<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PasswordResetWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.backend.url', 'https://api.saborcentral.com');
        Http::fake([
            '*' => Http::response([], 200),
        ]);
    }

    public function test_forgot_password_page_is_available(): void
    {
        $this->get('/password/forgot')
            ->assertOk()
            ->assertSee('Olvidaste tu contrasena?');
    }

    public function test_reset_password_page_keeps_token_in_form(): void
    {
        $this->get('/password/reset?token=test-token')
            ->assertOk()
            ->assertSee('Crea una nueva contrasena')
            ->assertSee('test-token');
    }

    public function test_forgot_password_request_is_forwarded_to_backend(): void
    {
        Http::fake([
            'https://api.saborcentral.com/api/auth/password/forgot' => Http::response([
                'message' => 'Solicitud recibida',
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $this->post('/password/forgot', [
            'email' => 'maria@example.com',
        ])->assertSessionHas('success');

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://api.saborcentral.com/api/auth/password/forgot');
    }
}
