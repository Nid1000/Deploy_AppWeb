<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificacionesService
{
    private bool $ready = false;
    private bool $available = false;

    private function ensureTable(): bool
    {
        if ($this->ready) {
            return $this->available;
        }

        $this->available = Schema::hasTable('notificaciones_app');

        if (!$this->available) {
            Log::warning('Tabla notificaciones_app no encontrada. Ejecuta las migraciones del backend.');
        }

        $this->ready = true;
        return $this->available;
    }

    public function createForUser(array $params): void
    {
        if (!$this->ensureTable()) {
            return;
        }

        DB::table('notificaciones_app')->insert([
            'usuario_id' => (int) $params['userId'],
            'titulo' => (string) $params['title'],
            'mensaje' => (string) $params['body'],
            'tipo' => $params['type'] ?? null,
            'audience' => $params['audience'] ?? 'both',
            'target_route' => $params['route'] ?? null,
            'target_id' => isset($params['targetId']) ? (string) $params['targetId'] : null,
            'mostrada_mobile' => 0,
            'mostrada_web' => 0,
            'leida' => 0,
            'created_at' => now(),
        ]);
    }

    public function broadcastManual(array $params): void
    {
        if (!$this->ensureTable()) {
            return;
        }

        $audience = $params['audience'] ?? 'both';
        if (!in_array($audience, ['web', 'mobile', 'both'], true)) {
            $audience = 'both';
        }

        $users = DB::table('usuarios')->select(['id'])->where('activo', 1)->get();
        if ($users->isEmpty()) {
            return;
        }

        $now = now();
        $batch = [];
        foreach ($users as $u) {
            $batch[] = [
                'usuario_id' => (int) $u->id,
                'titulo' => (string) $params['title'],
                'mensaje' => (string) $params['body'],
                'tipo' => $params['type'] ?? 'manual',
                'audience' => $audience,
                'target_route' => $params['route'] ?? null,
                'target_id' => isset($params['targetId']) ? (string) $params['targetId'] : null,
                'mostrada_mobile' => 0,
                'mostrada_web' => 0,
                'leida' => 0,
                'created_at' => $now,
            ];
        }

        DB::table('notificaciones_app')->insert($batch);
    }

    public function broadcastNewProduct(int $productId, string $productName): void
    {
        $this->broadcastManual([
            'title' => 'Nuevo producto',
            'body' => "Ya esta disponible: {$productName}",
            'type' => 'new_product',
            'route' => 'store',
            'targetId' => $productId,
            'audience' => 'both',
        ]);
    }

    public function getPendingForUser(int $userId, string $channel): array
    {
        if (!$this->ensureTable()) {
            return [];
        }

        $channel = $channel === 'web' ? 'web' : 'mobile';
        $shownColumn = $channel === 'web' ? 'mostrada_web' : 'mostrada_mobile';

        $rows = DB::table('notificaciones_app')
            ->select(['id', 'titulo', 'mensaje', 'tipo', 'target_route', 'target_id', 'created_at'])
            ->where('usuario_id', $userId)
            ->where($shownColumn, 0)
            ->whereIn('audience', [$channel, 'both'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'title' => (string) $row->titulo,
                'body' => (string) $row->mensaje,
                'type' => (string) ($row->tipo ?? ''),
                'route' => (string) ($row->target_route ?? ''),
                'targetId' => (string) ($row->target_id ?? ''),
                'createdAt' => $row->created_at,
            ];
        })->all();
    }

    public function markShown(int $userId, array $ids, string $channel): void
    {
        if (!$this->ensureTable()) {
            return;
        }

        $safeIds = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        if (count($safeIds) === 0) {
            return;
        }

        $channel = $channel === 'web' ? 'web' : 'mobile';
        $shownColumn = $channel === 'web' ? 'mostrada_web' : 'mostrada_mobile';
        $shownAtColumn = $channel === 'web' ? 'shown_web_at' : 'shown_mobile_at';

        DB::table('notificaciones_app')
            ->where('usuario_id', $userId)
            ->whereIn('id', $safeIds)
            ->update([
                $shownColumn => 1,
                $shownAtColumn => now(),
            ]);
    }
}
