<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAlmacenController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'pagina' => ['nullable', 'integer', 'min:1'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'producto_id' => ['nullable', 'integer', 'min:1'],
            'tipo_movimiento' => ['nullable', Rule::in(['entrada', 'salida'])],
        ]);

        $pagina = (int) ($validated['pagina'] ?? 1);
        $limite = (int) ($validated['limite'] ?? 20);
        $skip = ($pagina - 1) * $limite;

        $query = $this->baseQuery($validated);
        $total = (clone $query)->count();
        $movimientos = $query
            ->orderByDesc('m.fecha')
            ->orderByDesc('m.id')
            ->offset($skip)
            ->limit($limite)
            ->get();

        return response()->json([
            'statusCode' => 200,
            'movimientos' => $movimientos,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'totalPaginas' => (int) ceil($total / max($limite, 1)),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'min:1', 'exists:productos,id'],
            'tipo_movimiento' => ['required', Rule::in(['entrada', 'salida'])],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = $request->attributes->get('user');
        $adminId = is_array($payload) ? (int) ($payload['id'] ?? 0) : null;

        try {
            $result = DB::transaction(function () use ($data, $adminId) {
                $product = DB::table('productos')->where('id', $data['producto_id'])->lockForUpdate()->first();
                if (!$product) {
                    return ['ok' => false, 'status' => 404, 'message' => 'Producto no encontrado'];
                }

                $stockActual = (int) $product->stock;
                $cantidad = (int) $data['cantidad'];
                $nuevoStock = $data['tipo_movimiento'] === 'entrada'
                    ? $stockActual + $cantidad
                    : $stockActual - $cantidad;

                if ($nuevoStock < 0) {
                    return ['ok' => false, 'status' => 422, 'message' => 'No hay stock suficiente para registrar la salida'];
                }

                $id = DB::table('movimientos_almacen')->insertGetId([
                    'producto_id' => (int) $data['producto_id'],
                    'admin_id' => $adminId ?: null,
                    'tipo_movimiento' => $data['tipo_movimiento'],
                    'cantidad' => $cantidad,
                    'motivo' => $data['motivo'] ?? null,
                    'fecha' => now(),
                ]);

                DB::table('productos')->where('id', $data['producto_id'])->update([
                    'stock' => $nuevoStock,
                    'updated_at' => now(),
                ]);

                return ['ok' => true, 'id' => $id, 'stock' => $nuevoStock];
            });
        } catch (\Throwable $e) {
            return response()->json(['statusCode' => 500, 'message' => 'No se pudo registrar el movimiento'], 500);
        }

        if (!$result['ok']) {
            return response()->json(['statusCode' => $result['status'], 'message' => $result['message']], $result['status']);
        }

        return response()->json([
            'statusCode' => 201,
            'message' => 'Movimiento registrado',
            'movimiento_id' => $result['id'],
            'stock' => $result['stock'],
        ], 201);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'producto_id' => ['nullable', 'integer', 'min:1'],
            'tipo_movimiento' => ['nullable', Rule::in(['entrada', 'salida'])],
        ]);

        $rows = $this->baseQuery($validated)
            ->orderByDesc('m.fecha')
            ->orderByDesc('m.id')
            ->get();

        return $this->csvResponse('movimientos_almacen', [
            'ID',
            'Producto',
            'Tipo',
            'Cantidad',
            'Motivo',
            'Admin',
            'Fecha',
        ], $rows->map(fn ($row) => [
            $row->id,
            $row->producto_nombre,
            $row->tipo_movimiento,
            $row->cantidad,
            $row->motivo,
            $row->admin_nombre,
            $row->fecha,
        ])->all(), $validated);
    }

    private function baseQuery(array $filters)
    {
        $query = DB::table('movimientos_almacen as m')
            ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
            ->leftJoin('administradores as a', 'a.id', '=', 'm.admin_id')
            ->select([
                'm.id',
                'm.producto_id',
                'm.admin_id',
                'm.tipo_movimiento',
                'm.cantidad',
                'm.motivo',
                'm.fecha',
                'p.nombre as producto_nombre',
                'p.stock as producto_stock',
                'a.nombre as admin_nombre',
            ]);

        if (!empty($filters['desde'])) {
            $query->whereDate('m.fecha', '>=', $filters['desde']);
        }
        if (!empty($filters['hasta'])) {
            $query->whereDate('m.fecha', '<=', $filters['hasta']);
        }
        if (!empty($filters['producto_id'])) {
            $query->where('m.producto_id', (int) $filters['producto_id']);
        }
        if (!empty($filters['tipo_movimiento'])) {
            $query->where('m.tipo_movimiento', $filters['tipo_movimiento']);
        }

        return $query;
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
