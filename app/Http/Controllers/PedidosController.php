<?php

namespace App\Http\Controllers;

use App\Mail\PedidoConfirmacionUsuario;
use App\Mail\PedidoNuevoAdmin;
use App\Services\NotificacionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PedidosController extends Controller
{
    public function __construct(private readonly NotificacionesService $notificaciones)
    {
    }

    private function ensureRepartoColumns(): void
    {
        // Proyecto usa MySQL/MariaDB. Si cambia el driver, no alteramos schema aquí.
        try {
            $driver = DB::getDriverName();
        } catch (\Throwable) {
            return;
        }

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $dbName = (string) (env('DB_DATABASE') ?: '');
        if ($dbName === '') {
            return;
        }

        $need = [];
        foreach (['salida_reparto_at', 'regreso_reparto_at', 'conductor', 'vehiculo'] as $col) {
            $exists = DB::selectOne(
                "SELECT 1 as ok FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = ? LIMIT 1",
                [$dbName, $col]
            );
            if (!$exists) {
                $need[] = $col;
            }
        }

        if (count($need) === 0) {
            return;
        }

        // Agrega columnas faltantes (idempotente por verificación anterior).
        foreach ($need as $col) {
            if ($col === 'salida_reparto_at') {
                DB::statement("ALTER TABLE pedidos ADD COLUMN salida_reparto_at DATETIME NULL");
                continue;
            }
            if ($col === 'regreso_reparto_at') {
                DB::statement("ALTER TABLE pedidos ADD COLUMN regreso_reparto_at DATETIME NULL");
                continue;
            }
            if ($col === 'conductor') {
                DB::statement("ALTER TABLE pedidos ADD COLUMN conductor VARCHAR(191) NULL");
                continue;
            }
            if ($col === 'vehiculo') {
                DB::statement("ALTER TABLE pedidos ADD COLUMN vehiculo VARCHAR(191) NULL");
                continue;
            }
        }
    }

    private function toFloat($n): float
    {
        return is_numeric($n) ? (float) $n : (float) (string) $n;
    }

    public function store(Request $request)
    {
        $payload = $request->attributes->get('user');
        $usuarioId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        try {
            $data = $request->validate([
                'productos' => ['required', 'array', 'min:1'],
                'productos.*.id' => ['required', 'integer', 'min:1'],
                'productos.*.cantidad' => ['required', 'integer', 'min:1'],
                'fecha_entrega' => ['nullable', 'date_format:Y-m-d'],
                'direccion_entrega' => ['nullable', 'string'],
                'distrito_entrega' => ['nullable', 'string'],
                'numero_casa_entrega' => ['nullable', 'string'],
                'direccion_id' => ['nullable', 'integer'],
                'telefono_contacto' => ['nullable', 'string'],
                'notas' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $productoIds = collect($data['productos'])->pluck('id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        if (count($productoIds) === 0) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Carrito vacío',
                'message' => 'No se proporcionaron productos',
            ], 400);
        }

        $productos = DB::table('productos')
            ->whereIn('id', $productoIds)
            ->where('activo', 1)
            ->get()
            ->keyBy('id');

        if ($productos->count() !== count($productoIds)) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Producto inválido',
                'message' => 'Uno o más productos no existen o están inactivos',
            ], 400);
        }

        $detalles = [];
        $total = 0.0;
        foreach ($data['productos'] as $item) {
            $prod = $productos[(int) $item['id']];
            $precioUnit = $this->toFloat($prod->precio);
            $cantidad = max(1, (int) $item['cantidad']);
            $subtotal = $precioUnit * $cantidad;
            $total += $subtotal;
            $detalles[] = [
                'producto_id' => (int) $prod->id,
                'cantidad' => $cantidad,
                'precio_unitario' => (string) $precioUnit,
                'subtotal' => (string) $subtotal,
            ];
        }

        $fechaEntrega = !empty($data['fecha_entrega']) ? $data['fecha_entrega'] : null;

        $pedidoCreado = DB::transaction(function () use ($usuarioId, $data, $total, $fechaEntrega, $detalles) {
            $pedidoId = DB::table('pedidos')->insertGetId([
                'usuario_id' => $usuarioId,
                'total' => (string) $total,
                'estado' => 'pendiente',
                'fecha_entrega' => $fechaEntrega,
                'direccion_entrega' => $data['direccion_entrega'] ?? null,
                'distrito_entrega' => $data['distrito_entrega'] ?? null,
                'numero_casa_entrega' => $data['numero_casa_entrega'] ?? null,
                'direccion_id' => $data['direccion_id'] ?? null,
                'telefono_contacto' => $data['telefono_contacto'] ?? null,
                'notas' => $data['notas'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($detalles as $d) {
                DB::table('pedido_detalles')->insert([
                    'pedido_id' => $pedidoId,
                    'producto_id' => $d['producto_id'],
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['precio_unitario'],
                    'subtotal' => $d['subtotal'],
                ]);
            }

            return DB::table('pedidos')->where('id', $pedidoId)->first();
        });

        $det = DB::table('pedido_detalles')
            ->where('pedido_id', (int) $pedidoCreado->id)
            ->get();

        // Adjuntar nombre/imagen de producto como en Nest
        $prodIds = $det->pluck('producto_id')->filter()->unique()->values()->all();
        $prods = DB::table('productos')->whereIn('id', $prodIds)->select(['id', 'nombre', 'imagen'])->get()->keyBy('id');

        // Datos del usuario (para correo y notificaciones).
        $usuario = DB::table('usuarios')
            ->select(['id', 'nombre', 'apellido', 'email', 'telefono'])
            ->where('id', $usuarioId)
            ->first();

        $pedidoForEmail = [
            'id' => (int) $pedidoCreado->id,
            'total' => $this->toFloat($pedidoCreado->total),
            'estado' => (string) $pedidoCreado->estado,
            'fecha_entrega' => $pedidoCreado->fecha_entrega,
            'direccion_entrega' => $pedidoCreado->direccion_entrega,
            'distrito_entrega' => $pedidoCreado->distrito_entrega,
            'numero_casa_entrega' => $pedidoCreado->numero_casa_entrega,
            'telefono_contacto' => $pedidoCreado->telefono_contacto ?: ($usuario?->telefono ?? null),
            'notas' => $pedidoCreado->notas,
            'created_at' => $pedidoCreado->created_at,
            'cliente_nombre' => $usuario ? trim($usuario->nombre.' '.$usuario->apellido) : null,
            'cliente_email' => $usuario?->email ?? null,
        ];

        $detallesForEmail = $det->map(function ($d) use ($prods) {
            $p = $d->producto_id ? ($prods[(int) $d->producto_id] ?? null) : null;
            return [
                'producto_id' => $d->producto_id ? (int) $d->producto_id : null,
                'producto_nombre' => $p?->nombre ?? null,
                'cantidad' => (int) $d->cantidad,
                'subtotal' => $this->toFloat($d->subtotal),
            ];
        })->values()->all();

        // Notificación in-app al usuario (mobile/web).
        try {
            $this->notificaciones->createForUser([
                'userId' => $usuarioId,
                'title' => 'Pedido creado',
                'body' => "Tu pedido #{$pedidoForEmail['id']} fue creado exitosamente.",
                'type' => 'order_created',
                'audience' => 'both',
                'route' => 'order',
                'targetId' => $pedidoForEmail['id'],
            ]);
        } catch (\Throwable) {
            // no bloquear
        }

        // Notificación a administradores (web).
        try {
            $this->notificaciones->broadcastToAdmins([
                'title' => 'Nuevo pedido',
                'body' => "Se creó el pedido #{$pedidoForEmail['id']} por {$pedidoForEmail['cliente_nombre']}.",
                'type' => 'order_created',
                'route' => 'admin_orders',
                'targetId' => (string) $pedidoForEmail['id'],
            ]);
        } catch (\Throwable) {
            // no bloquear
        }

        // Correo: confirmación al usuario + aviso a soporte/admin (MAIL_MAILER=log por defecto).
        try {
            $adminEmail = (string) env('ADMIN_NOTIFICATIONS_EMAIL', env('MAIL_FROM_ADDRESS'));
            if ($adminEmail !== '') {
                Mail::to($adminEmail)->send(new PedidoNuevoAdmin($pedidoForEmail, $detallesForEmail));
            }
            $userEmail = (string) ($pedidoForEmail['cliente_email'] ?? '');
            if ($userEmail !== '') {
                Mail::to($userEmail)->send(new PedidoConfirmacionUsuario($pedidoForEmail, $detallesForEmail));
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar correo de pedido', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'statusCode' => 201,
            'message' => 'Pedido creado exitosamente',
            'pedido' => [
                'id' => (int) $pedidoCreado->id,
                'total' => $this->toFloat($pedidoCreado->total),
                'estado' => $pedidoCreado->estado,
                'fecha_entrega' => $pedidoCreado->fecha_entrega,
                'direccion_entrega' => $pedidoCreado->direccion_entrega,
                'distrito_entrega' => $pedidoCreado->distrito_entrega,
                'numero_casa_entrega' => $pedidoCreado->numero_casa_entrega,
                'telefono_contacto' => $pedidoCreado->telefono_contacto,
                'notas' => $pedidoCreado->notas,
                'created_at' => $pedidoCreado->created_at,
                'detalles' => $det->map(function ($d) use ($prods) {
                    $p = $d->producto_id ? ($prods[(int) $d->producto_id] ?? null) : null;
                    return [
                        'id' => (int) $d->id,
                        'producto_id' => $d->producto_id ? (int) $d->producto_id : null,
                        'cantidad' => (int) $d->cantidad,
                        'precio_unitario' => $this->toFloat($d->precio_unitario),
                        'subtotal' => $this->toFloat($d->subtotal),
                        'producto_nombre' => $p?->nombre ?? null,
                        'producto_imagen' => $p?->imagen ?? null,
                    ];
                }),
            ],
        ], 201);
    }

    public function misPedidos(Request $request)
    {
        $payload = $request->attributes->get('user');
        $usuarioId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $pagina = max(1, (int) ($request->query('pagina', '1')));
        $limite = max(1, (int) ($request->query('limite', '10')));
        $skip = ($pagina - 1) * $limite;

        $totalCount = (int) DB::table('pedidos')->where('usuario_id', $usuarioId)->count();
        $pedidos = DB::table('pedidos')
            ->where('usuario_id', $usuarioId)
            ->orderBy('created_at', 'desc')
            ->offset($skip)
            ->limit($limite)
            ->get();

        $pedidoIds = $pedidos->pluck('id')->all();
        $detalles = collect();
        if (count($pedidoIds) > 0) {
            $detalles = DB::table('pedido_detalles')->whereIn('pedido_id', $pedidoIds)->get();
        }
        $byPedido = $detalles->groupBy('pedido_id');

        $mapped = $pedidos->map(function ($p) use ($byPedido) {
            $ds = $byPedido[(int) $p->id] ?? collect();
            $totalProductos = $ds->reduce(fn ($acc, $d) => $acc + (int) $d->cantidad, 0);
            return [
                'id' => (int) $p->id,
                'total' => $this->toFloat($p->total),
                'estado' => $p->estado,
                'created_at' => $p->created_at,
                'fecha_pedido' => $p->created_at,
                'notas' => $p->notas ?: null,
                'total_productos' => $totalProductos,
            ];
        });

        return response()->json([
            'statusCode' => 200,
            'pedidos' => $mapped,
            'total' => $totalCount,
            'totalPaginas' => (int) ceil($totalCount / $limite),
            'pagina' => $pagina,
            'limite' => $limite,
        ], 200);
    }

    public function show(Request $request, int $id)
    {
        $payload = $request->attributes->get('user');
        $usuarioId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $pedido = DB::table('pedidos')->where('id', $id)->where('usuario_id', $usuarioId)->first();
        if (!$pedido) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Pedido no encontrado',
                'message' => 'El pedido solicitado no existe',
            ], 404);
        }

        $usuario = DB::table('usuarios')
            ->select(['id', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'distrito'])
            ->where('id', $usuarioId)
            ->first();

        $comp = DB::table('comprobantes')->where('pedido_id', $id)->orderBy('created_at', 'desc')->first();
        $detalles = DB::table('pedido_detalles')->where('pedido_id', $id)->get();
        $prodIds = $detalles->pluck('producto_id')->filter()->unique()->values()->all();
        $prods = DB::table('productos')->whereIn('id', $prodIds)->select(['id', 'nombre', 'imagen', 'precio'])->get()->keyBy('id');

        return response()->json([
            'statusCode' => 200,
            'pedido' => [
                'id' => (int) $pedido->id,
                'total' => $this->toFloat($pedido->total),
                'estado' => $pedido->estado,
                'created_at' => $pedido->created_at,
                'notas' => $pedido->notas ?? null,
                'direccion_entrega' => $pedido->direccion_entrega ?? null,
                'distrito_entrega' => $pedido->distrito_entrega ?? null,
                'numero_casa_entrega' => $pedido->numero_casa_entrega ?? null,
                'telefono_contacto' => $pedido->telefono_contacto ?? null,
                'cliente_nombre' => $usuario ? trim($usuario->nombre . ' ' . $usuario->apellido) : null,
                'cliente_email' => $usuario?->email ?? null,
                'cliente_telefono' => $usuario?->telefono ?? null,
                'metodo_pago' => $comp?->tipo ?? null,
                'comprobante_numero' => $comp?->numero_formateado ?? null,
            ],
            'detalles' => $detalles->map(function ($d) use ($prods) {
                $p = $d->producto_id ? ($prods[(int) $d->producto_id] ?? null) : null;
                return [
                    'producto_nombre' => $p?->nombre ?? null,
                    'producto_imagen' => $p?->imagen ?? null,
                    'cantidad' => (int) $d->cantidad,
                    'precio_unitario' => $this->toFloat($d->precio_unitario),
                    'subtotal' => $this->toFloat($d->subtotal),
                ];
            }),
        ], 200);
    }

    public function cancelar(Request $request, int $id)
    {
        $payload = $request->attributes->get('user');
        $usuarioId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $pedido = DB::table('pedidos')->where('id', $id)->where('usuario_id', $usuarioId)->first();
        if (!$pedido) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Pedido no encontrado',
                'message' => 'El pedido solicitado no existe',
            ], 404);
        }

        if (in_array($pedido->estado, ['entregado', 'cancelado', 'listo'], true)) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'No cancelable',
                'message' => 'El pedido no puede ser cancelado en su estado actual',
            ], 400);
        }

        DB::table('pedidos')->where('id', $id)->update([
            'estado' => 'cancelado',
            'updated_at' => now(),
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Pedido cancelado exitosamente',
        ], 200);
    }

    // ADMIN
    public function adminList(Request $request)
    {
        $this->ensureRepartoColumns();

        $pagina = max(1, (int) ($request->query('pagina', '1')));
        $limite = max(1, (int) ($request->query('limite', '20')));
        $skip = ($pagina - 1) * $limite;
        $estado = $request->query('estado');
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        $buscar = trim((string) $request->query('buscar', ''));

        $q = DB::table('pedidos');
        if ($estado) {
            $q->where('estado', (string) $estado);
        }
        if ($desde || $hasta) {
            if ($desde) {
                $q->where('created_at', '>=', $desde);
            }
            if ($hasta) {
                $q->where('created_at', '<=', $hasta);
            }
        }
        if ($buscar !== '') {
            $num = is_numeric($buscar) ? (int) $buscar : null;
            $q->where(function ($w) use ($buscar, $num) {
                $w->where('notas', 'like', "%{$buscar}%")
                    ->orWhere('direccion_entrega', 'like', "%{$buscar}%")
                    ->orWhere('telefono_contacto', 'like', "%{$buscar}%");
                if ($num !== null) {
                    $w->orWhere('id', $num);
                }
            });
        }

        $total = (int) $q->count();
        $pedidos = $q->orderBy('created_at', 'desc')->offset($skip)->limit($limite)->get();

        $pedidoIds = $pedidos->pluck('id')->all();
        $detalles = count($pedidoIds) ? DB::table('pedido_detalles')->whereIn('pedido_id', $pedidoIds)->get() : collect();
        $byPedido = $detalles->groupBy('pedido_id');

        $userIds = $pedidos->pluck('usuario_id')->filter()->unique()->values()->all();
        $users = count($userIds)
            ? DB::table('usuarios')->whereIn('id', $userIds)->select(['id', 'nombre', 'apellido', 'email'])->get()->keyBy('id')
            : collect();

        $rows = $pedidos->map(function ($p) use ($byPedido, $users) {
            $ds = $byPedido[(int) $p->id] ?? collect();
            $u = $p->usuario_id ? ($users[(int) $p->usuario_id] ?? null) : null;
            return [
                'id' => (int) $p->id,
                'usuario' => $u ? [
                    'id' => (int) $u->id,
                    'nombre' => (string) $u->nombre,
                    'apellido' => (string) $u->apellido,
                    'email' => (string) $u->email,
                ] : null,
                'total' => $this->toFloat($p->total),
                'estado' => $p->estado,
                'created_at' => $p->created_at,
                'fecha_entrega' => $p->fecha_entrega ?? null,
                'notas' => $p->notas ?? null,
                'direccion_entrega' => $p->direccion_entrega ?? null,
                'telefono_contacto' => $p->telefono_contacto ?? null,
                'salida_reparto_at' => property_exists($p, 'salida_reparto_at') ? ($p->salida_reparto_at ?? null) : null,
                'regreso_reparto_at' => property_exists($p, 'regreso_reparto_at') ? ($p->regreso_reparto_at ?? null) : null,
                'conductor' => property_exists($p, 'conductor') ? ($p->conductor ?? null) : null,
                'vehiculo' => property_exists($p, 'vehiculo') ? ($p->vehiculo ?? null) : null,
                'total_productos' => $ds->reduce(fn ($acc, $d) => $acc + (int) $d->cantidad, 0),
            ];
        });

        return response()->json([
            'statusCode' => 200,
            'pedidos' => $rows,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'totalPaginas' => (int) ceil($total / $limite),
            ],
        ], 200);
    }

    public function adminShow(int $id)
    {
        $this->ensureRepartoColumns();

        $p = DB::table('pedidos')->where('id', $id)->first();
        if (!$p) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Pedido no encontrado',
                'message' => 'El pedido solicitado no existe',
            ], 404);
        }

        $usuario = $p->usuario_id
            ? DB::table('usuarios')->select(['id', 'nombre', 'apellido', 'email', 'telefono'])->where('id', (int) $p->usuario_id)->first()
            : null;

        $detalles = DB::table('pedido_detalles')->where('pedido_id', $id)->get();
        $prodIds = $detalles->pluck('producto_id')->filter()->unique()->values()->all();
        $prods = DB::table('productos')->whereIn('id', $prodIds)->select(['id', 'nombre', 'imagen', 'precio'])->get()->keyBy('id');

        return response()->json([
            'statusCode' => 200,
            'pedido' => [
                'id' => (int) $p->id,
                'usuario' => $usuario ? [
                    'id' => (int) $usuario->id,
                    'nombre' => (string) $usuario->nombre,
                    'apellido' => (string) $usuario->apellido,
                    'email' => (string) $usuario->email,
                    'telefono' => $usuario->telefono,
                ] : null,
                'total' => $this->toFloat($p->total),
                'estado' => $p->estado,
                'created_at' => $p->created_at,
                'fecha_entrega' => $p->fecha_entrega ?? null,
                'notas' => $p->notas ?? null,
                'direccion_entrega' => $p->direccion_entrega ?? null,
                'telefono_contacto' => $p->telefono_contacto ?? null,
                'salida_reparto_at' => property_exists($p, 'salida_reparto_at') ? ($p->salida_reparto_at ?? null) : null,
                'regreso_reparto_at' => property_exists($p, 'regreso_reparto_at') ? ($p->regreso_reparto_at ?? null) : null,
                'conductor' => property_exists($p, 'conductor') ? ($p->conductor ?? null) : null,
                'vehiculo' => property_exists($p, 'vehiculo') ? ($p->vehiculo ?? null) : null,
            ],
            'detalles' => $detalles->map(function ($d) use ($prods) {
                $prod = $d->producto_id ? ($prods[(int) $d->producto_id] ?? null) : null;
                return [
                    'producto_nombre' => $prod?->nombre ?? null,
                    'producto_imagen' => $prod?->imagen ?? null,
                    'cantidad' => (int) $d->cantidad,
                    'precio_unitario' => $this->toFloat($d->precio_unitario),
                    'subtotal' => $this->toFloat($d->subtotal),
                ];
            }),
        ], 200);
    }

    public function adminReparto(Request $request, int $id)
    {
        $this->ensureRepartoColumns();

        try {
            $data = $request->validate([
                // Formato HTML datetime-local: YYYY-MM-DDTHH:MM
                'salida_reparto_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
                'regreso_reparto_at' => ['nullable', 'date_format:Y-m-d\\TH:i', 'after_or_equal:salida_reparto_at'],
                'conductor' => ['nullable', 'string', 'max:191'],
                'vehiculo' => ['nullable', 'string', 'max:191'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $pedido = DB::table('pedidos')->select(['id'])->where('id', $id)->first();
        if (!$pedido) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Pedido no encontrado',
                'message' => 'El pedido solicitado no existe',
            ], 404);
        }

        $salida = $data['salida_reparto_at'] ?? null;
        $regreso = $data['regreso_reparto_at'] ?? null;
        // Persistir como 'Y-m-d H:i:s' (DB)
        $salidaDb = $salida ? str_replace('T', ' ', $salida).':00' : null;
        $regresoDb = $regreso ? str_replace('T', ' ', $regreso).':00' : null;

        DB::table('pedidos')->where('id', $id)->update([
            'salida_reparto_at' => $salidaDb,
            'regreso_reparto_at' => $regresoDb,
            'conductor' => isset($data['conductor']) ? trim((string) $data['conductor']) : null,
            'vehiculo' => isset($data['vehiculo']) ? trim((string) $data['vehiculo']) : null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'statusCode' => 200,
            'ok' => true,
            'message' => 'Reparto actualizado',
            'salida_reparto_at' => $salidaDb,
            'regreso_reparto_at' => $regresoDb,
            'conductor' => isset($data['conductor']) ? trim((string) $data['conductor']) : null,
            'vehiculo' => isset($data['vehiculo']) ? trim((string) $data['vehiculo']) : null,
        ], 200);
    }

    public function adminEstado(Request $request, int $id)
    {
        try {
            $data = $request->validate([
                'estado' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $estado = (string) $data['estado'];
        if (!in_array($estado, ['pendiente', 'listo', 'entregado', 'cancelado'], true)) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Estado inválido',
                'message' => 'Estado de pedido no reconocido',
            ], 400);
        }

        $pedido = DB::table('pedidos')->select(['id', 'estado', 'usuario_id'])->where('id', $id)->first();
        if (!$pedido) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Pedido no encontrado',
                'message' => 'El pedido solicitado no existe',
            ], 404);
        }

        if (in_array($pedido->estado, ['entregado', 'cancelado'], true)) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'No modificable',
                'message' => 'El pedido no puede cambiarse en su estado actual',
            ], 400);
        }

        DB::table('pedidos')->where('id', $id)->update([
            'estado' => $estado,
            'updated_at' => now(),
        ]);

        // Notificación al usuario cuando esté listo (compat con Nest)
        if ($pedido->usuario_id && $estado === 'listo') {
            try {
                $this->notificaciones->createForUser([
                    'userId' => (int) $pedido->usuario_id,
                    'title' => 'Tu pedido está listo',
                    'body' => "El pedido #{$id} ya está listo para seguimiento.",
                    'type' => 'order_ready',
                    'audience' => 'both',
                    'route' => 'order',
                    'targetId' => $id,
                ]);
            } catch (\Throwable $e) {
                // no bloquear
            }
        }

        return response()->json([
            'statusCode' => 200,
            'message' => 'Estado actualizado',
            'estado' => $estado,
        ], 200);
    }

    public function adminFechaEntrega(Request $request, int $id)
    {
        try {
            $data = $request->validate([
                'fecha_entrega' => ['nullable', 'date_format:Y-m-d'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $pedido = DB::table('pedidos')->select(['id'])->where('id', $id)->first();
        if (!$pedido) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Pedido no encontrado',
                'message' => 'El pedido solicitado no existe',
            ], 404);
        }

        $fecha = $data['fecha_entrega'] ?? null;
        DB::table('pedidos')->where('id', $id)->update([
            'fecha_entrega' => $fecha,
            'updated_at' => now(),
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Fecha de entrega actualizada',
            'fecha_entrega' => $fecha,
        ], 200);
    }
}
