<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UsuariosController extends Controller
{
    public function distritosHuancayo()
    {
        // Compat con Nest: devuelve { statusCode, distritos: [{id,nombre}] }
        $rows = DB::table('catalogo_distritos_huancayo')
            ->select(['id', 'nombre'])
            ->where('activo', 1)
            ->orderBy('orden_lista', 'asc')
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json([
            'statusCode' => 200,
            'distritos' => $rows,
        ], 200);
    }

    public function perfil(Request $request)
    {
        $payload = $request->attributes->get('user');
        $id = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $usuario = DB::table('usuarios')
            ->select(['id', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'distrito', 'numero_casa', 'created_at'])
            ->where('id', $id)
            ->where('activo', 1)
            ->first();

        if (!$usuario) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Usuario no encontrado',
                'message' => 'Tu perfil no fue encontrado',
            ], 404);
        }

        return response()->json([
            'statusCode' => 200,
            'usuario' => $usuario,
        ], 200);
    }

    public function updatePerfil(Request $request)
    {
        $payload = $request->attributes->get('user');
        $id = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        try {
            $data = $request->validate([
                'nombre' => ['sometimes', 'nullable', 'string', 'min:2'],
                'apellido' => ['sometimes', 'nullable', 'string', 'min:2'],
                'telefono' => ['sometimes', 'nullable', 'regex:/^9\\d{8}$/'],
                'direccion' => ['sometimes', 'nullable', 'string', 'min:5'],
                'distrito' => ['sometimes', 'nullable', 'string', 'min:2'],
                'numero_casa' => ['sometimes', 'nullable', 'string', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $fields = [];
        foreach (['nombre', 'apellido', 'telefono', 'direccion', 'distrito', 'numero_casa'] as $k) {
            if (array_key_exists($k, $data)) {
                $fields[$k] = $data[$k];
            }
        }
        if (count($fields) === 0) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Sin cambios',
                'message' => 'No se proporcionaron datos para actualizar',
            ], 400);
        }
        $fields['updated_at'] = now();

        DB::table('usuarios')->where('id', $id)->update($fields);
        $usuario = DB::table('usuarios')
            ->select(['id', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'distrito', 'numero_casa', 'created_at'])
            ->where('id', $id)
            ->first();

        return response()->json([
            'statusCode' => 200,
            'message' => 'Perfil actualizado exitosamente',
            'usuario' => $usuario,
        ], 200);
    }

    public function cambiarPassword(Request $request)
    {
        $payload = $request->attributes->get('user');
        $id = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        try {
            $data = $request->validate([
                'passwordActual' => ['required', 'string'],
                'passwordNueva' => ['required', 'string', 'min:6'],
                'confirmarPassword' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        if ($data['confirmarPassword'] !== $data['passwordNueva']) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Las contraseñas no coinciden',
            ], 400);
        }

        $usuario = DB::table('usuarios')->where('id', $id)->first();
        if (!$usuario || !(bool) $usuario->activo) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Usuario no encontrado',
                'message' => 'Tu cuenta no fue encontrada',
            ], 404);
        }

        if (!Hash::check($data['passwordActual'], (string) $usuario->password)) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Contraseña incorrecta',
                'message' => 'La contraseña actual es incorrecta',
            ], 400);
        }

        DB::table('usuarios')->where('id', $id)->update([
            'password' => Hash::make($data['passwordNueva']),
            'updated_at' => now(),
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Contraseña actualizada exitosamente',
        ], 200);
    }

    public function estadisticas(Request $request)
    {
        $payload = $request->attributes->get('user');
        $id = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $totalPedidos = (int) DB::table('pedidos')->where('usuario_id', $id)->count();
        $porEstado = DB::table('pedidos')
            ->select(['estado', DB::raw('COUNT(*) as cantidad')])
            ->where('usuario_id', $id)
            ->groupBy('estado')
            ->get();
        $sum = (float) (DB::table('pedidos')
            ->where('usuario_id', $id)
            ->where('estado', '<>', 'cancelado')
            ->sum('total') ?? 0);
        $ultimo = DB::table('pedidos')->where('usuario_id', $id)->orderBy('created_at', 'desc')->first();

        return response()->json([
            'statusCode' => 200,
            'estadisticas' => [
                'total_pedidos' => $totalPedidos,
                'pedidos_por_estado' => $porEstado,
                'total_gastado' => $sum,
                'ultimo_pedido' => $ultimo,
            ],
        ], 200);
    }

    public function adminList(Request $request)
    {
        $limite = max(1, (int) ($request->query('limite', '20')));
        $pagina = max(1, (int) ($request->query('pagina', '1')));
        $skip = ($pagina - 1) * $limite;
        $buscar = trim((string) $request->query('buscar', ''));
        $activo = $request->query('activo');
        $activoFilter = $activo === null ? null : ($activo === 'true' || $activo === '1');

        $q = DB::table('usuarios');
        if ($buscar !== '') {
            $q->where(function ($w) use ($buscar) {
                $w->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%")
                    ->orWhere('distrito', 'like', "%{$buscar}%");
            });
        }
        if ($activoFilter !== null) {
            $q->where('activo', $activoFilter ? 1 : 0);
        }

        $total = (int) $q->count();
        $usuarios = $q
            ->select(['id', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'distrito', 'numero_casa', 'activo', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->offset($skip)
            ->limit($limite)
            ->get();

        return response()->json([
            'statusCode' => 200,
            'usuarios' => $usuarios,
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
        $usuario = DB::table('usuarios')
            ->select(['id', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'distrito', 'numero_casa', 'activo', 'created_at'])
            ->where('id', $id)
            ->first();
        if (!$usuario) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Usuario no encontrado',
                'message' => 'El usuario solicitado no existe',
            ], 404);
        }

        $totalPedidos = (int) DB::table('pedidos')->where('usuario_id', $id)->count();
        $sum = (float) (DB::table('pedidos')->where('usuario_id', $id)->where('estado', '<>', 'cancelado')->sum('total') ?? 0);

        return response()->json([
            'statusCode' => 200,
            'usuario' => [
                ...((array) $usuario),
                'estadisticas' => [
                    'total_pedidos' => $totalPedidos,
                    'total_gastado' => $sum,
                ],
            ],
        ], 200);
    }

    public function adminEstado(Request $request, int $id)
    {
        try {
            $data = $request->validate([
                'activo' => ['required', 'boolean'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $existe = DB::table('usuarios')->select(['id'])->where('id', $id)->first();
        if (!$existe) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Usuario no encontrado',
                'message' => 'El usuario solicitado no existe',
            ], 404);
        }

        DB::table('usuarios')->where('id', $id)->update([
            'activo' => $data['activo'] ? 1 : 0,
            'updated_at' => now(),
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Estado actualizado',
            'usuario' => ['id' => $id, 'activo' => $data['activo']],
        ], 200);
    }

    public function adminUpdate(Request $request, int $id)
    {
        $existente = DB::table('usuarios')->where('id', $id)->first();
        if (!$existente) {
            return response()->json(['statusCode' => 404, 'error' => 'Usuario no encontrado'], 404);
        }

        $data = $request->all();
        $update = [];
        if (array_key_exists('nombre', $data)) {
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '' || strlen($nombre) < 2) {
                return response()->json([
                    'statusCode' => 400,
                    'error' => 'Datos inválidos',
                    'message' => 'El nombre debe tener al menos 2 caracteres',
                ], 400);
            }
            $update['nombre'] = $nombre;
        }
        if (array_key_exists('apellido', $data)) {
            $update['apellido'] = trim((string) $data['apellido']);
        }
        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email === '' || !str_contains($email, '@')) {
                return response()->json(['statusCode' => 400, 'error' => 'Datos inválidos', 'message' => 'Email inválido'], 400);
            }
            if ($email !== $existente->email) {
                $dup = DB::table('usuarios')->where('email', $email)->first();
                if ($dup) {
                    return response()->json(['statusCode' => 400, 'error' => 'Duplicado', 'message' => 'Ya existe un usuario con ese email'], 400);
                }
            }
            $update['email'] = $email;
        }
        foreach (['telefono', 'direccion', 'distrito', 'numero_casa'] as $k) {
            if (array_key_exists($k, $data)) {
                $update[$k] = $data[$k] ?: null;
            }
        }
        if (count($update) === 0) {
            return response()->json(['statusCode' => 400, 'error' => 'Sin cambios'], 400);
        }
        $update['updated_at'] = now();
        DB::table('usuarios')->where('id', $id)->update($update);
        $usuario = DB::table('usuarios')->where('id', $id)->first();

        return response()->json([
            'statusCode' => 200,
            'message' => 'Usuario actualizado',
            'usuario' => $usuario,
        ], 200);
    }
}

