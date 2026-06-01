<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileWebController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function show(Request $request): View
    {
        $sessionUser = $request->session()->get('web_user');

        $profileResponse = $this->api->get('usuarios/perfil');
        abort_unless($profileResponse->successful(), 404);
        $statsResponse = $this->api->get('usuarios/estadisticas');
        $districtsResponse = $this->api->get('usuarios/distritos-huancayo');

        $user = (object) $this->api->okData($profileResponse, 'usuario', []);
        $statsPayload = (array) $this->api->okData($statsResponse, 'estadisticas', []);
        $stats = [
            'total_pedidos' => (int) ($statsPayload['total_pedidos'] ?? 0),
            'total_gastado' => (float) ($statsPayload['total_gastado'] ?? 0),
        ];

        return view('web.profile.show', [
            'user' => $user,
            'stats' => $stats,
            'distritos' => $this->mapDistricts($this->api->okData($districtsResponse, 'distritos', [])),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $sessionUser = $request->session()->get('web_user');
        $userId = (int) ($sessionUser['id'] ?? 0);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2'],
            'apellido' => ['required', 'string', 'min:2'],
            'telefono' => ['nullable', 'regex:/^9\d{8}$/'],
            'direccion' => ['required', 'string', 'min:5'],
            'distrito' => ['required', 'string', 'min:2'],
            'numero_casa' => ['required', 'string', 'min:1'],
        ]);

        $response = $this->api->put('usuarios/perfil', $data);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar el perfil.'));
        }
        $updated = (object) $this->api->okData($response, 'usuario', []);

        $request->session()->put('web_user', [
            'id' => (int) $updated->id,
            'nombre' => (string) $updated->nombre,
            'apellido' => (string) $updated->apellido,
            'email' => (string) $updated->email,
            'telefono' => $updated->telefono,
            'direccion' => $updated->direccion,
            'distrito' => $updated->distrito,
            'numero_casa' => $updated->numero_casa,
        ]);

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password_actual' => ['required', 'string'],
            'password_nueva' => ['required', 'confirmed', 'different:password_actual', PasswordRules::userPassword()],
        ], [
            'password_nueva.confirmed' => 'La confirmacion de contrasena no coincide.',
            'password_nueva.different' => 'La nueva contrasena debe ser diferente a la actual.',
        ]);

        $response = $this->api->put('usuarios/cambiar-password', [
            'passwordActual' => $data['password_actual'],
            'passwordNueva' => $data['password_nueva'],
            'confirmarPassword' => $request->input('password_nueva_confirmation'),
        ]);
        if (!$response->successful()) {
            return back()->withErrors(['password_actual' => $this->api->errorMessage($response, 'No se pudo actualizar la contrasena.')]);
        }

        return back()->with('success', 'Contrasena actualizada correctamente.');
    }

    public function markNotificationsSeen(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $response = $this->api->post('notificaciones/marcar-mostradas', [
            'ids' => $data['ids'] ?? [],
            'canal' => 'web',
        ]);

        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudieron marcar las notificaciones.'));
        }

        return back();
    }

    private function mapDistricts(mixed $districts): \Illuminate\Support\Collection
    {
        return collect($districts)->map(fn ($district) => is_array($district) ? (object) $district : $district)->values();
    }
}
