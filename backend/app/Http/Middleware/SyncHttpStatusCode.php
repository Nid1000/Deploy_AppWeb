<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

/**
 * Sincroniza el HTTP status con el campo `statusCode` del JSON.
 *
 * Esta API (por compatibilidad con Nest) incluye `statusCode` en el body.
 * En algunos entornos el status HTTP puede quedar en 200; este middleware
 * fuerza el código correcto si existe `statusCode` y es válido.
 */
class SyncHttpStatusCode
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!($response instanceof JsonResponse)) {
            return $response;
        }

        // No tocar respuestas sin body JSON.
        $data = $response->getData(true);
        if (!is_array($data) || !isset($data['statusCode'])) {
            return $response;
        }

        $code = $data['statusCode'];
        if (is_string($code) && ctype_digit($code)) {
            $code = (int) $code;
        }

        if (!is_int($code) || $code < 100 || $code > 599) {
            return $response;
        }

        if ($response->getStatusCode() !== $code) {
            $response->setStatusCode($code);
        }

        return $response;
    }
}
