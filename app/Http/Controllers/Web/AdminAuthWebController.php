<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuthWebController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('web_admin')) {
            return redirect()->route('web.admin.dashboard');
        }

        return view('admin.auth.login', ['title' => 'Login administrador']);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $response = $this->api->post('auth/admin/login', $data);
        if (!$response->successful()) {
            $request->session()->forget(['web_admin', 'auth_token', 'auth_tipo']);
            return back()->withInput($request->except('password'))->withErrors([
                'email' => $this->api->errorMessage($response, 'Email o contrasena incorrectos.'),
            ]);
        }
        $payload = $response->json();
        $admin = (array) data_get($payload, 'admin', []);
        $token = (string) data_get($payload, 'token', '');
        if ($token === '') {
            $request->session()->forget(['web_admin', 'auth_token', 'auth_tipo']);
            return back()->withInput($request->except('password'))->withErrors([
                'email' => 'El backend no emitio un JWT valido para administrador.',
            ]);
        }

        $request->session()->put([
            'web_admin' => [
                'id' => (int) ($admin['id'] ?? 0),
                'nombre' => (string) ($admin['nombre'] ?? ''),
                'email' => (string) ($admin['email'] ?? ''),
                'rol' => (string) ($admin['rol'] ?? 'admin'),
            ],
            'auth_token' => $token,
            'auth_tipo' => 'admin',
        ]);
        $request->session()->regenerate();

        return redirect()->route('web.admin.dashboard')->with('success', 'Bienvenido al panel administrativo.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['web_admin', 'auth_token', 'auth_tipo']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.admin.login')->with('success', 'Sesion de administrador cerrada.');
    }
}
