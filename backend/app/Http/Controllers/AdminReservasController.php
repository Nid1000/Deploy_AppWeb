<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminReservasController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'pagina' => ['nullable', 'integer', 'min:1'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'string', Rule::in(['pendiente', 'confirmada', 'asistio', 'asistió', 'cancelada'])],
            'buscar' => ['nullable', 'string', 'max:120'],
        ]);

        $pagina = (int) ($validated['pagina'] ?? 1);
        $limite = (int) ($validated['limite'] ?? 20);
        $skip = ($pagina - 1) * $limite;

        $query = $this->baseQuery($validated);
        $total = (clone $query)->count();
        $reservas = $query
            ->orderByDesc('r.fecha_reserva')
            ->orderByDesc('r.hora_reserva')
            ->offset($skip)
            ->limit($limite)
            ->get();

        return response()->json([
            'statusCode' => 200,
            'reservas' => $reservas,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'totalPaginas' => (int) ceil($total / max($limite, 1)),
            ],
        ]);
    }

    public function updateEstado(Request $request, int $id)
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(['pendiente', 'confirmada', 'asistio', 'asistió', 'cancelada'])],
        ]);
        $estado = $this->normalizeEstado($data['estado']);

        $updated = DB::table('reservas')->where('id', $id)->update(['estado' => $estado]);
        if ($updated === 0) {
            return response()->json(['statusCode' => 404, 'message' => 'Reserva no encontrada'], 404);
        }

        return response()->json(['statusCode' => 200, 'message' => 'Estado actualizado']);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'string', Rule::in(['pendiente', 'confirmada', 'asistio', 'asistió', 'cancelada'])],
            'buscar' => ['nullable', 'string', 'max:120'],
        ]);

        $rows = $this->baseQuery($validated)
            ->orderByDesc('r.fecha_reserva')
            ->orderByDesc('r.hora_reserva')
            ->get();

        return $this->csvResponse('reservas', [
            'ID',
            'Cliente',
            'Email',
            'Telefono',
            'Fecha',
            'Hora',
            'Personas',
            'Estado',
            'Notas',
            'Creado',
        ], $rows->map(fn ($row) => [
            $row->id,
            trim((string) ($row->cliente_nombre . ' ' . $row->cliente_apellido)),
            $row->cliente_email,
            $row->cliente_telefono,
            $row->fecha_reserva,
            $row->hora_reserva,
            $row->cantidad_personas,
            $row->estado,
            $row->notas,
            $row->created_at,
        ])->all(), $validated);
    }

    private function baseQuery(array $filters)
    {
        $query = DB::table('reservas as r')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->select([
                'r.id',
                'r.usuario_id',
                'r.fecha_reserva',
                'r.hora_reserva',
                'r.cantidad_personas',
                'r.estado',
                'r.notas',
                'r.created_at',
                'u.nombre as cliente_nombre',
                'u.apellido as cliente_apellido',
                'u.email as cliente_email',
                'u.telefono as cliente_telefono',
            ]);

        if (!empty($filters['desde'])) {
            $query->whereDate('r.fecha_reserva', '>=', $filters['desde']);
        }
        if (!empty($filters['hasta'])) {
            $query->whereDate('r.fecha_reserva', '<=', $filters['hasta']);
        }
        if (!empty($filters['estado'])) {
            $query->where('r.estado', $this->normalizeEstado($filters['estado']));
        }
        if (!empty($filters['buscar'])) {
            $buscar = '%' . trim((string) $filters['buscar']) . '%';
            $query->where(function ($q) use ($buscar) {
                $q->where('u.nombre', 'like', $buscar)
                    ->orWhere('u.apellido', 'like', $buscar)
                    ->orWhere('u.email', 'like', $buscar)
                    ->orWhere('u.telefono', 'like', $buscar);
            });
        }

        return $query;
    }

    private function normalizeEstado(string $estado): string
    {
        return $estado === 'asistio' ? 'asistió' : $estado;
    }

    private function csvResponse(string $prefix, array $headers, array $rows, array $filters)
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $desde = $filters['desde'] ?? now()->toDateString();
        $hasta = $filters['hasta'] ?? now()->toDateString();

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $prefix . '_' . $desde . '_' . $hasta . '.csv"',
        ]);
    }
}
