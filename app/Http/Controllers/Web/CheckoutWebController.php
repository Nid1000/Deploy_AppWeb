<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BackendApiClient;
use App\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutWebController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
    ) {
    }

    public function show(Request $request): View
    {
        return view('web.checkout', $this->checkoutViewData($request));
    }

    public function store(Request $request): View|RedirectResponse
    {
        $cartItems = StorefrontCart::items($request);
        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Tu carrito esta vacio.');
        }

        $data = $request->validate([
            'fecha_entrega' => ['required', 'date_format:Y-m-d', 'after_or_equal:tomorrow'],
            'direccion_entrega' => ['required', 'string', 'min:5'],
            'distrito_entrega' => ['required', 'string', 'min:2'],
            'numero_casa_entrega' => ['required', 'string', 'min:1'],
            'telefono_contacto' => ['required', 'regex:/^9\d{8}$/'],
            'notas' => ['nullable', 'string', 'max:500'],
            'comprobante_tipo' => ['nullable', 'required_unless:metodo_pago,izipay', 'in:boleta,factura'],
            'tipo_documento' => ['nullable', 'required_unless:metodo_pago,izipay', 'in:DNI,RUC'],
            'numero_documento' => ['nullable', 'required_unless:metodo_pago,izipay', 'string'],
            'metodo_pago' => ['required', 'in:contra_entrega,tarjeta,izipay,yape'],
            'acepta_pago' => ['accepted'],
        ], [
            'fecha_entrega.after_or_equal' => 'La fecha de entrega debe ser desde manana en adelante.',
            'telefono_contacto.regex' => 'El telefono debe tener 9 digitos y empezar con 9.',
            'acepta_pago.accepted' => 'Debes aceptar las condiciones del pago.',
        ]);

        if ($data['metodo_pago'] !== 'izipay') {
            $data['numero_documento'] = $this->normalizeDocumentNumber((string) $data['numero_documento']);

            if ($data['comprobante_tipo'] === 'factura' && $data['tipo_documento'] !== 'RUC') {
                return back()->withInput()->with('error', 'Para emitir factura, el documento debe ser RUC.');
            }

            if ($data['tipo_documento'] === 'DNI' && !preg_match('/^\d{8}$/', $data['numero_documento'])) {
                return back()->withInput()->with('error', 'El DNI debe tener 8 digitos.');
            }

            if ($data['tipo_documento'] === 'RUC' && !preg_match('/^\d{11}$/', $data['numero_documento'])) {
                return back()->withInput()->with('error', 'El RUC debe tener 11 digitos.');
            }

            $documentPath = $data['tipo_documento'] === 'DNI'
                ? 'facturacion/consulta-dni'
                : 'facturacion/consulta-ruc';
            $documentResponse = $this->api->get($documentPath, ['numero' => $data['numero_documento']]);
            if (!$documentResponse->successful()) {
                return back()
                    ->withInput()
                    ->with('error', $data['tipo_documento'] === 'DNI'
                        ? 'No se pudo consultar el nombre real del DNI en este momento.'
                        : 'No se pudo consultar la razon social real del RUC en este momento.');
            }
            if ((bool) data_get($documentResponse->json(), 'validacion_real', false) !== true) {
                return back()
                    ->withInput()
                    ->with('error', $data['tipo_documento'] === 'DNI'
                        ? 'No se pudo obtener el nombre real del DNI. Configura APIPERU_TOKEN.'
                        : 'No se pudo obtener la razon social real del RUC. Configura APIPERU_TOKEN.');
            }
            $documentName = $this->documentDisplayName($documentResponse->json(), $data['tipo_documento']);
            if ($documentName === '') {
                return back()
                    ->withInput()
                    ->with('error', $data['tipo_documento'] === 'DNI'
                        ? 'No se pudo obtener el nombre del DNI consultado.'
                        : 'No se pudo obtener la razon social del RUC consultado.');
            }
        }

        $orderResponse = $this->api->post('pedidos', [
            'productos' => $cartItems->map(fn ($item) => ['id' => $item->id, 'cantidad' => $item->cantidad])->values()->all(),
            'fecha_entrega' => $data['fecha_entrega'],
            'direccion_entrega' => $data['direccion_entrega'],
            'distrito_entrega' => $data['distrito_entrega'],
            'numero_casa_entrega' => $data['numero_casa_entrega'],
            'telefono_contacto' => $data['telefono_contacto'],
            'notas' => $data['notas'] ?? null,
            'metodo_pago' => $data['metodo_pago'],
            'pago_referencia' => match ($data['metodo_pago']) {
                'izipay' => 'Pago con tarjeta Izipay pendiente',
                'yape' => 'Pago por Yape pendiente',
                default => 'Pago contra entrega',
            },
        ]);
        if ($orderResponse->failed()) {
            return back()->withInput()->with('error', $this->api->errorMessage($orderResponse, 'No se pudo crear el pedido.'));
        }

        $pedidoId = (int) data_get($orderResponse->json(), 'pedido.id', 0);

        if ($data['metodo_pago'] === 'izipay') {
            $paymentResponse = $this->api->post('pagos/izipay/crear', [
                'pedido_id' => $pedidoId,
                'metodo_pago' => 'tarjeta',
            ]);

            if ($paymentResponse->failed()) {
                return redirect()->route('web.orders.show', $pedidoId)
                    ->with('success', 'Pedido creado correctamente.')
                    ->with('error', $this->api->errorMessage($paymentResponse, 'No se pudo iniciar el pago con Izipay.'));
            }

            StorefrontCart::clear($request);

            $payment = $paymentResponse->json();
            $formToken = (string) data_get($payment, 'formToken', '');
            $publicKey = (string) data_get($payment, 'publicKey', '');
            if ($formToken === '' || $publicKey === '') {
                return redirect()->route('web.orders.show', $pedidoId)
                    ->with('success', 'Pedido creado correctamente.')
                    ->with('error', 'Izipay no devolvio los datos para mostrar el formulario de pago.');
            }

            $backendUrl = rtrim((string) config('services.backend.url'), '/');
            $defaultJsUrl = 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js';
            $jsUrl = (string) data_get($payment, 'jsUrl', $defaultJsUrl);
            if ($jsUrl === '') {
                $jsUrl = $defaultJsUrl;
            }
            $cssUrl = (string) data_get($payment, 'cssUrl', '');
            if ($cssUrl === '') {
                $cssUrl = (string) preg_replace('/\.js(\?.*)?$/', '.css$1', $jsUrl);
            }

            return view('web.checkout', $this->checkoutViewData($request, $cartItems, [
                'pedidoId' => $pedidoId,
                'orderId' => (string) data_get($payment, 'orderId', 'PEDIDO-'.$pedidoId),
                'formToken' => $formToken,
                'publicKey' => $publicKey,
                'jsUrl' => $jsUrl,
                'cssUrl' => $cssUrl,
                'successUrl' => (string) data_get($payment, 'successUrl', $backendUrl.'/api/pagos/izipay/confirmar'),
                'cancelUrl' => (string) data_get($payment, 'cancelUrl', $backendUrl.'/api/pagos/izipay/cancelado'),
                'method' => $data['metodo_pago'],
            ]));
        }

        $invoiceResponse = $this->api->post('facturacion/emitir', [
            'pedido_id' => $pedidoId,
            'comprobante_tipo' => $data['comprobante_tipo'],
            'tipo_documento' => $data['tipo_documento'],
            'numero_documento' => $data['numero_documento'],
        ]);

        StorefrontCart::clear($request);

        if ($invoiceResponse->failed()) {
            return redirect()->route('web.orders.show', $pedidoId)
                ->with('success', 'Pedido creado correctamente. El comprobante podra emitirse despues.')
                ->with('error', $this->api->errorMessage($invoiceResponse, 'No se pudo emitir el comprobante.'));
        }

        $emailSent = data_get($invoiceResponse->json(), 'correo.enviado');
        if ($emailSent === false) {
            return redirect()->route('web.orders.show', $pedidoId)
                ->with('success', 'Pedido creado y comprobante emitido correctamente.')
                ->with(
                    'error',
                    (string) data_get(
                        $invoiceResponse->json(),
                        'correo.message',
                        'El comprobante no pudo enviarse al correo registrado.'
                    )
                );
        }

        if ($emailSent !== true) {
            return redirect()->route('web.orders.show', $pedidoId)
                ->with('success', 'Pedido creado y comprobante emitido correctamente.');
        }

        return redirect()->route('web.orders.show', $pedidoId)
            ->with('success', 'Pedido creado. El comprobante fue enviado a tu correo registrado.');
    }

    public function validateDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo_documento' => ['required', 'in:DNI,RUC'],
            'numero_documento' => ['required', 'string'],
        ]);

        $number = $this->normalizeDocumentNumber($data['numero_documento']);
        if ($data['tipo_documento'] === 'DNI' && !preg_match('/^\d{8}$/', $number)) {
            return response()->json([
                'ok' => false,
                'message' => 'El DNI debe tener exactamente 8 digitos.',
            ], 422);
        }

        if ($data['tipo_documento'] === 'RUC' && !$this->isValidRuc($number)) {
            return response()->json([
                'ok' => false,
                'message' => 'El RUC debe tener 11 digitos y un digito verificador correcto.',
            ], 422);
        }

        $path = $data['tipo_documento'] === 'DNI'
            ? 'facturacion/consulta-dni'
            : 'facturacion/consulta-ruc';
        $response = $this->api->get($path, ['numero' => $number]);

        if (!$response->successful()) {
            $backendMessage = (string) data_get($response->json(), 'message', '');
            return response()->json([
                'ok' => false,
                'message' => $backendMessage !== ''
                    ? $backendMessage
                    : ($data['tipo_documento'] === 'DNI'
                        ? 'No se pudo validar DNI con APIPERU.'
                        : 'No se pudo validar RUC con APIPERU.'),
                'validation_unavailable' => true,
                'numero' => $number,
                'validacion_real' => false,
            ], 503);
        }

        $payload = $response->json();
        $documentData = data_get($payload, 'data', []);
        $name = $this->documentDisplayName($payload, $data['tipo_documento']);
        $realValidation = (bool) data_get($payload, 'validacion_real', false);
        $providerMessage = (string) data_get($payload, 'message', '');
        if (!$realValidation) {
            return response()->json([
                'ok' => false,
                'message' => $data['tipo_documento'] === 'DNI'
                    ? 'La validacion en linea del DNI no devolvio datos reales.'
                    : 'La validacion en linea del RUC no devolvio datos reales.',
                'numero' => $number,
                'validacion_real' => false,
                'validation_unavailable' => true,
                'data' => $documentData,
            ], 503);
        }
        if ($name === '') {
            return response()->json([
                'ok' => false,
                'message' => $data['tipo_documento'] === 'DNI'
                    ? 'No se pudo obtener el nombre del DNI consultado.'
                    : 'No se pudo obtener la razon social del RUC consultado.',
                'numero' => $number,
                'validacion_real' => true,
                'data' => $documentData,
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'message' => $name !== ''
                ? ($data['tipo_documento'] === 'DNI' ? "DNI validado: {$name}" : "RUC validado: {$name}")
                : ($providerMessage ?: ($data['tipo_documento'] === 'DNI' ? 'DNI validado correctamente.' : 'RUC validado correctamente.')),
            'numero' => $number,
            'validacion_real' => $realValidation,
            'data' => $documentData,
        ]);
    }

    private function mapDistricts(mixed $districts): \Illuminate\Support\Collection
    {
        return collect($districts)->map(fn ($district) => is_array($district) ? (object) $district : $district)->values();
    }

    private function checkoutViewData(Request $request, mixed $cartItems = null, ?array $izipayPayment = null): array
    {
        $cartItems ??= StorefrontCart::items($request);
        $districtsResponse = $this->api->get('usuarios/distritos-huancayo');

        return [
            'cartItems' => $cartItems,
            'cartTotal' => $cartItems->sum('subtotal'),
            'distritos' => $this->mapDistricts($this->api->okData($districtsResponse, 'distritos', [])),
            'user' => $request->session()->get('web_user'),
            'minDeliveryDate' => now()->addDay()->toDateString(),
            'yapeQrUrl' => env('YAPE_QR_URL', asset('images/payments/yape-qr.png')),
            'yapePhone' => env('YAPE_PHONE', '974268690'),
            'izipayPayment' => $izipayPayment,
        ];
    }

    private function requiresRealDocumentValidation(): bool
    {
        return filter_var(env('DOCUMENT_VALIDATION_REQUIRED', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function documentDisplayName(array $payload, string $type): string
    {
        $data = data_get($payload, 'data', []);
        if (!is_array($data)) {
            return '';
        }

        if ($type === 'DNI') {
            $fullName = trim((string) ($data['nombre_completo'] ?? ''));
            if ($fullName !== '') {
                return $fullName;
            }

            return trim((string) (
                ($data['first_name'] ?? $data['nombres'] ?? '') . ' '
                . ($data['first_last_name'] ?? $data['apellido_paterno'] ?? '') . ' '
                . ($data['second_last_name'] ?? $data['apellido_materno'] ?? '')
            ));
        }
        if ($type === 'RUC') {
            return $this->rucDisplayName($data);
        }

        return trim((string) (
            $data['razon_social']
            ?? $data['nombre_o_razon_social']
            ?? $data['nombre_comercial']
            ?? ''
        ));
    }

    private function rucDisplayName(array $data): string
    {
        return trim((string) (
            $data['razon_social']
            ?? $data['nombre_o_razon_social']
            ?? $data['nombre_comercial']
            ?? $data['nombre']
            ?? ''
        ));
    }

    private function normalizeDocumentNumber(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?: '';
    }

    private function isValidRuc(string $number): bool
    {
        if (!preg_match('/^\d{11}$/', $number) || !in_array(substr($number, 0, 2), ['10', '15', '17', '20'], true)) {
            return false;
        }

        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += ((int) $number[$index]) * $weight;
        }

        $check = 11 - ($sum % 11);
        if ($check === 10) {
            $check = 0;
        }
        if ($check === 11) {
            $check = 1;
        }

        return $check === (int) $number[10];
    }
}
