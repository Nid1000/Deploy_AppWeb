<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FacturacionController extends Controller
{
    private function toFloat($n): float
    {
        return is_numeric($n) ? (float) $n : (float) (string) $n;
    }

    private function comprobantesDir(): string
    {
        $dir = public_path('uploads/comprobantes');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function placeholderImgUrl(): string
    {
        return '/uploads/comprobantes/placeholder.svg';
    }

    private function escXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function comprobanteImageUrl(string $fileBase): string
    {
        $abs = public_path('uploads/comprobantes/' . $fileBase . '.svg');
        if (is_file($abs)) {
            return '/uploads/comprobantes/' . $fileBase . '.svg';
        }
        return $this->placeholderImgUrl();
    }

    private function decolectaBaseUrl(): string
    {
        return env('DECOLECTA_BASE_URL', 'https://api.decolecta.com/v1');
    }

    private function decolectaVerifyOption()
    {
        $envBundle = trim((string) env('CURL_CA_BUNDLE', ''));
        if ($envBundle !== '' && is_file($envBundle)) {
            return $envBundle;
        }

        $bundle = base_path('..' . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'cacert.pem');
        if (is_file($bundle)) {
            return $bundle;
        }

        return true; // usa el store del sistema
    }

    private function fetchReniecDni(string $dni, ?string $token): ?array
    {
        if (!$token) {
            return null;
        }
        try {
            $resp = Http::withToken($token)
                ->withOptions(['verify' => $this->decolectaVerifyOption()])
                ->timeout(15)
                ->connectTimeout(10)
                ->get($this->decolectaBaseUrl() . '/reniec/dni', [
                    'numero' => $dni,
                ]);
            if (!$resp->ok()) {
                return null;
            }
            return $resp->json();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchSunatRuc(string $ruc, ?string $token): ?array
    {
        if (!$token) {
            return null;
        }
        try {
            $resp = Http::withToken($token)
                ->withOptions(['verify' => $this->decolectaVerifyOption()])
                ->timeout(15)
                ->connectTimeout(10)
                ->get($this->decolectaBaseUrl() . '/sunat/ruc/full', [
                    'numero' => $ruc,
                ]);
            if (!$resp->ok()) {
                return null;
            }
            return $resp->json();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function nextNumeroComprobante(string $tipo, string $serie): array
    {
        return DB::transaction(function () use ($tipo, $serie) {
            $row = DB::table('comprobante_series')
                ->where('tipo', $tipo)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                DB::table('comprobante_series')->insert([
                    'tipo' => $tipo,
                    'serie' => $serie,
                    'correlativo' => 0,
                ]);
                $row = DB::table('comprobante_series')
                    ->where('tipo', $tipo)
                    ->where('serie', $serie)
                    ->lockForUpdate()
                    ->first();
            }

            DB::table('comprobante_series')
                ->where('tipo', $tipo)
                ->where('serie', $serie)
                ->increment('correlativo', 1);

            $curr = (int) DB::table('comprobante_series')
                ->where('tipo', $tipo)
                ->where('serie', $serie)
                ->value('correlativo');

            return [
                'numero' => $curr,
                'numeroFormateado' => $serie . '-' . str_pad((string) $curr, 8, '0', STR_PAD_LEFT),
            ];
        });
    }

    public function consultaDni(Request $request)
    {
        $numero = (string) $request->query('numero', '');
        if (strlen($numero) !== 8) {
            return response()->json(['statusCode' => 400, 'message' => 'El DNI debe tener 8 dígitos'], 400);
        }
        $token = $request->header('X-Decolecta-Token') ?: env('DECOLECTA_TOKEN');
        $data = $this->fetchReniecDni($numero, $token);
        if (!$data) {
            return response()->json(['statusCode' => 404, 'message' => 'No se encontró información del DNI en RENIEC'], 404);
        }
        return response()->json(['statusCode' => 200, 'dni' => $numero, 'data' => $data], 200);
    }

    public function consultaRuc(Request $request)
    {
        $numero = (string) $request->query('numero', '');
        if (strlen($numero) !== 11) {
            return response()->json(['statusCode' => 400, 'message' => 'El RUC debe tener 11 dígitos'], 400);
        }
        $token = $request->header('X-Decolecta-Token') ?: env('DECOLECTA_TOKEN');
        $data = $this->fetchSunatRuc($numero, $token);
        if (!$data) {
            return response()->json(['statusCode' => 404, 'message' => 'No se encontró información del RUC en SUNAT'], 404);
        }
        return response()->json(['statusCode' => 200, 'ruc' => $numero, 'data' => $data], 200);
    }

    public function emitir(Request $request)
    {
        $payload = $request->attributes->get('user');
        $usuarioId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        try {
            $data = $request->validate([
                'pedido_id' => ['required', 'integer', 'min:1'],
                'comprobante_tipo' => ['required', 'in:boleta,factura'],
                'tipo_documento' => ['required', 'in:DNI,RUC'],
                'numero_documento' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos inválidos',
                'message' => 'Validación fallida',
                'details' => $e->errors(),
            ], 400);
        }

        $pedido = DB::table('pedidos')
            ->where('id', (int) $data['pedido_id'])
            ->where('usuario_id', $usuarioId)
            ->first();
        if (!$pedido) {
            return response()->json([
                'statusCode' => 404,
                'error' => 'Pedido no encontrado',
                'message' => 'No se encontró el pedido para emitir comprobante',
            ], 404);
        }

        if ($data['comprobante_tipo'] === 'factura' && $data['tipo_documento'] !== 'RUC') {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Documento inválido',
                'message' => 'Para FACTURA, el documento debe ser RUC',
            ], 400);
        }
        if ($data['tipo_documento'] === 'DNI' && strlen($data['numero_documento']) !== 8) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Documento inválido',
                'message' => 'El DNI debe tener 8 dígitos',
            ], 400);
        }
        if ($data['tipo_documento'] === 'RUC' && strlen($data['numero_documento']) !== 11) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Documento inválido',
                'message' => 'El RUC debe tener 11 dígitos',
            ], 400);
        }

        $exist = DB::table('comprobantes')
            ->where('pedido_id', (int) $pedido->id)
            ->where('tipo', (string) $data['comprobante_tipo'])
            ->first();

        if ($exist) {
            $fileBase = "pedido-{$pedido->id}-{$exist->serie}-" . str_pad((string) $exist->numero, 8, '0', STR_PAD_LEFT);
            return response()->json([
                'statusCode' => 200,
                'message' => 'El comprobante ya fue emitido previamente',
                'comprobante' => [
                    'id' => (int) $exist->id,
                    'tipo' => $exist->tipo,
                    'serie' => $exist->serie,
                    'numero' => (int) $exist->numero,
                    'numero_formateado' => $exist->numero_formateado,
                    'estado' => 'emitido',
                    'pedido_id' => (int) $pedido->id,
                    'total' => $this->toFloat($pedido->total),
                    'created_at' => $exist->created_at,
                ],
                'archivos' => [
                    'pdf' => '/uploads/' . str_replace('\\', '/', (string) $exist->archivo_ruta),
                    'xml' => "/uploads/comprobantes/{$fileBase}.xml",
                    'img' => $this->comprobanteImageUrl($fileBase),
                ],
            ], 200);
        }

        $serie = $data['comprobante_tipo'] === 'boleta' ? 'B001' : 'F001';
        $corr = $this->nextNumeroComprobante((string) $data['comprobante_tipo'], $serie);
        $numero = (int) $corr['numero'];
        $numeroStr = str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
        $numeroFormateado = (string) $corr['numeroFormateado'];

        $fileBase = "pedido-{$pedido->id}-{$serie}-{$numeroStr}";
        $dir = $this->comprobantesDir();
        $pdfRel = 'comprobantes/' . $fileBase . '.pdf';
        $xmlRel = 'comprobantes/' . $fileBase . '.xml';
        $svgRel = 'comprobantes/' . $fileBase . '.svg';
        $pdfAbs = $dir . DIRECTORY_SEPARATOR . $fileBase . '.pdf';
        $xmlAbs = $dir . DIRECTORY_SEPARATOR . $fileBase . '.xml';
        $svgAbs = $dir . DIRECTORY_SEPARATOR . $fileBase . '.svg';

        $token = $request->header('X-Decolecta-Token') ?: env('DECOLECTA_TOKEN');
        $identidad = null;
        if ($data['tipo_documento'] === 'DNI') {
            $identidad = $this->fetchReniecDni($data['numero_documento'], $token);
        } else {
            $identidad = $this->fetchSunatRuc($data['numero_documento'], $token);
        }

        $total = $this->toFloat($pedido->total);
        $fechaEmision = now()->format('Y-m-d H:i:s');
        $clienteNombre = '';
        $verificadoTexto = 'No';
        if ($data['tipo_documento'] === 'DNI') {
            if (is_array($identidad)) {
                $clienteNombre = trim(($identidad['first_name'] ?? '') . ' ' . ($identidad['first_last_name'] ?? '') . ' ' . ($identidad['second_last_name'] ?? ''));
                $verificadoTexto = 'Sí';
            }
        } else {
            if (is_array($identidad)) {
                $clienteNombre = (string) ($identidad['razon_social'] ?? $identidad['nombre_o_razon_social'] ?? $identidad['nombre_comercial'] ?? '');
                $verificadoTexto = 'Sí';
            }
        }

        // PDF simple (Dompdf)
        $html = '<html><body style="font-family: sans-serif;">'
            . '<h2 style="margin-bottom: 4px;">Comprobante electrónico</h2>'
            . '<div>Tipo: <b>' . strtoupper($data['comprobante_tipo']) . '</b></div>'
            . '<div>Serie: <b>' . $serie . '</b></div>'
            . '<div>Número: <b>' . $numeroStr . '</b></div>'
            . '<div>Correlativo: <b>' . $numeroFormateado . '</b></div>'
            . '<hr/>'
            . '<div>Documento: <b>' . $data['tipo_documento'] . ' ' . htmlspecialchars($data['numero_documento']) . '</b></div>';

        if ($data['tipo_documento'] === 'DNI') {
            $nombre = '';
            if (is_array($identidad)) {
                $nombre = trim(($identidad['first_name'] ?? '') . ' ' . ($identidad['first_last_name'] ?? '') . ' ' . ($identidad['second_last_name'] ?? ''));
            }
            $html .= '<div>Cliente: <b>' . htmlspecialchars($nombre !== '' ? $nombre : 'N/A') . '</b></div>';
            $html .= '<div>Verificado en RENIEC: <b>' . $verificadoTexto . '</b></div>';
        } else {
            $razon = '';
            if (is_array($identidad)) {
                $razon = (string) ($identidad['razon_social'] ?? $identidad['nombre_o_razon_social'] ?? $identidad['nombre_comercial'] ?? '');
            }
            $html .= '<div>Razón Social: <b>' . htmlspecialchars($razon !== '' ? $razon : 'N/A') . '</b></div>';
            $html .= '<div>Verificado en SUNAT: <b>' . $verificadoTexto . '</b></div>';
        }

        $html .= '<hr/>'
            . '<div>Fecha de emisión: <b>' . $fechaEmision . '</b></div>'
            . '<div>Total: <b>S/ ' . number_format($total, 2, '.', '') . '</b></div>'
            . '</body></html>';

        try {
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->render();
            file_put_contents($pdfAbs, $dompdf->output());
        } catch (\Throwable $e) {
            return response()->json([
                'statusCode' => 500,
                'error' => 'Error interno',
                'message' => 'No se pudo generar el PDF',
            ], 500);
        }

        $xmlSkeleton =
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Comprobante tipo="' . $data['comprobante_tipo'] . '" serie="' . $serie . '" numero="' . $numeroStr . '">' . "\n"
            . '  <NumeroFormateado>' . $numeroFormateado . '</NumeroFormateado>' . "\n"
            . '  <Documento tipo="' . $data['tipo_documento'] . '">' . htmlspecialchars($data['numero_documento']) . '</Documento>' . "\n"
            . '  <Totales>' . "\n"
            . '    <OpGravada>' . number_format($total, 2, '.', '') . '</OpGravada>' . "\n"
            . '    <IGV>0.00</IGV>' . "\n"
            . '    <Total>' . number_format($total, 2, '.', '') . '</Total>' . "\n"
            . '  </Totales>' . "\n"
            . '</Comprobante>';
        file_put_contents($xmlAbs, $xmlSkeleton);

        $svgLines = [];
        $y = 60;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="26" font-weight="700">Comprobante electrónico</text>';
        $y += 34;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">Tipo: <tspan font-weight="700">' . $this->escXml(strtoupper($data['comprobante_tipo'])) . '</tspan></text>';
        $y += 24;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">Serie: <tspan font-weight="700">' . $this->escXml($serie) . '</tspan></text>';
        $y += 24;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">Número: <tspan font-weight="700">' . $this->escXml($numeroStr) . '</tspan></text>';
        $y += 24;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">Correlativo: <tspan font-weight="700">' . $this->escXml($numeroFormateado) . '</tspan></text>';
        $y += 18;
        $svgLines[] = '<line x1="40" y1="' . $y . '" x2="760" y2="' . $y . '" stroke="#333" stroke-width="1" />';
        $y += 28;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">Documento: <tspan font-weight="700">' . $this->escXml($data['tipo_documento'] . ' ' . $data['numero_documento']) . '</tspan></text>';
        $y += 24;
        $clienteLabel = $data['tipo_documento'] === 'DNI' ? 'Cliente' : 'Razón Social';
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">' . $this->escXml($clienteLabel) . ': <tspan font-weight="700">' . $this->escXml($clienteNombre !== '' ? $clienteNombre : 'N/A') . '</tspan></text>';
        $y += 24;
        $verificadoLabel = $data['tipo_documento'] === 'DNI' ? 'Verificado en RENIEC' : 'Verificado en SUNAT';
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">' . $this->escXml($verificadoLabel) . ': <tspan font-weight="700">' . $this->escXml($verificadoTexto) . '</tspan></text>';
        $y += 18;
        $svgLines[] = '<line x1="40" y1="' . $y . '" x2="760" y2="' . $y . '" stroke="#333" stroke-width="1" />';
        $y += 30;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">Fecha de emisión: <tspan font-weight="700">' . $this->escXml($fechaEmision) . '</tspan></text>';
        $y += 24;
        $svgLines[] = '<text x="40" y="' . $y . '" font-size="18">Total: <tspan font-weight="700">S/ ' . $this->escXml(number_format($total, 2, '.', '')) . '</tspan></text>';

        $svg =
            '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">'
            . '<rect x="0" y="0" width="800" height="450" fill="#ffffff" />'
            . '<g font-family="Arial, sans-serif" fill="#000000">'
            . implode('', $svgLines)
            . '</g></svg>';
        file_put_contents($svgAbs, $svg);

        $db = DB::table('comprobantes')->insertGetId([
            'pedido_id' => (int) $pedido->id,
            'tipo' => (string) $data['comprobante_tipo'],
            'serie' => $serie,
            'numero' => $numero,
            'numero_formateado' => $numeroFormateado,
            'archivo_nombre' => $fileBase . '.pdf',
            'archivo_ruta' => $pdfRel,
            'mime' => 'application/pdf',
            'size_bytes' => is_file($pdfAbs) ? filesize($pdfAbs) : null,
            'created_at' => now(),
        ]);

        return response()->json([
            'statusCode' => 200,
            'message' => 'Comprobante emitido exitosamente',
            'comprobante' => [
                'id' => (int) $db,
                'tipo' => (string) $data['comprobante_tipo'],
                'serie' => $serie,
                'numero' => $numero,
                'numero_formateado' => $numeroFormateado,
                'estado' => 'emitido',
                'pedido_id' => (int) $pedido->id,
                'total' => $total,
                'created_at' => now(),
            ],
            'archivos' => [
                'pdf' => '/uploads/' . $pdfRel,
                'xml' => '/uploads/' . $xmlRel,
                'img' => '/uploads/' . $svgRel,
            ],
        ], 200);
    }

    public function misComprobantes(Request $request)
    {
        $payload = $request->attributes->get('user');
        $usuarioId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        $items = DB::table('comprobantes as c')
            ->join('pedidos as p', 'p.id', '=', 'c.pedido_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'p.usuario_id')
            ->where('p.usuario_id', $usuarioId)
            ->orderBy('c.created_at', 'desc')
            ->select([
                'c.id', 'c.tipo', 'c.serie', 'c.numero', 'c.numero_formateado', 'c.archivo_ruta', 'c.created_at',
                'p.id as pedido_id', 'p.total as pedido_total',
                'u.nombre as u_nombre', 'u.apellido as u_apellido',
            ])
            ->get();

        $rows = $items->map(function ($c) {
            $fileBase = "pedido-{$c->pedido_id}-{$c->serie}-" . str_pad((string) $c->numero, 8, '0', STR_PAD_LEFT);
            return [
                'id' => (int) $c->id,
                'tipo' => (string) $c->tipo,
                'serie' => (string) $c->serie,
                'numero' => (string) $c->numero_formateado,
                'numero_formateado' => (string) $c->numero_formateado,
                'estado' => 'emitido',
                'total' => $this->toFloat($c->pedido_total),
                'created_at' => $c->created_at,
                'archivos' => [
                    'pdf' => '/uploads/' . str_replace('\\', '/', (string) $c->archivo_ruta),
                    'xml' => "/uploads/comprobantes/{$fileBase}.xml",
                    'img' => $this->comprobanteImageUrl($fileBase),
                ],
                'cliente' => [
                    'nombre' => trim((string) ($c->u_nombre ?? '') . ' ' . (string) ($c->u_apellido ?? '')) ?: 'Cliente',
                ],
            ];
        });

        return response()->json([
            'statusCode' => 200,
            'comprobantes' => $rows,
        ], 200);
    }

    public function adminComprobantes(Request $request)
    {
        $pagina = max(1, (int) ($request->query('pagina', '1')));
        $limite = max(1, (int) ($request->query('limite', '20')));
        $skip = ($pagina - 1) * $limite;
        $tipo = $request->query('tipo');

        $q = DB::table('comprobantes as c')->join('pedidos as p', 'p.id', '=', 'c.pedido_id');
        if ($tipo === 'boleta' || $tipo === 'factura') {
            $q->where('c.tipo', $tipo);
        }

        $total = (int) $q->count();
        $items = $q
            ->leftJoin('usuarios as u', 'u.id', '=', 'p.usuario_id')
            ->orderBy('c.created_at', 'desc')
            ->offset($skip)
            ->limit($limite)
            ->select([
                'c.id', 'c.tipo', 'c.serie', 'c.numero', 'c.numero_formateado', 'c.archivo_ruta', 'c.created_at',
                'p.id as pedido_id', 'p.total as pedido_total',
                'u.nombre as u_nombre', 'u.apellido as u_apellido',
            ])
            ->get();

        $rows = $items->map(function ($c) {
            $fileBase = "pedido-{$c->pedido_id}-{$c->serie}-" . str_pad((string) $c->numero, 8, '0', STR_PAD_LEFT);
            return [
                'id' => (int) $c->id,
                'tipo' => (string) $c->tipo,
                'serie' => (string) $c->serie,
                'numero' => (string) $c->numero_formateado,
                'numero_formateado' => (string) $c->numero_formateado,
                'estado' => 'emitido',
                'total' => $this->toFloat($c->pedido_total),
                'created_at' => $c->created_at,
                'archivos' => [
                    'pdf' => '/uploads/' . str_replace('\\', '/', (string) $c->archivo_ruta),
                    'xml' => "/uploads/comprobantes/{$fileBase}.xml",
                    'img' => $this->comprobanteImageUrl($fileBase),
                ],
                'cliente' => [
                    'nombre' => trim((string) ($c->u_nombre ?? '') . ' ' . (string) ($c->u_apellido ?? '')) ?: 'Cliente',
                    'razon_social' => null,
                    'dni' => null,
                    'ruc' => null,
                ],
            ];
        });

        return response()->json([
            'statusCode' => 200,
            'comprobantes' => $rows,
            'total' => $total,
            'pagina' => $pagina,
            'totalPaginas' => max(1, (int) ceil($total / $limite)),
        ], 200);
    }
}
