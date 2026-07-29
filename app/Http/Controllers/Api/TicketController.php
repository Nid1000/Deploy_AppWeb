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
            'items' => $this->ticketItems($requestPayload, $receipt, $total),
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

    private function ticketCompany(): array
    {
        return [
            'nombre' => env('EMPRESA_RAZON_SOCIAL', 'DELICIAS EIRLTDA'),
            'nombre_comercial' => env('EMPRESA_NOMBRE_COMERCIAL', 'Delicias'),
            'ruc' => env('EMPRESA_RUC', '20215106536'),
            'direccion' => env('EMPRESA_DIRECCION', 'JR. PARRA DEL RIEGO'),
            'telefono' => env('YAPE_PHONE', '993560096'),
        ];
    }

    private function ticketLogoDataUri(): string
    {
        $path = public_path('images/logos/logo 1.png');
        if (!is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }

    private function ticketItems(array $requestPayload, array $receipt, float $total): array
    {
        $items = data_get($requestPayload, 'details')
            ?: data_get($requestPayload, 'items')
            ?: data_get($receipt, 'items')
            ?: [];

        $items = collect(is_array($items) ? $items : [])->map(function ($item, int $index) {
            $cantidad = (float) (
                data_get($item, 'cantidad')
                ?? data_get($item, 'qty')
                ?? data_get($item, 'quantity')
                ?? 1
            );
            $descripcion = (string) (
                data_get($item, 'descripcion')
                ?? data_get($item, 'description')
                ?? data_get($item, 'producto_nombre')
                ?? data_get($item, 'nombre')
                ?? 'VENTA'
            );
            $precio = (float) (
                data_get($item, 'mtoPrecioUnitario')
                ?? data_get($item, 'precio_unitario')
                ?? data_get($item, 'precio')
                ?? data_get($item, 'unit_price')
                ?? 0
            );
            $subtotal = (float) (
                data_get($item, 'subtotal')
                ?? data_get($item, 'mtoValorVenta')
                ?? ($precio * max(1, $cantidad))
            );

            return [
                'codigo' => (string) (
                    data_get($item, 'codigo')
                    ?? data_get($item, 'codProducto')
                    ?? data_get($item, 'producto_id')
                    ?? 'P' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)
                ),
                'descripcion' => $descripcion,
                'cantidad' => max(1, $cantidad),
                'precio' => $precio > 0 ? $precio : $subtotal,
                'subtotal' => $subtotal,
            ];
        })->values()->all();

        if ($items !== []) {
            return $items;
        }

        return [[
            'codigo' => 'VENTA',
            'descripcion' => 'VENTA',
            'cantidad' => 1,
            'precio' => $total,
            'subtotal' => $total,
        ]];
    }

    private function boletaHtml(array $boleta): string
    {
        $escape = fn ($value) => e((string) $value);
        $company = $this->ticketCompany();
        $logo = $this->ticketLogoDataUri();
        $logoHtml = $logo !== ''
            ? '<img src="' . $logo . '" alt="Logo" style="width:70px;height:70px;object-fit:contain">'
            : '<strong>' . $escape($company['nombre_comercial']) . '</strong>';
        $opGravada = round(((float) $boleta['total']) / 1.18, 2);
        $igv = round(((float) $boleta['total']) - $opGravada, 2);

        $rows = '';
        foreach ($boleta['items'] as $item) {
            $rows .= '<tr>'
                . '<td>' . $escape($item['codigo']) . '</td>'
                . '<td>' . $escape(mb_strtoupper((string) $item['descripcion'])) . '<br><span>' . number_format((float) $item['cantidad'], 0) . ' UND x S/ ' . number_format((float) $item['precio'], 2) . '</span></td>'
                . '<td class="right">S/ ' . number_format((float) $item['subtotal'], 2) . '</td>'
                . '</tr>';
        }

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Boleta</title>'
            . '<style>@page{margin:10px;size:80mm auto}body{font-family:"Courier New",monospace;color:#1c1917;font-size:11px;margin:0}'
            . '.ticket{width:280px;margin:0 auto;padding:10px}.center{text-align:center}.line{border-top:1px dashed #444;margin:8px 0}'
            . 'h1,h2,p{margin:0}h1{font-size:14px;text-transform:uppercase}h2{font-size:12px;text-transform:uppercase;margin-top:4px}'
            . '.small{font-size:10px;line-height:1.35}.row{display:table;width:100%}.label,.value{display:table-cell}.value{text-align:right}'
            . 'table{width:100%;border-collapse:collapse}th{border-bottom:1px dashed #444;border-top:1px dashed #444;text-align:left;padding:4px 0;font-size:9px}'
            . 'td{padding:4px 0;vertical-align:top}td span{font-size:9px;color:#57534e}.right{text-align:right;white-space:nowrap}.total{font-size:13px;font-weight:700}'
            . '.footer{text-align:center;font-size:9px;line-height:1.35;margin-top:10px}</style></head><body><div class="ticket">'
            . '<div class="center">' . $logoHtml . '</div>'
            . '<div class="center"><h1>' . $escape($company['nombre']) . '</h1><p class="small">RUC ' . $escape($company['ruc']) . '</p>'
            . '<p class="small">' . $escape($company['direccion']) . '</p><p class="small">TEL: ' . $escape($company['telefono']) . '</p></div>'
            . '<div class="line"></div><div class="center"><h2>' . $escape($boleta['tipo']) . ' ELECTRONICA</h2><h2>NRO. ' . $escape($boleta['correlativo']) . '</h2></div>'
            . '<div class="line"></div><p class="small">FECHA: ' . $escape($boleta['fecha_emision']) . '</p>'
            . '<p class="small">DOCUMENTO: ' . $escape($boleta['documento']) . '</p><p class="small">CLIENTE: ' . $escape($boleta['cliente']) . '</p>'
            . '<div class="line"></div><table><thead><tr><th>CODIGO</th><th>DESCRIPCION</th><th class="right">MONTO</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<div class="line"></div><div class="row small"><span class="label">OP. GRAVADA</span><span class="value">S/ ' . number_format($opGravada, 2) . '</span></div>'
            . '<div class="row small"><span class="label">IGV 18%</span><span class="value">S/ ' . number_format($igv, 2) . '</span></div>'
            . '<div class="row total"><span class="label">TOTAL</span><span class="value">' . $escape($boleta['total_formateado']) . '</span></div>'
            . '<div class="line"></div><div class="footer">Gracias por su compra<br>Representacion impresa generada por Delicias<br>Formato tipo APISPERU/SUNAT</div>'
            . '</div></body></html>';
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
