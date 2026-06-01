<?php

namespace App\Http\Middleware;

use App\Services\BackendApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebAdminAuthenticated
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->session()->get('web_admin');
        $token = (string) $request->session()->get('auth_token', '');
        $tipo = (string) $request->session()->get('auth_tipo', '');

        if (!$admin || $token === '' || $tipo !== 'admin') {
            $request->session()->forget(['web_admin', 'auth_token', 'auth_tipo']);
            return redirect()->route('web.admin.login')
                ->with('error', 'Debes iniciar sesion como administrador.');
        }

        $response = $this->api->get('auth/verify');
        if (!$response->successful() || data_get($response->json(), 'tipo') !== 'admin') {
            $request->session()->forget(['web_admin', 'auth_token', 'auth_tipo']);
            return redirect()->route('web.admin.login')
                ->with('error', 'Tu sesion de administrador expiro. Inicia sesion nuevamente.');
        }

        return $next($request);
    }
}
