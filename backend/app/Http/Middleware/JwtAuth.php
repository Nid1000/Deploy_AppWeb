<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = (string) $request->header('Authorization', '');
        if (!str_starts_with($auth, 'Bearer ')) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'No autorizado',
                'message' => 'Token requerido',
            ], 401);
        }

        $token = trim(substr($auth, 7));
        if ($token === '') {
            return response()->json([
                'statusCode' => 401,
                'error' => 'No autorizado',
                'message' => 'Token requerido',
            ], 401);
        }

        try {
            $payload = app(JwtService::class)->verify($token);
        } catch (\Throwable $e) {
            return response()->json([
                'statusCode' => 401,
                'error' => 'Token inválido',
                'message' => 'Token inválido o expirado',
            ], 401);
        }

        // Compat: req.user en Nest. En Laravel lo guardamos en attributes.
        $request->attributes->set('user', $payload);

        return $next($request);
    }
}

