<?php

namespace App\Http\Middleware;

use App\Services\BackendApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebUserAuthenticated
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('web_user');
        $token = (string) $request->session()->get('auth_token', '');
        $tipo = (string) $request->session()->get('auth_tipo', '');

        if (!$user || $token === '' || $tipo !== 'usuario') {
            $request->session()->forget(['web_user', 'auth_token', 'auth_tipo']);
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Tu sesión expiró. Inicia sesión nuevamente.',
                ], 401);
            }

            return redirect()->route('web.login')
                ->with('error', 'Debes iniciar sesión para continuar.');
        }

        $response = $this->api->get('auth/verify');
        if (!$response->successful() || data_get($response->json(), 'tipo') !== 'usuario') {
            $request->session()->forget(['web_user', 'auth_token', 'auth_tipo']);
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Tu sesión expiró. Inicia sesión nuevamente.',
                ], 401);
            }

            return redirect()->route('web.login')
                ->with('error', 'Tu sesión expiró. Inicia sesión nuevamente.');
        }

        return $next($request);
    }
}
