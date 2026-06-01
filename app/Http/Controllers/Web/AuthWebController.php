<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthWebController extends Controller
{
    private const DEFAULT_DISTRICTS = [
        ['id' => 1, 'nombre' => 'Huancayo'],
        ['id' => 2, 'nombre' => 'El Tambo'],
        ['id' => 3, 'nombre' => 'Chilca'],
        ['id' => 4, 'nombre' => 'Carhuacallanga'],
        ['id' => 5, 'nombre' => 'Cullhuas'],
        ['id' => 6, 'nombre' => 'Chacapampa'],
        ['id' => 7, 'nombre' => 'Chicche'],
        ['id' => 8, 'nombre' => 'Chongos Alto'],
        ['id' => 9, 'nombre' => 'Chupuro'],
        ['id' => 10, 'nombre' => 'Colca'],
        ['id' => 11, 'nombre' => 'Huacrapuquio'],
        ['id' => 12, 'nombre' => 'Hualhuas'],
        ['id' => 13, 'nombre' => 'Huancan'],
        ['id' => 14, 'nombre' => 'Huasicancha'],
        ['id' => 15, 'nombre' => 'Huayucachi'],
        ['id' => 16, 'nombre' => 'Ingenio'],
        ['id' => 17, 'nombre' => 'Pariahuanca'],
        ['id' => 18, 'nombre' => 'Pilcomayo'],
        ['id' => 19, 'nombre' => 'Pucara'],
        ['id' => 20, 'nombre' => 'Quichuay'],
        ['id' => 21, 'nombre' => 'Quilcas'],
        ['id' => 22, 'nombre' => 'Santo Domingo de Acobamba'],
        ['id' => 23, 'nombre' => 'Sano'],
        ['id' => 24, 'nombre' => 'Sapallanga'],
        ['id' => 25, 'nombre' => 'Sicaya'],
        ['id' => 26, 'nombre' => 'Viques'],
        ['id' => 27, 'nombre' => 'San Agustin de Cajas'],
    ];

    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('web_user')) {
            return redirect()->route('web.home');
        }

        return view('web.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Ingresa un email valido.',
            'password.required' => 'La contrasena es obligatoria.',
        ]);

        $response = $this->api->post('auth/login', $credentials);
        if (!$response->successful()) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => 'Email o contrasena incorrectos.']);
        }
        $payload = $response->json();
        $user = (array) data_get($payload, 'user', []);

        $sessionUser = [
            'id' => (int) ($user['id'] ?? 0),
            'nombre' => (string) ($user['nombre'] ?? ''),
            'apellido' => (string) ($user['apellido'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'telefono' => $user['telefono'] ?? null,
            'direccion' => $user['direccion'] ?? null,
            'distrito' => $user['distrito'] ?? null,
            'numero_casa' => $user['numero_casa'] ?? null,
        ];

        $request->session()->put([
            'web_user' => $sessionUser,
            'auth_token' => (string) data_get($payload, 'token', ''),
            'auth_tipo' => 'usuario',
        ]);

        $request->session()->regenerate();

        return redirect()->route('web.home')->with('success', 'Bienvenido de nuevo.');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('web_user')) {
            return redirect()->route('web.home');
        }

        $districtsResponse = $this->api->get('usuarios/distritos-huancayo');
        $districts = $this->mapDistricts($this->api->okData($districtsResponse, 'distritos', []));
        if ($districts->isEmpty()) {
            $districts = $this->mapDistricts(self::DEFAULT_DISTRICTS);
        }

        return view('web.auth.register', [
            'distritos' => $districts,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:191'],
            'apellido' => ['required', 'string', 'min:2', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'confirmed', PasswordRules::userPassword()],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['required', 'string'],
            'distrito' => ['required', 'string', 'min:2', 'max:120'],
            'numero_casa' => ['required', 'string', 'max:20'],
        ], [
            'password.confirmed' => 'La confirmacion de contrasena no coincide.',
        ]);

        $response = $this->api->post('auth/register', [
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'email' => $data['email'],
            'password' => $data['password'],
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'],
            'distrito' => $data['distrito'],
            'numero_casa' => $data['numero_casa'],
        ]);

        if (!$response->successful()) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => $this->api->errorMessage($response, 'No se pudo crear la cuenta.')]);
        }
        $payload = $response->json();
        $user = (array) data_get($payload, 'user', []);

        $sessionUser = [
            'id' => (int) ($user['id'] ?? 0),
            'nombre' => (string) ($user['nombre'] ?? $data['nombre']),
            'apellido' => (string) ($user['apellido'] ?? $data['apellido']),
            'email' => (string) ($user['email'] ?? $data['email']),
            'telefono' => $user['telefono'] ?? ($data['telefono'] ?? null),
            'direccion' => $user['direccion'] ?? $data['direccion'],
            'distrito' => $user['distrito'] ?? $data['distrito'],
            'numero_casa' => $user['numero_casa'] ?? $data['numero_casa'],
        ];

        $request->session()->put([
            'web_user' => $sessionUser,
            'auth_token' => (string) data_get($payload, 'token', ''),
            'auth_tipo' => 'usuario',
        ]);

        $request->session()->regenerate();

        return redirect()->route('web.home')->with('success', 'Tu cuenta fue creada correctamente.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['web_user', 'auth_token', 'auth_tipo']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.login')->with('success', 'Sesion cerrada correctamente.');
    }

    private function mapDistricts(mixed $districts): \Illuminate\Support\Collection
    {
        return collect($districts)->map(fn ($district) => is_array($district) ? (object) $district : $district)->values();
    }
}
