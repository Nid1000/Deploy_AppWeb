<?php

namespace App\Support;

use App\Services\BackendApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorefrontCart
{
    public static function imageUrl(null|string $image, string $fallback = '/images/products/alfajores.jpg'): string
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
                return app(BackendApiClient::class)->publicUrl($value);
            }

            return asset(ltrim($value, '/'));
        }

        return app(BackendApiClient::class)->publicUrl('uploads/' . ltrim($value, '/'));
    }

    public static function items(Request $request): Collection
    {
        return collect($request->session()->get('storefront_cart', []))
            ->map(function ($item) {
                $price = (float) ($item['precio'] ?? 0);
                $quantity = max(1, (int) ($item['cantidad'] ?? 1));

                return (object) [
                    'id' => (int) ($item['id'] ?? 0),
                    'nombre' => (string) ($item['nombre'] ?? 'Producto'),
                    'precio' => $price,
                    'cantidad' => $quantity,
                    'stock' => (int) ($item['stock'] ?? 0),
                    'imagen' => $item['imagen'] ?? null,
                    'imagen_url' => self::imageUrl($item['imagen'] ?? null),
                    'categoria_id' => isset($item['categoria_id']) ? (int) $item['categoria_id'] : null,
                    'categoria_nombre' => $item['categoria_nombre'] ?? null,
                    'subtotal' => $price * $quantity,
                ];
            })
            ->values();
    }

    public static function count(Request $request): int
    {
        return self::items($request)->sum('cantidad');
    }

    public static function total(Request $request): float
    {
        return (float) self::items($request)->sum('subtotal');
    }

    public static function add(Request $request, int $productId, int $quantity = 1): void
    {
        $product = self::fetchProduct($request, $productId);
        if (!$product) {
            return;
        }

        $cart = collect($request->session()->get('storefront_cart', []))->keyBy('id');
        $existing = $cart->get($productId, []);
        $current = (int) ($existing['cantidad'] ?? 0);

        $cart[$productId] = [
            'id' => (int) $product['id'],
            'nombre' => (string) $product['nombre'],
            'precio' => (float) ($product['precio'] ?? 0),
            'cantidad' => max(1, min($current + $quantity, max(1, (int) ($product['stock'] ?? 1)))),
            'stock' => (int) ($product['stock'] ?? 0),
            'imagen' => $product['imagen'] ?? null,
            'categoria_id' => isset($product['categoria_id']) ? (int) $product['categoria_id'] : null,
            'categoria_nombre' => $product['categoria_nombre'] ?? null,
        ];
        $request->session()->put('storefront_cart', $cart->values()->all());
    }

    public static function update(Request $request, int $productId, int $quantity): void
    {
        $cart = $request->session()->get('storefront_cart', []);
        $updated = collect($cart)->map(function ($item) use ($productId, $quantity) {
            if ((int) ($item['id'] ?? 0) !== $productId) {
                return $item;
            }
            $item['cantidad'] = $quantity;
            return $item;
        })->reject(fn ($item) => (int) ($item['id'] ?? 0) === $productId && $quantity <= 0)->values()->all();
        $request->session()->put('storefront_cart', $updated);
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget('storefront_cart');
    }
    private static function fetchProduct(Request $request, int $productId): ?array
    {
        $response = app(BackendApiClient::class)->get('productos/' . $productId);
        if (!$response->successful()) {
            return null;
        }

        $product = $response->json();
        return is_array($product) ? $product : null;
    }
}
