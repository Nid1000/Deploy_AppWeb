<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use App\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrdersWebController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'receipts' ? 'receipts' : 'orders';
        $filters = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'estado' => trim((string) $request->query('estado', '')),
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
        ];

        $ordersResponse = $this->api->get('pedidos/mis-pedidos', ['pagina' => 1, 'limite' => 100]);
        $receiptsResponse = $this->api->get('facturacion/mis-comprobantes');
        $orders = collect($this->api->okData($ordersResponse, 'pedidos', []))->map(function ($order) {
            $order = (object) $order;
            $order->total = (float) ($order->total ?? 0);
            $order->lineas = collect();
            $order->total_productos = (int) ($order->total_productos ?? 0);
            return $order;
        });
        $receipts = collect($this->api->okData($receiptsResponse, 'comprobantes', []))->map(function ($receipt) {
            $receipt = (object) $receipt;
            $receipt->pdf_url = !empty($receipt->archivos['pdf'] ?? null) ? rtrim((string) config('services.backend.url'), '/') . $receipt->archivos['pdf'] : null;
            $receipt->xml_url = !empty($receipt->archivos['xml'] ?? null) ? rtrim((string) config('services.backend.url'), '/') . $receipt->archivos['xml'] : null;
            $receipt->img_url = !empty($receipt->archivos['img'] ?? null) ? rtrim((string) config('services.backend.url'), '/') . $receipt->archivos['img'] : null;
            return $receipt;
        });

        $orders = $orders->filter(function ($order) use ($filters) {
            $needle = mb_strtolower($filters['buscar']);
            if ($needle !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($order->id ?? ''),
                    (string) ($order->estado ?? ''),
                ]));
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            if ($filters['estado'] !== '' && mb_strtolower((string) ($order->estado ?? '')) !== mb_strtolower($filters['estado'])) {
                return false;
            }

            $createdAt = !empty($order->created_at) ? Carbon::parse($order->created_at) : null;
            if ($createdAt && $filters['desde'] !== '' && $createdAt->lt(Carbon::parse($filters['desde'])->startOfDay())) {
                return false;
            }
            if ($createdAt && $filters['hasta'] !== '' && $createdAt->gt(Carbon::parse($filters['hasta'])->endOfDay())) {
                return false;
            }

            return true;
        })->values();

        $receipts = $receipts->filter(function ($receipt) use ($filters) {
            $needle = mb_strtolower($filters['buscar']);
            if ($needle !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($receipt->pedido_id ?? ''),
                    (string) ($receipt->numero_formateado ?? ''),
                    (string) ($receipt->tipo ?? ''),
                ]));
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            $issuedAt = null;
            if (!empty($receipt->created_at)) {
                $issuedAt = Carbon::parse($receipt->created_at);
            } elseif (!empty($receipt->fecha_emision)) {
                $issuedAt = Carbon::parse($receipt->fecha_emision);
            }

            if ($issuedAt && $filters['desde'] !== '' && $issuedAt->lt(Carbon::parse($filters['desde'])->startOfDay())) {
                return false;
            }
            if ($issuedAt && $filters['hasta'] !== '' && $issuedAt->gt(Carbon::parse($filters['hasta'])->endOfDay())) {
                return false;
            }

            return true;
        })->values();

        return view('web.orders.index', [
            'orders' => $orders,
            'receipts' => $receipts,
            'activeTab' => $tab,
            'filters' => $filters,
            'orderStates' => $orders
                ->pluck('estado')
                ->filter(fn ($state) => filled($state))
                ->map(fn ($state) => (string) $state)
                ->unique()
                ->sort()
                ->values(),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $response = $this->api->get('pedidos/' . $id);
        abort_unless($response->successful(), 404);
        $order = (object) $this->api->okData($response, 'pedido', []);
        $details = collect($this->api->okData($response, 'detalles', []))->map(function ($detail) {
            $detail = (object) $detail;
            $detail->precio_unitario = (float) ($detail->precio_unitario ?? 0);
            $detail->subtotal = (float) ($detail->subtotal ?? 0);
            $detail->producto_imagen_url = StorefrontCart::imageUrl($detail->producto_imagen ?? null);
            return $detail;
        });
        $receiptsResponse = $this->api->get('facturacion/mis-comprobantes');
        $receipt = collect($this->api->okData($receiptsResponse, 'comprobantes', []))
            ->first(fn ($item) => (string) ($item['numero_formateado'] ?? '') === (string) ($order->comprobante_numero ?? ''));
        $receipt = is_array($receipt) ? (object) $receipt : null;
        if ($receipt && isset($receipt->archivos)) {
            $receipt->pdf_url = !empty($receipt->archivos['pdf'] ?? null) ? $this->api->publicUrl($receipt->archivos['pdf']) : null;
            $receipt->xml_url = !empty($receipt->archivos['xml'] ?? null) ? $this->api->publicUrl($receipt->archivos['xml']) : null;
            $receipt->img_url = !empty($receipt->archivos['img'] ?? null) ? $this->api->publicUrl($receipt->archivos['img']) : null;
        }

        return view('web.orders.show', [
            'order' => $order,
            'details' => $details,
            'receipt' => $receipt,
        ]);
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $response = $this->api->put('pedidos/' . $id . '/cancelar');
        if (!$response->successful()) {
            return back()->with('error', $this->api->errorMessage($response, 'No se pudo cancelar el pedido.'));
        }

        return back()->with('success', 'Pedido cancelado correctamente.');
    }
}
