<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoriasController extends Controller
{
    private function firstValidationMessage(array $errors): string
    {
        foreach ($errors as $fieldErrors) {
            if (is_array($fieldErrors) && count($fieldErrors) > 0) {
                $msg = (string) $fieldErrors[0];
                if (trim($msg) !== '') {
                    return $msg;
                }
            }
        }
        return 'Validación fallida';
    }

    private function normalizeImagenField($imagen): ?string
    {
        if ($imagen === null) return null;
        $val = trim((string) $imagen);
        if ($val === '') return null;

        if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            return $val;
        }

        $val = str_replace('\\', '/', $val);
        if (str_starts_with($val, '/')) {
            $cleanAbs = ltrim($val, '/');
            if (str_starts_with($cleanAbs, 'uploads/')) {
                $abs = public_path($cleanAbs);
                return is_file($abs) ? $val : null;
            }
            return $val;
        }

        $abs = public_path('uploads/' . $val);
        if (is_file($abs)) {
            return $val;
        }

        return null;
    }

    private function maxUploadKb(): int
    {
        $bytes = (int) env('MAX_FILE_SIZE', 5242880);
        return max(1, (int) floor($bytes / 1024));
    }

    public function index(Request $request)
    {
        $activo = $request->query('activo');
        $onlyActive = $activo === null ? true : ($activo === 'true' || $activo === '1');

        $q = DB::table('categorias')->orderBy('nombre', 'asc');
        if ($onlyActive) {
            $q->where('activo', 1);
        }

        $rows = $q->get()->map(function ($c) {
            $c->imagen = $this->normalizeImagenField($c->imagen ?? null);
            return $c;
        });
        return response()->json($rows, 200);
    }

    public function show(int $id)
    {
        $row = DB::table('categorias')->where('id', $id)->where('activo', 1)->first();
        if (!$row) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Categoría no encontrada',
                'message' => 'Categoría no encontrada',
            ], 404);
        }
        $row->imagen = $this->normalizeImagenField($row->imagen ?? null);
        return response()->json($row, 200);
    }

    public function productos(Request $request, int $id)
    {
        $categoria = DB::table('categorias')->where('id', $id)->where('activo', 1)->first();
        if (!$categoria) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Categoría no encontrada',
                'message' => 'Categoría no encontrada',
            ], 404);
        }

        $pagina = max(1, (int) ($request->query('pagina', '1')));
        $limite = max(1, (int) ($request->query('limite', '20')));
        $skip = ($pagina - 1) * $limite;

        $base = DB::table('productos')->where('categoria_id', $id)->where('activo', 1);
        $total = (int) $base->count();
        $productos = $base
            ->orderBy('created_at', 'desc')
            ->offset($skip)
            ->limit($limite)
            ->get()
            ->map(function ($p) {
                $p->precio = (float) $p->precio;
                return $p;
            });

        return response()->json([
            'categoria' => $categoria,
            'productos' => $productos,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'totalPaginas' => (int) ceil($total / $limite),
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

        $q = DB::table('categorias');
        if ($buscar !== '') {
            $q->where(function ($w) use ($buscar) {
                $w->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('descripcion', 'like', "%{$buscar}%");
            });
        }
        if ($activoFilter !== null) {
            $q->where('activo', $activoFilter ? 1 : 0);
        }

        $total = (int) $q->count();
        $categorias = $q->orderBy('nombre', 'asc')->offset($skip)->limit($limite)->get()->map(function ($c) {
            $c->imagen = $this->normalizeImagenField($c->imagen ?? null);
            return $c;
        });

        return response()->json([
            'statusCode' => 200,
            'categorias' => $categorias,
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
        $categoria = DB::table('categorias')->where('id', $id)->first();
        if (!$categoria) {
            return response()->json(['statusCode' => 404, 'error' => 'Categoría no encontrada'], 404);
        }
        return response()->json(['statusCode' => 200, 'categoria' => $categoria], 200);
    }

    public function adminCreate(Request $request)
    {
        try {
            $data = $request->validate([
                'nombre' => ['required', 'string', 'min:2', 'max:200'],
                'descripcion' => ['nullable', 'string', 'max:2000'],
                'imagen' => ['nullable', 'string', 'max:500'],
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => $this->firstValidationMessage($errors),
                'details' => $errors,
            ], 400);
        }

        $nombre = trim($data['nombre']);
        $dup = DB::table('categorias')->where('nombre', $nombre)->first();
        if ($dup) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Duplicado',
                'message' => 'Ya existe una categoría con ese nombre',
            ], 400);
        }

        $id = DB::table('categorias')->insertGetId([
            'nombre' => $nombre,
            'descripcion' => $data['descripcion'] ?? null,
            'imagen' => $data['imagen'] ?? null,
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoria = DB::table('categorias')->where('id', $id)->first();
        return response()->json([
            'statusCode' => 201,
            'message' => 'Categoría creada',
            'categoria' => $categoria,
        ], 201);
    }

    public function adminUpdate(Request $request, int $id)
    {
        $existente = DB::table('categorias')->where('id', $id)->first();
        if (!$existente) {
            return response()->json(['statusCode' => 404, 'error' => 'Categoría no encontrada'], 404);
        }

        try {
            $data = $request->validate([
                'nombre' => ['sometimes', 'nullable', 'string', 'min:2', 'max:200'],
                'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'imagen' => ['sometimes', 'nullable', 'string', 'max:500'],
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => $this->firstValidationMessage($errors),
                'details' => $errors,
            ], 400);
        }

        $update = [];
        if (array_key_exists('nombre', $data)) {
            $nombre = trim((string) ($data['nombre'] ?? ''));
            if ($nombre === '' || strlen($nombre) < 2) {
                return response()->json([
                    'statusCode' => 400,
                    'error' => 'Datos inválidos',
                    'message' => 'El nombre debe tener al menos 2 caracteres',
                ], 400);
            }
            if ($nombre !== $existente->nombre) {
                $dup = DB::table('categorias')->where('nombre', $nombre)->first();
                if ($dup) {
                    return response()->json([
                        'statusCode' => 400,
                        'error' => 'Duplicado',
                        'message' => 'Ya existe una categoría con ese nombre',
                    ], 400);
                }
            }
            $update['nombre'] = $nombre;
        }
        if (array_key_exists('descripcion', $data)) {
            $update['descripcion'] = $data['descripcion'] ?? null;
        }
        if (array_key_exists('imagen', $data)) {
            $update['imagen'] = $data['imagen'] ?? null;
        }

        if (count($update) === 0) {
            return response()->json(['statusCode' => 400, 'error' => 'Sin cambios'], 400);
        }

        $update['updated_at'] = now();
        DB::table('categorias')->where('id', $id)->update($update);
        $categoria = DB::table('categorias')->where('id', $id)->first();
        if ($categoria) {
            $categoria->imagen = $this->normalizeImagenField($categoria->imagen ?? null);
        }

        return response()->json([
            'statusCode' => 200,
            'message' => 'Categoría actualizada',
            'categoria' => $categoria,
        ], 200);
    }

    public function adminUpdateImagen(Request $request, int $id)
    {
        $existente = DB::table('categorias')->where('id', $id)->first();
        if (!$existente) {
            return response()->json(['statusCode' => 404, 'error' => 'Categoría no encontrada'], 404);
        }

        try {
            $request->validate([
                'imagen' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp,jfif', 'max:'.$this->maxUploadKb()],
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return response()->json([
                'statusCode' => 400,
                'error' => 'Archivo requerido',
                'message' => $this->firstValidationMessage($errors),
                'details' => $errors,
            ], 400);
        }

        $file = $request->file('imagen');
        $dir = public_path('uploads/categorias');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $name = 'categoria-' . now()->timestamp . '-' . Str::random(8) . '.' . $ext;
        $file->move($dir, $name);

        $rel = 'categorias/' . $name;
        DB::table('categorias')->where('id', $id)->update([
            'imagen' => $rel,
            'updated_at' => now(),
        ]);

        $categoria = DB::table('categorias')->where('id', $id)->first();
        if ($categoria) {
            $categoria->imagen = $this->normalizeImagenField($categoria->imagen ?? null);
        }
        return response()->json([
            'statusCode' => 200,
            'message' => 'Imagen actualizada',
            'categoria' => $categoria,
        ], 200);
    }

    public function adminEstado(Request $request, int $id)
    {
        $existente = DB::table('categorias')->where('id', $id)->first();
        if (!$existente) {
            return response()->json(['statusCode' => 404, 'error' => 'Categoría no encontrada'], 404);
        }

        try {
            $data = $request->validate([
                'activo' => ['required', 'boolean'],
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => $this->firstValidationMessage($errors),
                'details' => $errors,
            ], 400);
        }

        DB::table('categorias')->where('id', $id)->update([
            'activo' => $data['activo'] ? 1 : 0,
            'updated_at' => now(),
        ]);
        $categoria = DB::table('categorias')->where('id', $id)->first();
        if ($categoria) {
            $categoria->imagen = $this->normalizeImagenField($categoria->imagen ?? null);
        }
        return response()->json([
            'statusCode' => 200,
            'message' => 'Estado actualizado',
            'categoria' => $categoria,
        ], 200);
    }

    public function adminDelete(int $id)
    {
        $existente = DB::table('categorias')->where('id', $id)->first();
        if (!$existente) {
            return response()->json(['statusCode' => 404, 'error' => 'Categoría no encontrada'], 404);
        }

        DB::table('categorias')->where('id', $id)->update([
            'activo' => 0,
            'updated_at' => now(),
        ]);
        $categoria = DB::table('categorias')->where('id', $id)->first();
        if ($categoria) {
            $categoria->imagen = $this->normalizeImagenField($categoria->imagen ?? null);
        }
        return response()->json([
            'statusCode' => 200,
            'message' => 'Categoría eliminada',
            'categoria' => $categoria,
        ], 200);
}
}
