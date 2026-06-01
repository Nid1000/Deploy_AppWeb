<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function home(Request $request): View
    {
        $productsResponse = $this->api->get('productos', ['limite' => 12]);
        $categoriesResponse = $this->api->get('categorias');
        $products = collect($this->api->okData($productsResponse, 'productos', []));
        $categories = collect($this->api->okData($categoriesResponse, null, []));

        $featuredProducts = $this->mapProducts(
            $products->sortByDesc(fn ($item) => !empty($item['destacado'] ?? false))->take(5)
        );

        $homeCategories = $this->mapCategories($categories)->take(3);

        return view('web.home', [
            'user' => $request->session()->get('web_user'),
            'featuredProducts' => $featuredProducts,
            'homeCategories' => $homeCategories,
            'galleryImages' => [
                asset('images/products/alfajores.jpg'),
                asset('images/products/delikeik.jpg'),
                asset('images/products/karamanduka.jpg'),
                asset('images/products/pionono.jpg'),
                asset('images/products/tostadas.jpg'),
            ],
            'testimonials' => [
                [
                    'nombre' => 'Maria G.',
                    'texto' => 'Las tortas personalizadas son espectaculares. La decoracion y el sabor superaron mis expectativas.',
                ],
                [
                    'nombre' => 'Luis P.',
                    'texto' => 'El pan artesanal siempre esta fresco y crujiente. La atencion es amable y rapida.',
                ],
                [
                    'nombre' => 'Andrea R.',
                    'texto' => 'Los alfajores y piononos son mis favoritos. Perfectos para compartir en familia.',
                ],
            ],
        ]);
    }

    public function products(Request $request): View
    {
        $filters = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'categoria' => (int) $request->query('categoria', 0),
            'precioMin' => $request->query('precioMin'),
            'precioMax' => $request->query('precioMax'),
            'destacado' => $request->boolean('destacado'),
            'disponible' => $request->query('disponible', '1') !== '0',
            'orden' => (string) $request->query('orden', 'nombre'),
        ];
        $query = array_filter([
            'pagina' => (int) $request->query('pagina', 1),
            'limite' => (int) $request->query('limite', 12),
            'buscar' => $filters['buscar'] ?: null,
            'categoria' => $filters['categoria'] > 0 ? $filters['categoria'] : null,
            'destacado' => $filters['destacado'] ? 1 : null,
        ], fn ($value) => $value !== null && $value !== '');

        $productsResponse = $this->api->get('productos', $query);
        $products = collect($this->api->okData($productsResponse, 'productos', []));
        $pagination = (array) $this->api->okData($productsResponse, 'pagination', []);
        $categoriesResponse = $this->api->get('categorias');
        $categories = $this->mapCategories(collect($this->api->okData($categoriesResponse, null, [])));

        if ($filters['disponible']) {
            $products = $products->filter(fn ($item) => (int) ($item['stock'] ?? 0) > 0);
        }
        if (is_numeric($filters['precioMin'])) {
            $products = $products->filter(fn ($item) => (float) ($item['precio'] ?? 0) >= (float) $filters['precioMin']);
        }
        if (is_numeric($filters['precioMax'])) {
            $products = $products->filter(fn ($item) => (float) ($item['precio'] ?? 0) <= (float) $filters['precioMax']);
        }
        $products = (match ($filters['orden']) {
            'precio_asc' => $products->sortBy(fn ($item) => (float) ($item['precio'] ?? 0)),
            'precio_desc' => $products->sortByDesc(fn ($item) => (float) ($item['precio'] ?? 0)),
            'destacado' => $products->sortByDesc(fn ($item) => !empty($item['destacado'] ?? false)),
            default => $products->sortBy(fn ($item) => (string) ($item['nombre'] ?? '')),
        })->values();

        return view('web.products.index', [
            'products' => $this->mapProducts($products),
            'pagination' => $pagination,
            'categories' => $categories,
            'filters' => $filters,
            'perPage' => (int) ($query['limite'] ?? 12),
        ]);
    }

    public function categories(): View
    {
        $categoriesResponse = $this->api->get('categorias');
        $categories = $this->mapCategories(collect($this->api->okData($categoriesResponse, null, [])))
            ->sortBy(fn ($category) => mb_strtolower((string) $category->nombre))
            ->values();

        return view('web.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function contact(): View
    {
        return view('web.contact.index');
    }

    public function showProduct(Request $request, int $id): View
    {
        $productResponse = $this->api->get('productos/' . $id);
        abort_unless($productResponse->successful(), 404);
        $product = $this->mapProduct((object) $productResponse->json());

        $relatedResponse = $this->api->get('productos', [
            'categoria' => $product->categoria_id ?: null,
            'limite' => 8,
        ]);
        $related = $this->mapProducts(
            collect($this->api->okData($relatedResponse, 'productos', []))
                ->filter(fn ($item) => (int) ($item['id'] ?? 0) !== $id)
                ->take(4)
        );

        return view('web.products.show', [
            'product' => $product,
            'relatedProducts' => $related,
        ]);
    }

    private function mapProducts(Collection $products): Collection
    {
        return $products->map(function ($product) {
            if (is_array($product)) {
                $product = (object) $product;
            }
            return $this->mapProduct($product);
        });
    }

    private function mapCategories(Collection $categories): Collection
    {
        return $categories->map(function ($category) {
            if (is_array($category)) {
                $category = (object) $category;
            }

            $category->imagen_url = $this->resolvePublicImage($category->imagen ?? null, '/images/categories/pan.png');
            $category->descripcion = ($category->descripcion ?? null)
                ?: 'Explora productos artesanales preparados con ingredientes frescos y mucho cuidado.';

            return $category;
        })->values();
    }

    private function mapProduct(object $product): object
    {
        $product->precio = (float) $product->precio;
        $product->imagen_url = $this->resolvePublicImage($product->imagen, '/images/products/alfajores.jpg');
        $product->agotado = ((int) ($product->stock ?? 0)) <= 0;
        $product->stock_bajo = ((int) ($product->stock ?? 0)) > 0 && ((int) $product->stock <= 5);

        return $product;
    }

    private function resolvePublicImage(null|string $image, string $fallback): string
    {
        $value = trim((string) $image);
        if ($value === '') {
            return asset(ltrim($fallback, '/'));
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $value = str_replace('\\', '/', $value);
        if (str_starts_with($value, '/')) {
            if (str_starts_with($value, '/uploads/')) {
                return $this->api->publicUrl($value);
            }

            return asset(ltrim($value, '/'));
        }

        return $this->api->publicUrl('uploads/' . ltrim($value, '/'));
    }
}
