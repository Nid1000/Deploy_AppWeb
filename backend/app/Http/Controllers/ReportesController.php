<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
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

    private function dateFilters(Request $request): array
    {
        return [
            'desde' => $request->query('desde') ?: null,
            'hasta' => $request->query('hasta') ?: null,
        ];
    }

    public function ventasDiarias(Request $request)
    {
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $q = DB::table('pedidos')
            ->select([DB::raw("DATE(created_at) as fecha"), DB::raw("SUM(total) as total")])
            ->where('estado', '<>', 'cancelado')
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy(DB::raw("DATE(created_at)"), 'asc');

        if ($desde) {
            $q->where('created_at', '>=', $desde);
        }
        if ($hasta) {
            $q->where('created_at', '<=', $hasta);
        }

        $rows = $q->get()->map(fn ($r) => ['fecha' => $r->fecha, 'total' => (float) $r->total]);
        return response()->json(['data' => $rows], 200);
    }

    public function ventasSemanales(Request $request)
    {
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        // Semana = lunes. MySQL: YEARWEEK(date, 3) => Monday-based.
        $q = DB::table('pedidos')
            ->select([
                DB::raw("DATE_SUB(DATE(created_at), INTERVAL (WEEKDAY(created_at)) DAY) as semana"),
                DB::raw("SUM(total) as total"),
            ])
            ->where('estado', '<>', 'cancelado')
            ->groupBy(DB::raw("DATE_SUB(DATE(created_at), INTERVAL (WEEKDAY(created_at)) DAY)"))
            ->orderBy(DB::raw("DATE_SUB(DATE(created_at), INTERVAL (WEEKDAY(created_at)) DAY)"), 'asc');

        if ($desde) {
            $q->where('created_at', '>=', $desde);
        }
        if ($hasta) {
            $q->where('created_at', '<=', $hasta);
        }

        $rows = $q->get()->map(fn ($r) => ['semana' => $r->semana, 'total' => (float) $r->total]);
        return response()->json(['data' => $rows], 200);
    }

    public function ventasMensuales(Request $request)
    {
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $q = DB::table('pedidos')
            ->select([DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes"), DB::raw("SUM(total) as total")])
            ->where('estado', '<>', 'cancelado')
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), 'asc');

        if ($desde) {
            $q->where('created_at', '>=', $desde);
        }
        if ($hasta) {
            $q->where('created_at', '<=', $hasta);
        }

        $rows = $q->get()->map(fn ($r) => ['mes' => $r->mes, 'total' => (float) $r->total]);
        return response()->json(['data' => $rows], 200);
    }

    public function topProductos(Request $request)
    {
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        $limite = (int) ($request->query('limite', '10'));
        $limite = $limite > 0 ? $limite : 10;

        $q = DB::table('pedido_detalles as d')
            ->join('pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'd.producto_id')
            ->select([
                'd.producto_id as producto_id',
                DB::raw('MAX(pr.nombre) as nombre'),
                DB::raw('MAX(pr.imagen) as imagen'),
                DB::raw('SUM(d.cantidad) as cantidad'),
                DB::raw('SUM(d.subtotal) as subtotal'),
            ])
            ->whereNotNull('d.producto_id')
            ->where('p.estado', '<>', 'cancelado')
            ->groupBy('d.producto_id')
            ->orderBy(DB::raw('SUM(d.cantidad)'), 'desc');

        if ($desde) {
            $q->where('p.created_at', '>=', $desde);
        }
        if ($hasta) {
            $q->where('p.created_at', '<=', $hasta);
        }

        $rows = $q->limit($limite)->get()->map(function ($r) {
            return [
                'producto_id' => (int) $r->producto_id,
                'nombre' => (string) ($r->nombre ?: ('Producto '.(int) $r->producto_id)),
                'imagen' => $r->imagen,
                'cantidad' => (int) $r->cantidad,
                'subtotal' => (float) $r->subtotal,
            ];
        });

        return response()->json(['data' => $rows], 200);
    }

    public function topCategorias(Request $request)
    {
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        $limite = (int) ($request->query('limite', '10'));
        $limite = $limite > 0 ? $limite : 10;

        $q = DB::table('pedido_detalles as d')
            ->join('pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'd.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'pr.categoria_id')
            ->select([
                DB::raw('pr.categoria_id as categoria_id'),
                DB::raw('MAX(c.nombre) as nombre'),
                DB::raw('SUM(d.cantidad) as cantidad'),
                DB::raw('SUM(d.subtotal) as subtotal'),
            ])
            ->whereNotNull('d.producto_id')
            ->whereNotNull('pr.categoria_id')
            ->where('p.estado', '<>', 'cancelado')
            ->groupBy('pr.categoria_id')
            ->orderBy(DB::raw('SUM(d.cantidad)'), 'desc');

        if ($desde) {
            $q->where('p.created_at', '>=', $desde);
        }
        if ($hasta) {
            $q->where('p.created_at', '<=', $hasta);
        }

        $rows = $q->limit($limite)->get()->map(function ($r) {
            return [
                'categoria_id' => (int) $r->categoria_id,
                'nombre' => (string) ($r->nombre ?: ('Categoría '.(int) $r->categoria_id)),
                'cantidad' => (int) $r->cantidad,
                'subtotal' => (float) $r->subtotal,
            ];
        });

        return response()->json(['data' => $rows], 200);
    }

    public function exportVentas(Request $request)
    {
        $filters = $this->dateFilters($request);
        $modo = (string) $request->query('modo', 'diario');

        $query = DB::table('pedidos')
            ->where('estado', '<>', 'cancelado');

        if ($filters['desde']) {
            $query->where('created_at', '>=', $filters['desde']);
        }
        if ($filters['hasta']) {
            $query->where('created_at', '<=', $filters['hasta']);
        }

        if ($modo === 'mensual') {
            $query->select([DB::raw("DATE_FORMAT(created_at, '%Y-%m') as periodo"), DB::raw('COUNT(*) as pedidos'), DB::raw('SUM(total) as total')])
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->orderBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"));
        } elseif ($modo === 'semanal') {
            $query->select([DB::raw("DATE_SUB(DATE(created_at), INTERVAL (WEEKDAY(created_at)) DAY) as periodo"), DB::raw('COUNT(*) as pedidos'), DB::raw('SUM(total) as total')])
                ->groupBy(DB::raw("DATE_SUB(DATE(created_at), INTERVAL (WEEKDAY(created_at)) DAY)"))
                ->orderBy(DB::raw("DATE_SUB(DATE(created_at), INTERVAL (WEEKDAY(created_at)) DAY)"));
        } else {
            $query->select([DB::raw('DATE(created_at) as periodo'), DB::raw('COUNT(*) as pedidos'), DB::raw('SUM(total) as total')])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy(DB::raw('DATE(created_at)'));
        }

        $rows = $query->get()->map(fn ($row) => [
            $row->periodo,
            (int) $row->pedidos,
            number_format((float) $row->total, 2, '.', ''),
        ])->all();

        return $this->csvResponse('reporte_ventas_' . $modo, ['Periodo', 'Pedidos', 'Total'], $rows, $filters);
    }

    public function exportPedidos(Request $request)
    {
        $filters = $this->dateFilters($request);
        $query = DB::table('pedidos as p')
            ->leftJoin('usuarios as u', 'u.id', '=', 'p.usuario_id')
            ->leftJoin('pagos as pa', 'pa.pedido_id', '=', 'p.id')
            ->select([
                'p.id',
                'p.created_at',
                'p.fecha_entrega',
                'p.estado',
                'p.total',
                'p.distrito_entrega',
                'p.direccion_entrega',
                'p.telefono_contacto',
                'u.nombre',
                'u.apellido',
                'u.email',
                'pa.metodo',
                'pa.estado as estado_pago',
                'pa.referencia',
            ])
            ->orderByDesc('p.created_at');

        if ($filters['desde']) {
            $query->where('p.created_at', '>=', $filters['desde']);
        }
        if ($filters['hasta']) {
            $query->where('p.created_at', '<=', $filters['hasta']);
        }

        $rows = $query->get()->map(fn ($row) => [
            $row->id,
            $row->created_at,
            $row->fecha_entrega,
            trim((string) ($row->nombre . ' ' . $row->apellido)),
            $row->email,
            $row->telefono_contacto,
            $row->distrito_entrega,
            $row->direccion_entrega,
            $row->estado,
            $row->metodo,
            $row->estado_pago,
            $row->referencia,
            number_format((float) $row->total, 2, '.', ''),
        ])->all();

        return $this->csvResponse('reporte_pedidos', [
            'Pedido',
            'Fecha',
            'Entrega',
            'Cliente',
            'Email',
            'Telefono',
            'Distrito',
            'Direccion',
            'Estado pedido',
            'Metodo pago',
            'Estado pago',
            'Referencia pago',
            'Total',
        ], $rows, $filters);
    }

    public function exportProductos(Request $request)
    {
        $filters = $this->dateFilters($request);
        $query = DB::table('pedido_detalles as d')
            ->join('pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->leftJoin('productos as pr', 'pr.id', '=', 'd.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'pr.categoria_id')
            ->select([
                'd.producto_id',
                DB::raw('MAX(pr.nombre) as producto'),
                DB::raw('MAX(c.nombre) as categoria'),
                DB::raw('SUM(d.cantidad) as cantidad'),
                DB::raw('SUM(d.subtotal) as total'),
            ])
            ->whereNotNull('d.producto_id')
            ->where('p.estado', '<>', 'cancelado')
            ->groupBy('d.producto_id')
            ->orderByDesc(DB::raw('SUM(d.cantidad)'));

        if ($filters['desde']) {
            $query->where('p.created_at', '>=', $filters['desde']);
        }
        if ($filters['hasta']) {
            $query->where('p.created_at', '<=', $filters['hasta']);
        }

        $rows = $query->get()->map(fn ($row) => [
            $row->producto_id,
            $row->producto,
            $row->categoria,
            (int) $row->cantidad,
            number_format((float) $row->total, 2, '.', ''),
        ])->all();

        return $this->csvResponse('reporte_productos', ['Producto ID', 'Producto', 'Categoria', 'Cantidad vendida', 'Total'], $rows, $filters);
    }
}
