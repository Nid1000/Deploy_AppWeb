<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use App\Support\SiteSettings;
use App\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class AdminWebController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function dashboard(): View
    {
        $productsResponse = $this->api->get('productos', ['pagina' => 1, 'limite' => 1]);
        $categoriesResponse = $this->api->get('categorias/admin/todos', ['pagina' => 1, 'limite' => 1]);
        $usersResponse = $this->api->get('usuarios/admin/todos', ['pagina' => 1, 'limite' => 1]);
        $dailySalesResponse = $this->api->get('reportes/admin/ventas-diarias', [
            'desde' => now()->subDays(6)->toDateString(),
            'hasta' => now()->toDateString(),
        ]);
        $receiptsResponse = $this->api->get('facturacion/admin/comprobantes', ['pagina' => 1, 'limite' => 200]);

        $salesSeries = collect($this->api->okData($dailySalesResponse, 'data', []))->map(function ($item) {
            return (object) [
                'fecha' => data_get($item, 'fecha'),
                'total' => (float) data_get($item, 'total', 0),
            ];
        });
        $receipts = collect($this->api->okData($receiptsResponse, 'comprobantes', []));

        return view('admin.dashboard', [
            'metrics' => [
                'productos' => (int) data_get($productsResponse->json(), 'pagination.total', 0),
                'categorias' => (int) data_get($categoriesResponse->json(), 'pagination.total', 0),
                'usuarios' => (int) data_get($usersResponse->json(), 'pagination.total', 0),
                'ventasSemana' => (float) $salesSeries->sum('total'),
            ],
            'salesSeries' => $salesSeries,
            'receiptTypes' => [
                'boleta' => (int) $receipts->where('tipo', 'boleta')->count(),
                'factura' => (int) $receipts->where('tipo', 'factura')->count(),
            ],
        ]);
    }

    public function categoriesIndex(Request $request): View
    {
        $filters = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'activo' => (string) $request->query('activo', ''),
        ];
        $response = $this->api->get('categorias/admin/todos', array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => 20,
            'buscar' => $filters['buscar'] ?: null,
            'activo' => $filters['activo'] === '' ? null : ($filters['activo'] === '1' ? 'true' : 'false'),
        ], fn ($value) => $value !== null && $value !== ''));

        return view('admin.categories.index', [
            'categories' => $this->mapCategories(collect($this->api->okData($response, 'categorias', []))),
            'filters' => $filters,
            'pagination' => (array) $this->api->okData($response, 'pagination', []),
        ]);
    }

    public function categoriesCreate(): View
    {
        return view('admin.categories.create');
    }

    public function categoriesStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'imagen' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,jfif', 'max:5120'],
        ]);
        $response = $this->api->post('categorias/admin', [
            'nombre' => trim($data['nombre']),
            'descripcion' => $data['descripcion'] ?? null,
        ]);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo crear la categoria.'));
        }
        $id = (int) data_get($response->json(), 'categoria.id', 0);
        if ($id > 0 && $request->hasFile('imagen')) {
            $imageResponse = $this->api->postMultipart('categorias/admin/' . $id . '/imagen', [], 'imagen', $request->file('imagen'));
            if (!$imageResponse->successful()) {
                return redirect()->route('web.admin.categories.edit', $id)
                    ->with('error', $this->api->errorMessage($imageResponse, 'Categoria creada, pero no se pudo subir la imagen.'));
            }
        }

        return redirect()->route('web.admin.categories.edit', $id)->with('success', 'Categoria creada correctamente.');
    }

    public function categoriesEdit(int $id): View
    {
        $response = $this->api->get('categorias/admin/' . $id);
        abort_unless($response->successful(), 404);
        $category = $this->mapCategory((array) $this->api->okData($response, 'categoria', []));

        return view('admin.categories.edit', ['category' => $category]);
    }

    public function categoriesUpdate(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'imagen' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,jfif', 'max:5120'],
        ]);
        $response = $this->api->put('categorias/admin/' . $id, [
            'nombre' => trim($data['nombre']),
            'descripcion' => $data['descripcion'] ?? null,
        ]);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar la categoria.'));
        }
        if ($request->hasFile('imagen')) {
            $imageResponse = $this->api->postMultipart('categorias/admin/' . $id . '/imagen', [], 'imagen', $request->file('imagen'));
            if (!$imageResponse->successful()) {
                return back()->with('error', $this->api->errorMessage($imageResponse, 'Categoria actualizada, pero no se pudo subir la imagen.'));
            }
        }

        return back()->with('success', 'Categoria actualizada.');
    }

    public function categoriesToggle(int $id): RedirectResponse
    {
        $response = $this->api->get('categorias/admin/' . $id);
        if (!$response->successful()) {
            return back()->with('error', 'Categoria no encontrada.');
        }
        $current = (bool) data_get($response->json(), 'categoria.activo', false);
        $toggle = $this->api->patch('categorias/admin/' . $id . '/estado', ['activo' => !$current]);
        if (!$toggle->successful()) {
            return back()->with('error', $this->api->errorMessage($toggle, 'No se pudo actualizar la categoria.'));
        }

        return back()->with('success', 'Estado de la categoria actualizado.');
    }

    public function categoriesDelete(int $id): RedirectResponse
    {
        $response = $this->api->delete('categorias/admin/' . $id);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo desactivar la categoria.'));
        }

        return redirect()->route('web.admin.categories.index')->with('success', 'Categoria desactivada.');
    }

    public function productsIndex(Request $request): View
    {
        $filters = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'categoria' => (int) $request->query('categoria', 0),
            'stock_bajo' => $request->boolean('stock_bajo'),
        ];
        $response = $this->api->get('productos', array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => 20,
            'buscar' => $filters['buscar'] ?: null,
            'categoria' => $filters['categoria'] > 0 ? $filters['categoria'] : null,
        ], fn ($value) => $value !== null && $value !== ''));
        $products = collect($this->api->okData($response, 'productos', []));
        if ($filters['stock_bajo']) {
            $products = $products->filter(fn ($product) => (int) ($product['stock'] ?? 0) <= 5);
        }

        return view('admin.products.index', [
            'products' => $this->mapProducts($products),
            'categories' => $this->allCategories(),
            'filters' => $filters,
            'pagination' => (array) $this->api->okData($response, 'pagination', []),
        ]);
    }

    public function productsCreate(): View
    {
        return view('admin.products.create', [
            'categories' => $this->allCategories(),
        ]);
    }

    public function productsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio' => ['required', 'numeric', 'min:0'],
            'categoria_id' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'destacado' => ['nullable', 'boolean'],
            'imagen' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,jfif', 'max:5120'],
        ]);
        $payload = [
            'nombre' => trim($data['nombre']),
            'descripcion' => $data['descripcion'] ?? null,
            'precio' => $data['precio'],
            'categoria_id' => (int) $data['categoria_id'],
            'stock' => (int) $data['stock'],
            'destacado' => $request->boolean('destacado'),
        ];
        $response = $request->hasFile('imagen')
            ? $this->api->postMultipart('productos', $payload, 'imagen', $request->file('imagen'))
            : $this->api->post('productos', $payload);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo crear el producto.'));
        }
        $id = (int) data_get($response->json(), 'producto.id', 0);

        return redirect()->route('web.admin.products.edit', $id)->with('success', 'Producto creado correctamente.');
    }

    public function productsEdit(int $id): View
    {
        $response = $this->api->get('productos/' . $id);
        abort_unless($response->successful(), 404);
        $product = $this->mapProduct((array) $response->json());

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => $this->allCategories(),
        ]);
    }

    public function productsUpdate(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio' => ['required', 'numeric', 'min:0'],
            'categoria_id' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'destacado' => ['nullable', 'boolean'],
            'imagen' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,jfif', 'max:5120'],
        ]);
        $response = $this->api->put('productos/' . $id, [
            'nombre' => trim($data['nombre']),
            'descripcion' => $data['descripcion'] ?? null,
            'precio' => $data['precio'],
            'categoria_id' => (int) $data['categoria_id'],
            'stock' => (int) $data['stock'],
            'destacado' => $request->boolean('destacado'),
        ]);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar el producto.'));
        }
        if ($request->hasFile('imagen')) {
            $imageResponse = $this->api->postMultipart('productos/' . $id . '/imagen', [], 'imagen', $request->file('imagen'));
            if (!$imageResponse->successful()) {
                return back()->with('error', $this->api->errorMessage($imageResponse, 'Producto actualizado, pero no se pudo subir la imagen.'));
            }
        }

        return back()->with('success', 'Producto actualizado.');
    }

    public function productsToggleFeatured(Request $request, int $id): RedirectResponse
    {
        $destacado = !$request->boolean('destacado_actual');
        $response = $this->api->put('productos/' . $id, ['destacado' => $destacado]);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar el destacado.'));
        }

        return back()->with('success', 'Preferencia de destacado actualizada.');
    }

    public function productsDelete(int $id): RedirectResponse
    {
        $response = $this->api->delete('productos/' . $id);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo desactivar el producto.'));
        }

        return redirect()->route('web.admin.products.index')->with('success', 'Producto desactivado.');
    }

    public function usersIndex(Request $request): View
    {
        $filters = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'estado' => (string) $request->query('estado', ''),
        ];
        $response = $this->api->get('usuarios/admin/todos', array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => 20,
            'buscar' => $filters['buscar'] ?: null,
            'activo' => $filters['estado'] === '' ? null : ($filters['estado'] === 'activos' ? 'true' : 'false'),
        ], fn ($value) => $value !== null && $value !== ''));

        return view('admin.users.index', [
            'users' => collect($this->api->okData($response, 'usuarios', []))->map(fn ($user) => (object) $user),
            'filters' => $filters,
            'pagination' => (array) $this->api->okData($response, 'pagination', []),
        ]);
    }

    public function usersShow(int $id): View
    {
        $response = $this->api->get('usuarios/admin/' . $id);
        abort_unless($response->successful(), 404);
        $user = (object) $this->api->okData($response, 'usuario', []);
        $stats = [
            'total_pedidos' => (int) data_get($response->json(), 'usuario.estadisticas.total_pedidos', 0),
            'total_gastado' => (float) data_get($response->json(), 'usuario.estadisticas.total_gastado', 0),
        ];

        return view('admin.users.show', compact('user', 'stats'));
    }

    public function usersUpdate(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2'],
            'apellido' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string'],
            'distrito' => ['nullable', 'string', 'max:120'],
            'numero_casa' => ['nullable', 'string', 'max:20'],
        ]);
        $response = $this->api->put('usuarios/admin/' . $id, $data);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar el usuario.'));
        }

        return back()->with('success', 'Usuario actualizado.');
    }

    public function usersToggle(int $id): RedirectResponse
    {
        $show = $this->api->get('usuarios/admin/' . $id);
        if (!$show->successful()) {
            return back()->with('error', 'Usuario no encontrado.');
        }
        $activo = !(bool) data_get($show->json(), 'usuario.activo', false);
        $response = $this->api->patch('usuarios/admin/' . $id . '/estado', ['activo' => $activo]);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar el estado.'));
        }

        return back()->with('success', 'Estado del usuario actualizado.');
    }

    public function ordersIndex(Request $request): View
    {
        $filters = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'estado' => (string) $request->query('estado', ''),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];
        $response = $this->api->get('pedidos/admin/todos', array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => 20,
            'buscar' => $filters['buscar'] ?: null,
            'estado' => $filters['estado'] ?: null,
            'desde' => $filters['desde'] ?: null,
            'hasta' => $filters['hasta'] ?: null,
        ], fn ($value) => $value !== null && $value !== ''));

        return view('admin.orders.index', [
            'orders' => collect($this->api->okData($response, 'pedidos', []))->map(fn ($item) => (object) $item),
            'filters' => $filters,
            'pagination' => (array) $this->api->okData($response, 'pagination', []),
        ]);
    }

    public function ordersShow(int $id): View
    {
        $response = $this->api->get('pedidos/admin/' . $id);
        abort_unless($response->successful(), 404);
        $order = (object) $this->api->okData($response, 'pedido', []);
        $details = collect($this->api->okData($response, 'detalles', []))->map(function ($detail) {
            $detail = (object) $detail;
            $detail->precio_unitario = (float) ($detail->precio_unitario ?? 0);
            $detail->subtotal = (float) ($detail->subtotal ?? 0);
            $detail->producto_imagen_url = StorefrontCart::imageUrl($detail->producto_imagen ?? null);
            return $detail;
        });

        return view('admin.orders.show', compact('order', 'details'));
    }

    public function ordersUpdateState(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'in:pendiente,listo,entregado,cancelado'],
        ]);
        $response = $this->api->patch('pedidos/admin/' . $id . '/estado', $data);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar el estado.'));
        }

        return back()->with('success', 'Estado del pedido actualizado.');
    }

    public function ordersUpdateShipping(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'salida_reparto_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'conductor' => ['nullable', 'string', 'max:191'],
            'vehiculo' => ['nullable', 'string', 'max:191'],
        ]);
        $response = $this->api->put('pedidos/admin/' . $id . '/reparto', $data);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar el reparto.'));
        }

        return back()->with('success', 'Datos de reparto actualizados.');
    }

    public function ordersUpdateDeliveryDate(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'fecha_entrega' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $response = $this->api->put('pedidos/admin/' . $id . '/fecha-entrega', $data);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar la fecha.'));
        }

        return back()->with('success', 'Fecha de entrega actualizada.');
    }

    public function receiptsIndex(Request $request): View
    {
        $filters = [
            'tipo' => (string) $request->query('tipo', ''),
            'buscar' => trim((string) $request->query('buscar', '')),
        ];
        $response = $this->api->get('facturacion/admin/comprobantes', array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => 20,
            'tipo' => $filters['tipo'] ?: null,
        ], fn ($value) => $value !== null && $value !== ''));
        $receipts = collect($this->api->okData($response, 'comprobantes', []))
            ->filter(function ($receipt) use ($filters) {
                if ($filters['buscar'] === '') {
                    return true;
                }
                $haystack = mb_strtolower((string) (($receipt['numero_formateado'] ?? '') . ' ' . data_get($receipt, 'cliente.nombre', '')));
                return str_contains($haystack, mb_strtolower($filters['buscar']));
            })
            ->map(function ($receipt) {
                $receipt = (object) $receipt;
                $receipt->pdf_url = !empty($receipt->archivos['pdf'] ?? null) ? $this->api->publicUrl($receipt->archivos['pdf']) : null;
                $receipt->xml_url = !empty($receipt->archivos['xml'] ?? null) ? $this->api->publicUrl($receipt->archivos['xml']) : null;
                $receipt->img_url = !empty($receipt->archivos['img'] ?? null) ? $this->api->publicUrl($receipt->archivos['img']) : null;
                return $receipt;
            });

        return view('admin.receipts.index', [
            'receipts' => $receipts,
            'filters' => $filters,
            'pagination' => [
                'total' => (int) data_get($response->json(), 'total', $receipts->count()),
                'pagina' => (int) data_get($response->json(), 'pagina', 1),
                'totalPaginas' => (int) data_get($response->json(), 'totalPaginas', 1),
            ],
        ]);
    }

    public function reservationsIndex(Request $request): View
    {
        $filters = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'estado' => (string) $request->query('estado', ''),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];
        $response = $this->api->get('reservas/admin/todas', array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => 20,
            'buscar' => $filters['buscar'] ?: null,
            'estado' => $filters['estado'] ?: null,
            'desde' => $filters['desde'] ?: null,
            'hasta' => $filters['hasta'] ?: null,
        ], fn ($value) => $value !== null && $value !== ''));

        return view('admin.reservations.index', [
            'reservations' => collect($this->api->okData($response, 'reservas', []))->map(fn ($item) => (object) $item),
            'filters' => $filters,
            'pagination' => (array) $this->api->okData($response, 'pagination', []),
        ]);
    }

    public function reservationsUpdateState(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'in:pendiente,confirmada,asistio,cancelada'],
        ]);
        $response = $this->api->patch('reservas/admin/' . $id . '/estado', $data);
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo actualizar la reserva.'));
        }

        return back()->with('success', 'Reserva actualizada.');
    }

    public function reservationsExport(Request $request)
    {
        return $this->downloadFromApi('reservas/admin/exportar', $request->only(['buscar', 'estado', 'desde', 'hasta']));
    }

    public function warehouseIndex(Request $request): View
    {
        $filters = [
            'producto_id' => (int) $request->query('producto_id', 0),
            'tipo_movimiento' => (string) $request->query('tipo_movimiento', ''),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];
        $response = $this->api->get('almacen/admin/movimientos', array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => 20,
            'producto_id' => $filters['producto_id'] > 0 ? $filters['producto_id'] : null,
            'tipo_movimiento' => $filters['tipo_movimiento'] ?: null,
            'desde' => $filters['desde'] ?: null,
            'hasta' => $filters['hasta'] ?: null,
        ], fn ($value) => $value !== null && $value !== ''));

        return view('admin.warehouse.index', [
            'movements' => collect($this->api->okData($response, 'movimientos', []))->map(fn ($item) => (object) $item),
            'products' => $this->allProducts(),
            'filters' => $filters,
            'pagination' => (array) $this->api->okData($response, 'pagination', []),
        ]);
    }

    public function warehouseStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'min:1'],
            'tipo_movimiento' => ['required', 'in:entrada,salida'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);
        $response = $this->api->post('almacen/admin/movimientos', $data);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo registrar el movimiento.'));
        }

        return back()->with('success', 'Movimiento de almacen registrado.');
    }

    public function warehouseExport(Request $request)
    {
        return $this->downloadFromApi('almacen/admin/exportar', $request->only(['producto_id', 'tipo_movimiento', 'desde', 'hasta']));
    }

    public function reportsIndex(Request $request): View
    {
        $mode = (string) $request->query('modo', 'diario');
        $from = $request->query('desde');
        $to = $request->query('hasta');
        $query = array_filter([
            'desde' => $from ?: null,
            'hasta' => $to ?: null,
            'limite' => 8,
        ], fn ($value) => $value !== null && $value !== '');
        $seriesEndpoint = match ($mode) {
            'semanal' => 'reportes/admin/ventas-semanales',
            'mensual' => 'reportes/admin/ventas-mensuales',
            default => 'reportes/admin/ventas-diarias',
        };
        $seriesResponse = $this->api->get($seriesEndpoint, $query);
        $productsResponse = $this->api->get('reportes/admin/top-productos', $query);
        $categoriesResponse = $this->api->get('reportes/admin/top-categorias', $query);

        $series = collect($this->api->okData($seriesResponse, 'data', []))->map(function ($item) use ($mode) {
            return (object) [
                'label' => data_get($item, match ($mode) {
                    'semanal' => 'semana',
                    'mensual' => 'mes',
                    default => 'fecha',
                }),
                'total' => (float) data_get($item, 'total', 0),
            ];
        });
        $topProducts = collect($this->api->okData($productsResponse, 'data', []))->map(fn ($item) => $this->mapProduct($item));
        $topCategories = collect($this->api->okData($categoriesResponse, 'data', []))->map(fn ($item) => (object) $item);

        return view('admin.reports.index', [
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
            'series' => $series,
            'topProducts' => $topProducts,
            'topCategories' => $topCategories,
        ]);
    }

    public function reportsExport(Request $request, string $tipo)
    {
        $endpoint = match ($tipo) {
            'pedidos' => 'reportes/admin/exportar/pedidos',
            'productos' => 'reportes/admin/exportar/productos',
            default => 'reportes/admin/exportar/ventas',
        };

        return $this->downloadFromApi($endpoint, $request->only(['modo', 'desde', 'hasta']));
    }

    public function settingsIndex(): View
    {
        return view('admin.settings.index', [
            'settings' => SiteSettings::get(),
        ]);
    }

    public function settingsUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'moneda' => ['required', 'string', 'max:10'],
            'prefijo' => ['required', 'string', 'max:20'],
            'branding' => ['required', 'string', 'max:120'],
        ]);

        SiteSettings::put($data);

        return back()->with('success', 'Configuracion guardada.');
    }

    public function sendNotification(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:600'],
            'audience' => ['required', 'in:web,mobile,both'],
            'route' => ['nullable', 'string', 'max:50'],
            'targetId' => ['nullable', 'string', 'max:50'],
            'userId' => ['nullable', 'integer'],
        ]);
        $response = $this->api->post('notificaciones/admin/enviar', $data);
        if (!$response->successful()) {
            return back()->withInput()->with('error', $this->api->errorMessage($response, 'No se pudo enviar la notificacion.'));
        }

        return back()->with('success', 'Notificacion enviada correctamente.');
    }

    public function markNotificationsSeen(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $response = $this->api->post('notificaciones/admin/marcar-mostradas', [
            'ids' => $data['ids'] ?? [],
        ]);

        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudieron marcar las notificaciones.'));
        }

        return back();
    }

    private function allCategories(): Collection
    {
        $response = $this->api->get('categorias/admin/todos', ['pagina' => 1, 'limite' => 200, 'activo' => 'true']);
        return $this->mapCategories(collect($this->api->okData($response, 'categorias', [])));
    }

    private function allProducts(): Collection
    {
        $response = $this->api->get('productos', ['pagina' => 1, 'limite' => 500]);
        return $this->mapProducts(collect($this->api->okData($response, 'productos', [])));
    }

    private function downloadFromApi(string $path, array $query)
    {
        $response = $this->api->get($path, array_filter($query, fn ($value) => $value !== null && $value !== ''));
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo descargar el archivo.'));
        }

        $report = $this->resolveExportReport($path);
        $rows = $this->parseCsvRows($response->body());

        if ($rows === []) {
            return back()->with('error', 'El archivo exportado esta vacio.');
        }

        $html = $this->renderExcelHtmlReport($report, $rows);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $report['filename'] . '"',
        ]);
    }

    private function resolveExportReport(string $path): array
    {
        return match ($path) {
            'reservas/admin/exportar' => [
                'key' => 'reservas',
                'title' => 'REPORTE DE RESERVAS',
                'accent' => '#7c3aed',
                'accent_dark' => '#5b21b6',
                'accent_soft' => '#f3e8ff',
                'filename' => 'reporte-reservas-' . now()->format('Y-m-d') . '.xls',
            ],
            'almacen/admin/exportar' => [
                'key' => 'almacen',
                'title' => 'REPORTE DE ALMACEN',
                'accent' => '#334155',
                'accent_dark' => '#1e293b',
                'accent_soft' => '#e2e8f0',
                'filename' => 'reporte-almacen-' . now()->format('Y-m-d') . '.xls',
            ],
            'reportes/admin/exportar/pedidos' => [
                'key' => 'pedidos',
                'title' => 'REPORTE DE PEDIDOS',
                'accent' => '#2563eb',
                'accent_dark' => '#1d4ed8',
                'accent_soft' => '#dbeafe',
                'filename' => 'reporte-pedidos-' . now()->format('Y-m-d') . '.xls',
            ],
            'reportes/admin/exportar/productos' => [
                'key' => 'productos',
                'title' => 'REPORTE DE PRODUCTOS',
                'accent' => '#16a34a',
                'accent_dark' => '#15803d',
                'accent_soft' => '#dcfce7',
                'filename' => 'reporte-productos-' . now()->format('Y-m-d') . '.xls',
            ],
            default => [
                'key' => 'ventas',
                'title' => 'REPORTE DE VENTAS',
                'accent' => '#f97316',
                'accent_dark' => '#ea580c',
                'accent_soft' => '#ffedd5',
                'filename' => 'reporte-ventas-' . now()->format('Y-m-d') . '.xls',
            ],
        };
    }

    private function parseCsvRows(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }

        $delimiter = $this->detectReportDelimiter($csv);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function detectReportDelimiter(string $csv): string
    {
        $firstLine = strtok($csv, "\n") ?: '';
        $semicolonCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');

        if ($semicolonCount > $commaCount) {
            return ';';
        }

        return ',';
    }

    private function renderExcelHtmlReport(array $report, array $rows): string
    {
        $rows = $this->normalizeReportRows($rows);
        $headers = array_shift($rows) ?? [];
        $headers = array_map(
            fn ($value) => $this->normalizeReportCell($value),
            $headers
        );
        $colspan = max(1, count($headers));

        $safeTitle = htmlspecialchars((string) ($report['title'] ?? 'REPORTE'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $accent = htmlspecialchars((string) ($report['accent'] ?? '#334155'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $accentDark = htmlspecialchars((string) ($report['accent_dark'] ?? '#1e293b'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $accentSoft = htmlspecialchars((string) ($report['accent_soft'] ?? '#e2e8f0'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $generatedAt = htmlspecialchars(now()->format('d/m/Y H:i'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $brandName = htmlspecialchars((string) (SiteSettings::get()['branding'] ?? 'Delicias'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logoDataUri = $this->reportLogoDataUri();
        $summary = $this->buildReportSummary($report['key'] ?? 'ventas', $headers, $rows);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            body{font-family:Arial,Helvetica,sans-serif;background:#f7f7f7;color:#111;margin:0;padding:24px}
            .sheet{max-width:1400px;margin:0 auto;background:#fff;padding:24px;border:1px solid #d1d5db}
            .brand-table{width:100%;border-collapse:collapse;margin-bottom:14px}
            .brand-top{padding:14px 16px;border:2px solid #000;background:linear-gradient(135deg,' . $accentSoft . ',#ffffff)}
            .brand-inner{display:flex;align-items:center;gap:14px}
            .brand-logo{width:52px;height:52px;object-fit:cover;border-radius:14px;border:2px solid ' . $accentDark . ';background:#fff}
            .brand-name{font-size:15px;font-weight:700;color:' . $accentDark . ';text-transform:uppercase;letter-spacing:.12em}
            .brand-title{margin-top:3px;font-size:24px;font-weight:700;color:' . $accentDark . ';line-height:1.1}
            .brand-subtitle{margin-top:4px;font-size:12px;color:#475569}
            .title-row td,.subtitle-row td,.meta-row td{border:1px solid #000}
            .title-cell{padding:14px 12px;text-align:center;font-size:24px;font-weight:700;color:' . $accentDark . ';letter-spacing:.5px;background:#f8fafc;white-space:nowrap}
            .subtitle-cell{padding:8px 12px;text-align:center;font-size:12px;color:#6b7280;background:#fff}
            .meta-cell{padding:8px 12px;text-align:center;font-size:12px;color:#374151;background:#f9fafb}
            .summary-table{width:100%;border-collapse:collapse;margin:14px 0 18px}
            .summary-title{padding:10px 12px;border:2px solid #000;background:' . $accentSoft . ';color:' . $accentDark . ';font-weight:700;text-transform:uppercase}
            .summary-grid{width:100%;border-collapse:collapse}
            .summary-grid td{border:1px solid #000;padding:10px 12px;background:#fff}
            .summary-label{font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em}
            .summary-value{margin-top:4px;font-size:18px;font-weight:700;color:' . $accentDark . '}
            table{width:100%;border-collapse:collapse;border:2px solid #000;background:#fff}
            th,td{border:1px solid #000;padding:8px 10px;font-size:12px;vertical-align:top}
            thead th{background:' . $accentDark . ';color:#fff;font-weight:700;text-transform:uppercase;text-align:center}
            tbody td{background:#fffdf8}
            tbody tr:nth-child(even) td{background:#f6f7f9}
        </style></head><body><div class="sheet">';
        $html .= '<table class="brand-table">';
        $html .= '<tr><td class="brand-top">';
        $html .= '<div class="brand-inner">';
        if ($logoDataUri !== '') {
            $html .= '<img src="' . $logoDataUri . '" class="brand-logo" alt="Logo">';
        }
        $html .= '<div>';
        $html .= '<div class="brand-name">' . $brandName . '</div>';
        $html .= '<div class="brand-title">' . $safeTitle . '</div>';
        $html .= '<div class="brand-subtitle">Reporte generado el ' . $generatedAt . '</div>';
        $html .= '</div></div></td></tr>';
        $html .= '</table>';

        if (!empty($summary)) {
            $html .= '<table class="summary-table">';
            $html .= '<tr><td colspan="' . $colspan . '" class="summary-title">Resumen general</td></tr>';
            $html .= '<tr><td>';
            $html .= '<table class="summary-grid"><tr>';
            foreach ($summary as $item) {
                $html .= '<td><div class="summary-label">' . htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                $html .= '<div class="summary-value">' . htmlspecialchars($item['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></td>';
            }
            $html .= '</tr></table>';
            $html .= '</td></tr>';
            $html .= '</table>';
        }

        $html .= '<table><thead><tr>';

        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars((string) $header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $index => $header) {
                $value = $this->normalizeReportCell($row[$index] ?? '');
                $html .= '<td>' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></body></html>';

        return $html;
    }

    private function reportLogoDataUri(): string
    {
        $path = public_path('images/logos/logo 1.png');
        if (!is_file($path)) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($path));

        return 'data:' . $mime . ';base64,' . $data;
    }

    private function buildReportSummary(string $reportKey, array $headers, array $rows): array
    {
        $summary = [
            [
                'label' => 'Registros',
                'value' => number_format(count($rows)),
            ],
        ];

        $numericColumns = [];
        foreach ($headers as $index => $header) {
            $normalized = mb_strtolower(trim((string) $header));
            if (preg_match('/(total|subtotal|cantidad|stock|importe|monto|ventas)/u', $normalized)) {
                $numericColumns[] = $index;
            }
        }

        foreach (array_slice($numericColumns, 0, 3) as $index) {
            $sum = 0.0;
            foreach ($rows as $row) {
                $value = $row[$index] ?? '';
                if (is_numeric($value)) {
                    $sum += (float) $value;
                }
            }

            $summary[] = [
                'label' => (string) ($headers[$index] ?? 'Valor'),
                'value' => 'S/ ' . number_format($sum, 2),
            ];
        }

        if ($reportKey === 'reservas') {
            $summary[] = [
                'label' => 'Nota',
                'value' => 'Reporte de reservas',
            ];
        }

        return array_slice($summary, 0, 4);
    }

    private function normalizeReportRows(array $rows): array
    {
        $normalized = [];
        $headerFound = false;

        foreach ($rows as $row) {
            $row = array_map(
                fn ($value) => $this->normalizeReportCell($value),
                is_array($row) ? $row : [$row]
            );

            $cells = array_values(array_filter($row, fn ($value) => trim((string) $value) !== ''));

            if (!$headerFound) {
                if (count($cells) <= 1) {
                    continue;
                }

                $headerFound = true;
                $normalized[] = $row;
                continue;
            }

            if (count($cells) <= 1) {
                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    private function normalizeReportCell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'SI' : 'NO';
        }

        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/', $text)) {
            try {
                return Carbon::parse($text)->format('d/m/Y H:i');
            } catch (\Throwable) {
            }
        }

        return $text;
    }

    private function mapCategories(Collection $categories): Collection
    {
        return $categories->map(fn ($category) => $this->mapCategory((array) $category));
    }

    private function mapCategory(array $category): object
    {
        $row = (object) $category;
        $row->imagen_url = StorefrontCart::imageUrl($row->imagen ?? null, '/images/categories/pan.png');
        return $row;
    }

    private function mapProducts(Collection $products): Collection
    {
        return $products->map(fn ($product) => $this->mapProduct((array) $product));
    }

    private function mapProduct(array $product): object
    {
        $row = (object) $product;
        $row->precio = (float) ($row->precio ?? 0);
        $row->imagen_url = StorefrontCart::imageUrl($row->imagen ?? null);
        return $row;
    }
}
