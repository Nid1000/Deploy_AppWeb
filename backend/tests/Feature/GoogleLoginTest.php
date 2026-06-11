<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'frontend-client.apps.googleusercontent.com');
        putenv('JWT_SECRET=test-jwt-secret-with-enough-random-characters');

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('telefono')->nullable();
            $table->text('direccion')->nullable();
            $table->string('distrito')->nullable();
            $table->string('numero_casa')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('usuarios');
        putenv('JWT_SECRET');

        parent::tearDown();
    }

    public function test_existing_active_user_can_login_with_google(): void
    {
        DB::table('usuarios')->insert([
            'nombre' => 'Maria',
            'apellido' => 'Perez',
            'email' => 'maria@example.com',
            'password' => 'not-used-by-google-login',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->fakeGoogleProfile('maria@example.com');

        $this->postJson('/api/auth/google', ['id_token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('user.email', 'maria@example.com')
            ->assertJsonPath('message', 'Login con Google exitoso')
            ->assertJsonStructure(['token']);
    }

    public function test_google_login_does_not_create_unknown_users(): void
    {
        $this->fakeGoogleProfile('unknown@example.com');

        $this->postJson('/api/auth/google', ['id_token' => 'valid-token'])
            ->assertNotFound()
            ->assertJsonPath('error', 'Cuenta no encontrada');

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_google_login_rejects_a_token_for_another_client(): void
    {
        $this->fakeGoogleProfile(
            'maria@example.com',
            'another-client.apps.googleusercontent.com'
        );

        $this->postJson('/api/auth/google', ['id_token' => 'wrong-audience'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Token invalido');
    }

    private function fakeGoogleProfile(
        string $email,
        string $audience = 'frontend-client.apps.googleusercontent.com'
    ): void {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => $audience,
                'iss' => 'https://accounts.google.com',
                'email' => $email,
                'email_verified' => 'true',
            ]),
        ]);
    }
}
