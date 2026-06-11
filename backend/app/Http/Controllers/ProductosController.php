<?php

namespace App\Http\Controllers;

use App\Services\NewProductEmailService;
use App\Services\NotificacionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductosController extends Controller
{
    public function __construct(
        private readonly NotificacionesService $notificaciones,
        private readonly NewProductEmailService $productEmails
    ) {
    }

    private function coerceBooleanInput(Request $request, string $key): void
    {
        $val = $request->input($key);

        if (is_bool($val)) {
            $request->merge([$key => $val ? 1 : 0]);
            return;
        }

        if (!is_string($val)) {
            return;
        }
        $lower = strtolower(trim($val));
        if ($lower === 'true') {
            $request->merge([$key => 1]);
        } elseif ($lower === 'false') {
            $request->merge([$key => 0]);
        } elseif ($lower === 'on') {
            $request->merge([$key => 1]);
        } elseif ($lower === 'off') {
            $request->merge([$key => 0]);
        } elseif ($lower === '1') {
            $request->merge([$key => 1]);
        } elseif ($lower === '0') {
            $request->merge([$key => 0]);
        }
    }

    private function validateBooleanLike($value): bool
    {
        return in_array($value, [0, 1, '0', '1', true, false, 'true', 'false', 'on', 'off'], true);
    }

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

        // URLs externas se devuelven tal cual.
        if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            return $val;
        }

        // Normalizar separadores de Windows.
        $val = str_replace('\\', '/', $val);

        // Rutas absolutas tipo /uploads/... o /images/... (front).
        // Si es /uploads, validar que el archivo exista; si no, devolver null para evitar thumbnails rotos.
        if (str_starts_with($val, '/')) {
            $cleanAbs = ltrim($val, '/');
            if (str_starts_with($cleanAbs, 'uploads/')) {
                $abs = public_path($cleanAbs);
                return is_file($abs) ? $val : null;
            }
            return $val;
        }

        // Si es una ruta relativa de uploads (productos/xxx.jpg), validar que exista.
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
        $categoria = $request->query('categoria');
        $destacado = $request->query('destacado');
        $buscar = trim((string) $request->query('buscar', ''));
        $limite = max(1, (int) ($request->query('limite', '50')));
        $pagina = max(1, (int) ($request->query('pagina', '1')));
        $skip = ($pagina - 1) * $limite;

        $q = DB::table('productos')->where('activo', 1);
        if ($categoria !== null && (int) $categoria > 0) {
            $q->where('categoria_id', (int) $categoria);
        }
        if ($destacado === 'true' || $destacado === '1') {
            $q->where('destacado', 1);
        }
        if ($buscar !== '') {
            $q->where(function ($w) use ($buscar) {
                $w->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('descripcion', 'like', "%{$buscar}%");
            });
        }

        $total = (int) $q->count();
        $items = $q
            ->orderBy('created_at', 'desc')
            ->offset($skip)
            ->limit($limite)
            ->get()
            ->map(function ($p) {
                $p->precio = (float) $p->precio;
                return $p;
            });

        // Adjuntar categoria_nombre (como en Nest)
        $catIds = $items->pluck('categoria_id')->filter()->unique()->values()->all();
        $cats = [];
        if (count($catIds) > 0) {
            $cats = DB::table('categorias')->whereIn('id', $catIds)->pluck('nombre', 'id')->all();
        }

        $productos = $items->map(function ($p) use ($cats) {
            $p->categoria_nombre = $p->categoria_id ? ($cats[$p->categoria_id] ?? null) : null;
            $p->imagen = $this->normalizeImagenField($p->imagen ?? null);
            return $p;
        });

        return response()->json([
            'productos' => $productos,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'totalPaginas' => (int) ceil($total / $limite),
            ],
        ], 200);
    }

    public function show(int $id)
    {
        $p = DB::table('productos')->where('id', $id)->where('activo', 1)->first();
        if (!$p) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Producto no encontrado',
                'message' => 'Producto no encontrado',
            ], 404);
        }
        $cat = $p->categoria_id
            ? DB::table('categorias')->select(['nombre'])->where('id', (int) $p->categoria_id)->first()
            : null;

        $p->precio = (float) $p->precio;
        $p->categoria_nombre = $cat?->nombre ?? null;
        $p->imagen = $this->normalizeImagenField($p->imagen ?? null);
        return response()->json($p, 200);
    }

    public function store(Request $request)
    {
        $this->coerceBooleanInput($request, 'destacado');

        try {
            $data = $request->validate([
                'nombre' => ['required', 'string', 'min:2', 'max:200'],
                'descripcion' => ['nullable', 'string', 'max:2000'],
                'precio' => ['required', 'numeric', 'min:0'],
                'categoria_id' => ['required', 'integer', 'min:1'],
                'stock' => ['nullable', 'integer', 'min:0'],
                'destacado' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->validateBooleanLike($value)) {
                        $fail('El campo destacado debe ser verdadero o falso.');
                    }
                }],
                'imagen_url' => ['nullable', 'url', 'max:500'],
                // Soporta formatos comunes que Windows/Android suelen producir (incluye jfif).
                'imagen' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,jfif', 'max:'.$this->maxUploadKb()],
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

        $categoria = DB::table('categorias')
            ->where('id', (int) $data['categoria_id'])
            ->where('activo', 1)
            ->first();
        if (!$categoria) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Categoría inválida',
                'message' => 'La categoría seleccionada no existe',
            ], 400);
        }

        $imagen = null;
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $dir = public_path('uploads/productos');
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $name = 'producto-' . now()->timestamp . '-' . Str::random(8) . '.' . $ext;
            $file->move($dir, $name);
            $imagen = 'productos/' . $name;
        } elseif (!empty($data['imagen_url'])) {
            $imagen = $data['imagen_url'];
        }

        $id = DB::table('productos')->insertGetId([
            'nombre' => trim($data['nombre']),
            'descripcion' => $data['descripcion'] ?? null,
            'precio' => (string) $data['precio'],
            'categoria_id' => (int) $data['categoria_id'],
            'imagen' => $imagen,
            'stock' => isset($data['stock']) ? (int) $data['stock'] : 0,
            'destacado' => !empty($data['destacado']) ? 1 : 0,
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $p = DB::table('productos')->where('id', $id)->first();
        $p->precio = (float) $p->precio;
        $p->categoria_nombre = $categoria->nombre ?? null;
        $p->imagen = $this->normalizeImagenField($p->imagen ?? null);

        try {
            $this->notificaciones->broadcastNewProduct((int) $id, (string) $p->nombre);
        } catch (\Throwable $e) {
            // No bloquear la creación si falla la notificación.
        }

        try {
            $correo = $this->productEmails->send($p);
        } catch (\Throwable) {
            $correo = [
                'enabled' => true,
                'sent' => 0,
                'failed' => 0,
                'message' => 'El producto fue creado, pero no se pudo iniciar el envio de correos.',
            ];
        }

        return response()->json([
            'statusCode' => 201,
            'message' => $correo['sent'] > 0
                ? 'Producto creado y clientes notificados por correo'
                : 'Producto creado exitosamente',
            'producto' => $p,
            'correo' => $correo,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $existente = DB::table('productos')->where('id', $id)->first();
        if (!$existente) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Producto no encontrado',
                'message' => 'El producto a actualizar no existe',
            ], 404);
        }

        $this->coerceBooleanInput($request, 'destacado');

        try {
            $data = $request->validate([
                'nombre' => ['sometimes', 'nullable', 'string', 'min:2', 'max:200'],
                'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'precio' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'categoria_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'destacado' => ['sometimes', 'nullable', function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->validateBooleanLike($value)) {
                        $fail('El campo destacado debe ser verdadero o falso.');
                    }
                }],
                'imagen_url' => ['sometimes', 'nullable', 'url', 'max:500'],
                'imagen' => ['sometimes', 'nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,jfif', 'max:'.$this->maxUploadKb()],
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
            $update['nombre'] = $nombre;
        }
        if (array_key_exists('descripcion', $data)) {
            $update['descripcion'] = $data['descripcion'] ?? null;
        }
        if (array_key_exists('precio', $data)) {
            if ($data['precio'] === null || !is_numeric($data['precio']) || (float) $data['precio'] < 0) {
                return response()->json([
                    'statusCode' => 400,
                    'error' => 'Datos inválidos',
                    'message' => 'El precio debe ser un número positivo',
                ], 400);
            }
            $update['precio'] = (string) $data['precio'];
        }
        if (array_key_exists('categoria_id', $data) && $data['categoria_id'] !== null) {
            $cat = DB::table('categorias')->where('id', (int) $data['categoria_id'])->where('activo', 1)->first();
            if (!$cat) {
                return response()->json([
                    'statusCode' => 400,
                    'error' => 'Categoría inválida',
                    'message' => 'La categoría seleccionada no existe',
                ], 400);
            }
            $update['categoria_id'] = (int) $data['categoria_id'];
        }
        if (array_key_exists('stock', $data)) {
            $update['stock'] = $data['stock'] === null ? 0 : (int) $data['stock'];
        }
        if (array_key_exists('destacado', $data)) {
            $update['destacado'] = !empty($data['destacado']) ? 1 : 0;
        }

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $dir = public_path('uploads/productos');
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $name = 'producto-' . now()->timestamp . '-' . Str::random(8) . '.' . $ext;
            $file->move($dir, $name);
            $update['imagen'] = 'productos/' . $name;
        } elseif (array_key_exists('imagen_url', $data)) {
            $update['imagen'] = $data['imagen_url'] ?: null;
        }

        if (count($update) === 0) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Sin cambios',
                'message' => 'No se proporcionaron datos para actualizar',
            ], 400);
        }

        $update['updated_at'] = now();
        DB::table('productos')->where('id', $id)->update($update);

        $p = DB::table('productos')->where('id', $id)->first();
        $p->precio = (float) $p->precio;
        $catNombre = $p->categoria_id ? DB::table('categorias')->where('id', (int) $p->categoria_id)->value('nombre') : null;
        $p->categoria_nombre = $catNombre ?: null;
        $p->imagen = $this->normalizeImagenField($p->imagen ?? null);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Producto actualizado exitosamente',
            'producto' => $p,
        ], 200);
    }

    public function updateImagen(Request $request, int $id)
    {
        $existente = DB::table('productos')->where('id', $id)->first();
        if (!$existente) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Producto no encontrado',
                'message' => 'El producto a actualizar no existe',
            ], 404);
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
        $dir = public_path('uploads/productos');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $name = 'producto-' . now()->timestamp . '-' . Str::random(8) . '.' . $ext;
        $file->move($dir, $name);

        DB::table('productos')->where('id', $id)->update([
            'imagen' => 'productos/' . $name,
            'updated_at' => now(),
        ]);

        $p = DB::table('productos')->where('id', $id)->first();
        $p->precio = (float) $p->precio;
        $catNombre = $p->categoria_id ? DB::table('categorias')->where('id', (int) $p->categoria_id)->value('nombre') : null;
        $p->categoria_nombre = $catNombre ?: null;
        $p->imagen = $this->normalizeImagenField($p->imagen ?? null);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Imagen actualizada',
            'producto' => $p,
        ], 200);
    }

    public function destroy(int $id)
    {
        $existente = DB::table('productos')->where('id', $id)->first();
        if (!$existente) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Producto no encontrado',
                'message' => 'El producto a eliminar no existe',
            ], 404);
        }

        DB::table('productos')->where('id', $id)->update([
            'activo' => 0,
            'updated_at' => now(),
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Producto eliminado exitosamente',
        ], 200);
    }
}
