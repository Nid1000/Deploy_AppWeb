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
                return response()->json($this->receiptResponse($payload, $response->json()), 201);
            }

            $message = $this->api->errorMessage($response, 'No se pudo crear el comprobante en APISPERU.');

            return response()->json(['error' => $message], $response->status() ?: 500);
        }

        // Por defecto reenviamos al backend local configurado en services.backend.url
        $backendPath = trim((string) env('BACKEND_TICKETS_PATH', 'tickets'), '/');
        $response = $this->api->post($backendPath, $payload);

        if ($response->successful()) {
            return response()->json($this->receiptResponse($payload, $this->api->okData($response)), 201);
        }

        $message = $this->api->errorMessage($response, 'No se pudo crear el boleto en la plataforma externa.');

        return response()->json(['error' => $message], $response->status() ?: 500);
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
}
