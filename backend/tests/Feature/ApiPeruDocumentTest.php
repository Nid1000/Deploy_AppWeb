<?php

namespace Tests\Feature;

use App\Services\JwtService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiPeruDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.jwt.secret' => 'test-jwt-secret-with-enough-random-characters',
            'services.documents.provider' => 'apiperu',
            'services.documents.validation_required' => true,
            'services.documents.apiperu.token' => 'api-peru-test-token',
            'services.documents.apiperu.base_url' => 'https://dniruc.apisperu.com/api/v1',
        ]);
    }

    public function test_dni_lookup_uses_cached_api_peru_configuration(): void
    {
        Http::fake([
            'https://dniruc.apisperu.com/api/v1/dni/12345678*' => Http::response([
                'dni' => '12345678',
                'nombres' => 'MARIA',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
            ]),
        ]);

        $this->withToken($this->userToken())
            ->getJson('/api/facturacion/consulta-dni?numero=12345678')
            ->assertOk()
            ->assertJsonPath('proveedor', 'APIPERU')
            ->assertJsonPath('data.nombre_completo', 'MARIA PEREZ LOPEZ');

        Http::assertSent(function ($request): bool {
            return str_starts_with(
                $request->url(),
                'https://dniruc.apisperu.com/api/v1/dni/12345678'
            ) && $request->data()['token'] === 'api-peru-test-token';
        });
    }

    public function test_api_peru_camel_case_names_are_normalized(): void
    {
        Http::fake([
            'https://dniruc.apisperu.com/api/v1/dni/76047284*' => Http::response([
                'dni' => '76047284',
                'nombres' => 'JUAN CARLOS',
                'apellidoPaterno' => 'PEREZ',
                'apellidoMaterno' => 'LOPEZ',
            ]),
        ]);

        $this->withToken($this->userToken())
            ->getJson('/api/facturacion/consulta-dni?numero=76047284')
            ->assertOk()
            ->assertJsonPath('data.nombre_completo', 'JUAN CARLOS PEREZ LOPEZ');
    }

    private function userToken(): string
    {
        return app(JwtService::class)->sign([
            'id' => 1,
            'email' => 'maria@example.com',
            'tipo' => 'usuario',
        ]);
    }
}
