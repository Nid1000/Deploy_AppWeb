<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApisPeruClient;
use App\Services\BackendApiClient;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $api,
        private readonly ApisPeruClient $apisPeru,
    )
    {
    }

    public function store(Request $request)
    {
        $payload = $request->all();

        // Si está configurado APISPERU_ENABLED usaremos su API para emitir comprobantes
        if ((bool) env('APISPERU_ENABLED', false)) {
            $response = $this->apisPeru->sendInvoice($payload);

            if ($response->successful()) {
                return $this->ticketResponse($request, $this->receiptResponse($payload, $response->json()));
            }

            $message = $this->api->errorMessage($response, 'No se pudo crear el comprobante en APISPERU.');

            return response()->json(['error' => $message], $response->status() ?: 500);
        }

        // Por defecto reenviamos al backend local configurado en services.backend.url
        $backendPath = trim((string) env('BACKEND_TICKETS_PATH', 'tickets'), '/');
        $response = $this->api->post($backendPath, $payload);

        if ($response->successful()) {
            return $this->ticketResponse($request, $this->receiptResponse($payload, $this->api->okData($response)));
        }

        $message = $this->api->errorMessage($response, 'No se pudo crear el boleto en la plataforma externa.');

        return response()->json(['error' => $message], $response->status() ?: 500);
    }

    private function ticketResponse(Request $request, array $payload)
    {
        if ($request->query('format') === 'html') {
            return response($payload['boleta_html'], 201)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return response()->json($payload, 201);
    }

    private function receiptResponse(array $requestPayload, mixed $apiPayload): array
    {
        $payload = is_array($apiPayload) ? $apiPayload : [];
        $receipt = data_get($payload, 'comprobante');
        $originalReceipt = is_array($receipt) ? $receipt : null;

        if (!is_array($receipt)) {
            $receipt = data_get($payload, 'data.comprobante');
        }

        if (!is_array($receipt)) {
            $receipt = $payload;
        }

        $response = $payload;
        $response['message'] = data_get($payload, 'message', 'Comprobante emitido correctamente.');
        $response['comprobante_original'] = $originalReceipt;
        $response['comprobante'] = $this->normalizeReceipt($requestPayload, $receipt, $payload);
        $response['correo'] = $this->normalizeEmailStatus($requestPayload, $payload);
        $response['boleta'] = $this->boletaFormat($requestPayload, $response['comprobante'], $payload);
        $response['boleta_html'] = $this->boletaHtml($response['boleta']);
        $response['apisperu'] = $this->apisPeruInvoiceFormat($requestPayload, $response['boleta'], $response['comprobante']);
        $response['data'] = $payload;

        return $response;
    }

    private function normalizeReceipt(array $requestPayload, array $receipt, array $fullPayload): array
    {
        $serie = (string) (
            data_get($receipt, 'serie')
            ?: data_get($fullPayload, 'serie')
            ?: data_get($requestPayload, 'serie')
            ?: ''
        );
        $numero = (string) (
            data_get($receipt, 'numero')
            ?: data_get($receipt, 'correlativo')
            ?: data_get($fullPayload, 'numero')
            ?: data_get($fullPayload, 'correlativo')
            ?: ''
        );
        $numeroFormateado = (string) (
            data_get($receipt, 'numero_formateado')
            ?: data_get($receipt, 'numeroFormateado')
            ?: data_get($fullPayload, 'numero_formateado')
            ?: data_get($fullPayload, 'numeroFormateado')
            ?: trim($serie . ($serie !== '' && $numero !== '' ? '-' : '') . $numero)
        );

        return [
            'pedido_id' => data_get($receipt, 'pedido_id')
                ?? data_get($receipt, 'pedidoId')
                ?? data_get($requestPayload, 'pedido_id'),
            'tipo' => strtolower((string) (
                data_get($receipt, 'tipo')
                ?: data_get($requestPayload, 'comprobante_tipo')
                ?: data_get($requestPayload, 'tipo')
                ?: 'boleta'
            )),
            'serie' => $serie,
            'numero' => $numero,
            'numero_formateado' => $numeroFormateado,
            'total' => (float) (
                data_get($receipt, 'total')
                ?? data_get($fullPayload, 'total')
                ?? data_get($requestPayload, 'total')
                ?? data_get($requestPayload, 'mtoImpVenta')
                ?? 0
            ),
            'cliente' => $this->normalizeCustomer($requestPayload, $receipt),
            'archivos' => [
                'pdf' => data_get($receipt, 'archivos.pdf')
                    ?? data_get($receipt, 'pdf_url')
                    ?? data_get($receipt, 'pdf')
                    ?? data_get($fullPayload, 'links.pdf')
                    ?? data_get($fullPayload, 'pdf'),
                'xml' => data_get($receipt, 'archivos.xml')
                    ?? data_get($receipt, 'xml_url')
                    ?? data_get($receipt, 'xml')
                    ?? data_get($fullPayload, 'links.xml')
                    ?? data_get($fullPayload, 'xml'),
                'img' => data_get($receipt, 'archivos.img')
                    ?? data_get($receipt, 'img_url')
                    ?? data_get($receipt, 'imagen')
                    ?? data_get($receipt, 'cdr')
                    ?? data_get($fullPayload, 'links.img')
                    ?? data_get($fullPayload, 'imagen'),
            ],
            'created_at' => data_get($receipt, 'created_at')
                ?? data_get($receipt, 'fecha_emision')
                ?? data_get($fullPayload, 'fecha_emision')
                ?? now()->toDateTimeString(),
        ];
    }

    private function normalizeCustomer(array $requestPayload, array $receipt): array
    {
        return [
            'nombre' => data_get($receipt, 'cliente.nombre')
                ?? data_get($requestPayload, 'cliente.nombre')
                ?? data_get($requestPayload, 'client.rznSocial')
                ?? data_get($requestPayload, 'cliente')
                ?? 'Cliente',
            'tipo_documento' => data_get($receipt, 'cliente.tipo_documento')
                ?? data_get($requestPayload, 'tipo_documento')
                ?? data_get($requestPayload, 'client.tipoDoc'),
            'numero_documento' => data_get($receipt, 'cliente.numero_documento')
                ?? data_get($requestPayload, 'numero_documento')
                ?? data_get($requestPayload, 'client.numDoc'),
        ];
    }

    private function normalizeEmailStatus(array $requestPayload, array $fullPayload): array
    {
        $email = data_get($fullPayload, 'correo');
        if (is_array($email)) {
            return $email;
        }

        return [
            'enviado' => data_get($fullPayload, 'correo_enviado')
                ?? data_get($fullPayload, 'email_sent')
                ?? data_get($requestPayload, 'correo.enviado')
                ?? data_get($requestPayload, 'enviar_correo'),
            'message' => data_get($fullPayload, 'correo_message')
                ?? data_get($fullPayload, 'email_message')
                ?? data_get($requestPayload, 'correo.message'),
            'destinatario' => data_get($fullPayload, 'correo_destinatario')
                ?? data_get($fullPayload, 'email')
                ?? data_get($requestPayload, 'correo.destinatario')
                ?? data_get($requestPayload, 'email')
                ?? data_get($requestPayload, 'cliente.email'),
        ];
    }

    private function boletaFormat(array $requestPayload, array $receipt, array $fullPayload): array
    {
        $serie = (string) (data_get($receipt, 'serie') ?: data_get($requestPayload, 'serie') ?: 'B001');
        $numero = $this->receiptNumber($receipt, $requestPayload);
        $total = (float) (data_get($receipt, 'total') ?: data_get($requestPayload, 'total') ?: data_get($requestPayload, 'mtoImpVenta') ?: 0);
        $documentType = (string) (
            data_get($receipt, 'cliente.tipo_documento')
            ?: data_get($requestPayload, 'tipo_documento')
            ?: data_get($requestPayload, 'client.tipoDoc')
            ?: 'DNI'
        );
        $documentNumber = (string) (
            data_get($receipt, 'cliente.numero_documento')
            ?: data_get($requestPayload, 'numero_documento')
            ?: data_get($requestPayload, 'client.numDoc')
            ?: ''
        );
        $customer = (string) (
            data_get($receipt, 'cliente.nombre')
            ?: data_get($requestPayload, 'cliente.nombre')
            ?: data_get($requestPayload, 'client.rznSocial')
            ?: data_get($requestPayload, 'cliente')
            ?: 'Cliente'
        );
        $reniecVerified = data_get($fullPayload, 'validacion_real')
            ?? data_get($fullPayload, 'reniec.verificado')
            ?? data_get($requestPayload, 'validacion_real');

        return [
            'titulo' => 'Comprobante electronico',
            'tipo' => strtoupper((string) (data_get($receipt, 'tipo') ?: 'boleta')),
            'serie' => $serie,
            'numero' => $numero,
            'correlativo' => $serie . '-' . $numero,
            'documento' => strtoupper($documentType) . ' ' . $documentNumber,
            'documento_tipo' => strtoupper($documentType),
            'documento_numero' => $documentNumber,
            'cliente' => strtoupper($customer),
            'verificado_reniec' => $this->yesNo($reniecVerified),
            'fecha_emision' => (string) (
                data_get($receipt, 'created_at')
                ?: data_get($fullPayload, 'fecha_emision')
                ?: data_get($requestPayload, 'fechaEmision')
                ?: now()->toDateTimeString()
            ),
            'total' => $total,
            'total_formateado' => 'S/ ' . number_format($total, 2),
        ];
    }

    private function apisPeruInvoiceFormat(array $requestPayload, array $boleta, array $receipt): array
    {
        $total = (float) $boleta['total'];
        $igv = round($total - ($total / 1.18), 2);
        $gravada = round($total - $igv, 2);

        return [
            'ublVersion' => '2.1',
            'tipoOperacion' => '0101',
            'tipoDoc' => $boleta['tipo'] === 'FACTURA' ? '01' : '03',
            'serie' => $boleta['serie'],
            'correlativo' => $boleta['numero'],
            'fechaEmision' => $boleta['fecha_emision'],
            'formaPago' => [
                'moneda' => 'PEN',
                'tipo' => 'Contado',
            ],
            'tipoMoneda' => 'PEN',
            'client' => [
                'tipoDoc' => $boleta['documento_tipo'] === 'RUC' ? '6' : '1',
                'numDoc' => $boleta['documento_numero'],
                'rznSocial' => $boleta['cliente'],
            ],
            'mtoOperGravadas' => $gravada,
            'mtoIGV' => $igv,
            'valorVenta' => $gravada,
            'totalImpuestos' => $igv,
            'subTotal' => $total,
            'mtoImpVenta' => $total,
            'details' => data_get($requestPayload, 'details')
                ?: data_get($requestPayload, 'items')
                ?: data_get($receipt, 'items')
                ?: [],
        ];
    }

    private function boletaHtml(array $boleta): string
    {
        $escape = fn ($value) => e((string) $value);

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Boleta</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:40px;color:#000;font-size:18px}'
            . 'h1{font-size:28px;margin:0 0 10px}.line{border-top:2px solid #aaa;margin:14px 0 10px}'
            . 'p{margin:4px 0}strong{font-weight:700}</style></head><body>'
            . '<h1>Comprobante electronico</h1>'
            . '<p>Tipo: <strong>' . $escape($boleta['tipo']) . '</strong></p>'
            . '<p>Serie: <strong>' . $escape($boleta['serie']) . '</strong></p>'
            . '<p>Numero: <strong>' . $escape($boleta['numero']) . '</strong></p>'
            . '<p>Correlativo: <strong>' . $escape($boleta['correlativo']) . '</strong></p>'
            . '<div class="line"></div>'
            . '<p>Documento: <strong>' . $escape($boleta['documento']) . '</strong></p>'
            . '<p>Cliente: <strong>' . $escape($boleta['cliente']) . '</strong></p>'
            . '<p>Verificado en RENIEC: <strong>' . $escape($boleta['verificado_reniec']) . '</strong></p>'
            . '<div class="line"></div>'
            . '<p>Fecha de emision: <strong>' . $escape($boleta['fecha_emision']) . '</strong></p>'
            . '<p>Total: <strong>' . $escape($boleta['total_formateado']) . '</strong></p>'
            . '</body></html>';
    }

    private function receiptNumber(array $receipt, array $requestPayload): string
    {
        $number = (string) (
                data_get($receipt, 'numero')
            ?: data_get($requestPayload, 'numero')
            ?: data_get($requestPayload, 'correlativo')
            ?: '1'
        );

        if (str_contains($number, '-')) {
            $number = (string) str($number)->afterLast('-');
        }

        return str_pad(preg_replace('/\D+/', '', $number) ?: $number, 8, '0', STR_PAD_LEFT);
    }

    private function yesNo(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'No';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Si' : 'No';
    }
}
