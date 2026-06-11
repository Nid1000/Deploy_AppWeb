<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTipo
{
    public function handle(Request $request, Closure $next, string $tipo): Response
    {
        $user = $request->attributes->get('user');
        $actual = is_array($user) ? ($user['tipo'] ?? null) : null;

        if ($actual !== $tipo) {
            return response()->json([
                'statusCode' => 403,
                'error' => 'Acceso denegado',
                'message' => $tipo === 'admin'
                    ? 'Acceso denegado: se requiere rol administrador'
                    : 'Acceso denegado: se requiere usuario autenticado',
            ], 403);
        }

        return $next($request);
    }
}

