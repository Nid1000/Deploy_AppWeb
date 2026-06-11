<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NotificacionesService
{
    private bool $ready = false;

    private function ensureTable(): void
    {
        if ($this->ready) {
            return;
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS notificaciones_app (
              id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
              usuario_id INT NOT NULL,
              titulo VARCHAR(160) NOT NULL,
              mensaje TEXT NOT NULL,
              tipo VARCHAR(40) NULL,
              audience VARCHAR(16) NOT NULL DEFAULT 'both',
              target_route VARCHAR(40) NULL,
              target_id VARCHAR(80) NULL,
              mostrada_mobile TINYINT(1) NOT NULL DEFAULT 0,
              mostrada_web TINYINT(1) NOT NULL DEFAULT 0,
              leida TINYINT(1) NOT NULL DEFAULT 0,
              shown_mobile_at DATETIME NULL,
              shown_web_at DATETIME NULL,
              read_at DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_notif_usuario_mobile (usuario_id, mostrada_mobile, created_at),
              INDEX idx_notif_usuario_web (usuario_id, mostrada_web, created_at)
            )
        ");

        $this->ready = true;
    }

    public function createForUser(array $params): void
    {
        $this->ensureTable();

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
        $this->ensureTable();

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
        $this->ensureTable();

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
        $this->ensureTable();

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

