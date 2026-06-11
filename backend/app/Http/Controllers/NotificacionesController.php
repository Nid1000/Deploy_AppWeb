<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\NotificacionesService;
use Illuminate\Validation\ValidationException;

class NotificacionesController extends Controller
{
    public function __construct(private readonly NotificacionesService $notificaciones)
    {
    }

    public function pendientes(Request $request)
    {
        $payload = $request->attributes->get('user');
        $userId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $canal = $request->query('canal');
        $channel = $canal === 'web' ? 'web' : 'mobile';
        $items = $this->notificaciones->getPendingForUser($userId, $channel);

        return response()->json([
            'statusCode' => 200,
            'notificaciones' => $items,
        ], 200);
    }

    public function marcarMostradas(Request $request)
    {
        $payload = $request->attributes->get('user');
        $userId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        try {
            $data = $request->validate([
                'ids' => ['nullable', 'array'],
                'ids.*' => ['integer', 'min:1'],
                'canal' => ['nullable', 'in:web,mobile'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $channel = ($data['canal'] ?? 'mobile') === 'web' ? 'web' : 'mobile';
        $this->notificaciones->markShown($userId, $data['ids'] ?? [], $channel);

        return response()->json(['statusCode' => 200, 'ok' => true], 200);
    }

    public function adminEnviar(Request $request)
    {
        $title = trim((string) $request->input('title', ''));
        $message = trim((string) $request->input('message', ''));
        $route = $request->input('route');
        $targetId = $request->input('targetId');
        $userId = $request->input('userId');
        $audience = $request->input('audience', 'both');

        if ($title === '' || $message === '') {
            return response()->json([
                'statusCode' => 400,
                'message' => 'Titulo y mensaje son obligatorios',
            ], 400);
        }

        $aud = in_array($audience, ['web', 'mobile', 'both'], true) ? $audience : 'both';

        if ($userId && (int) $userId > 0) {
            $this->notificaciones->createForUser([
                'userId' => (int) $userId,
                'title' => $title,
                'body' => $message,
                'type' => 'manual',
                'audience' => $aud,
                'route' => $route ? trim((string) $route) : null,
                'targetId' => $targetId !== null ? (string) $targetId : null,
            ]);

            return response()->json(['statusCode' => 200, 'ok' => true, 'scope' => 'user'], 200);
        }

        $this->notificaciones->broadcastManual([
            'title' => $title,
            'body' => $message,
            'type' => 'manual',
            'audience' => $aud,
            'route' => $route ? trim((string) $route) : null,
            'targetId' => $targetId !== null ? (string) $targetId : null,
        ]);

        return response()->json(['statusCode' => 200, 'ok' => true, 'scope' => 'all'], 200);
    }

    public function adminPendientes(Request $request)
    {
        $payload = $request->attributes->get('user');
        $adminId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $items = $this->notificaciones->getPendingForAdmin($adminId);

        return response()->json([
            'statusCode' => 200,
            'notificaciones' => $items,
        ], 200);
    }

    public function adminMarcarMostradas(Request $request)
    {
        $payload = $request->attributes->get('user');
        $adminId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        try {
            $data = $request->validate([
                'ids' => ['nullable', 'array'],
                'ids.*' => ['integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $this->notificaciones->markShownAdmin($adminId, $data['ids'] ?? []);
        return response()->json(['statusCode' => 200, 'ok' => true], 200);
    }
}
