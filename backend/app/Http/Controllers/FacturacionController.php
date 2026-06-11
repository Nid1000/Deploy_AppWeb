<?php

namespace App\Http\Controllers;

use App\Services\ComprobanteEmailService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FacturacionController extends Controller
{
    public function __construct(
        private readonly ComprobanteEmailService $comprobanteEmail
    ) {
    }

    private function sanitizeToken(string $value): ?string
    {
        $token = trim($value);
        if ($token === '') {
            return null;
        }

        if (in_array(strtolower($token), ['tu_token_real', 'your_token', 'your_token_here', 'null'], true)) {
            return null;
        }

        return $token;
    }

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

    private function comprobanteFileBase(int $pedidoId, string $serie, int $numero): string
    {
        return "pedido-{$pedidoId}-{$serie}-" . str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }

    private function ensureComprobanteArtifacts(object $comprobante, ?object $pedido = null): array
    {
        $pedido ??= DB::table('pedidos')->where('id', (int) $comprobante->pedido_id)->first();
        $fileBase = $this->comprobanteFileBase((int) $comprobante->pedido_id, (string) $comprobante->serie, (int) $comprobante->numero);
        $dir = $this->comprobantesDir();

        $pdfRel = 'comprobantes/' . $fileBase . '.pdf';
        $xmlRel = 'comprobantes/' . $fileBase . '.xml';
        $svgRel = 'comprobantes/' . $fileBase . '.svg';
        $pdfAbs = $dir . DIRECTORY_SEPARATOR . $fileBase . '.pdf';
        $xmlAbs = $dir . DIRECTORY_SEPARATOR . $fileBase . '.xml';
        $svgAbs = $dir . DIRECTORY_SEPARATOR . $fileBase . '.svg';

        $tipo = strtoupper((string) ($comprobante->tipo ?? 'boleta'));
        $numeroFormateado = (string) ($comprobante->numero_formateado ?? ($comprobante->serie . '-' . str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT)));
        $total = $pedido ? $this->toFloat($pedido->total ?? 0) : 0.0;
        $fecha = (string) ($comprobante->created_at ?? now()->format('Y-m-d H:i:s'));

        if (!is_file($pdfAbs)) {
            $html = '<html><body style="font-family: sans-serif;">'
                . '<h2 style="margin-bottom: 4px;">Comprobante electronico</h2>'
                . '<div>Tipo: <b>' . htmlspecialchars($tipo) . '</b></div>'
                . '<div>Correlativo: <b>' . htmlspecialchars($numeroFormateado) . '</b></div>'
                . '<div>Pedido: <b>#' . (int) $comprobante->pedido_id . '</b></div>'
                . '<div>Fecha de emision: <b>' . htmlspecialchars($fecha) . '</b></div>'
                . '<div>Total: <b>S/ ' . number_format($total, 2, '.', '') . '</b></div>'
                . '</body></html>';

            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->render();
            file_put_contents($pdfAbs, $dompdf->output());
        }

        if (!is_file($xmlAbs)) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<Comprobante tipo="' . strtolower($tipo) . '" serie="' . $this->escXml((string) $comprobante->serie) . '" numero="' . str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT) . '">' . "\n"
                . '  <NumeroFormateado>' . $this->escXml($numeroFormateado) . '</NumeroFormateado>' . "\n"
                . '  <PedidoId>' . (int) $comprobante->pedido_id . '</PedidoId>' . "\n"
                . '  <Total>' . number_format($total, 2, '.', '') . '</Total>' . "\n"
                . '</Comprobante>';
            file_put_contents($xmlAbs, $xml);
        }

        if (!is_file($svgAbs)) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">'
                . '<rect width="800" height="450" fill="#fff"/>'
                . '<g font-family="Arial, sans-serif" fill="#111">'
                . '<text x="40" y="70" font-size="28" font-weight="700">Comprobante electronico</text>'
                . '<text x="40" y="120" font-size="18">Tipo: ' . $this->escXml($tipo) . '</text>'
                . '<text x="40" y="150" font-size="18">Numero: ' . $this->escXml($numeroFormateado) . '</text>'
                . '<text x="40" y="180" font-size="18">Pedido: #' . (int) $comprobante->pedido_id . '</text>'
                . '<text x="40" y="210" font-size="18">Total: S/ ' . $this->escXml(number_format($total, 2, '.', '')) . '</text>'
                . '</g></svg>';
            file_put_contents($svgAbs, $svg);
        }

        DB::table('comprobantes')->where('id', (int) $comprobante->id)->update([
            'archivo_ruta' => $pdfRel,
            'archivo_nombre' => $fileBase . '.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => is_file($pdfAbs) ? filesize($pdfAbs) : null,
        ]);

        return [
            'pdf' => '/uploads/' . $pdfRel,
            'xml' => '/uploads/' . $xmlRel,
            'img' => '/uploads/' . $svgRel,
        ];
    }

    private function decolectaBaseUrl(): string
    {
        return rtrim((string) config('services.documents.decolecta.base_url'), '/');
    }

    private function apiperuBaseUrl(): string
    {
        return rtrim((string) config('services.documents.apiperu.base_url'), '/');
    }

    private function documentValidationRequired(): bool
    {
        return (bool) config('services.documents.validation_required', true);
    }

    private function documentProvider(): string
    {
        $provider = strtolower(trim((string) config('services.documents.provider', 'apiperu')));
        if (in_array($provider, ['decolecta', 'apiperu'], true)) {
            return $provider;
        }

        $decolectaToken = $this->sanitizeToken((string) (
            config('services.documents.decolecta.token')
            ?: config('services.documents.decolecta.reniec_token')
            ?: config('services.documents.decolecta.sunat_token')
        ));
        if ($decolectaToken !== null) {
            return 'decolecta';
        }

        return 'apiperu';
    }

    private function apiPeruToken(Request $request): ?string
    {
        $candidates = [
            (string) $request->header('X-ApiPeru-Token'),
            (string) $request->header('X-Api-Peru-Token'),
            (string) config('services.documents.apiperu.token'),
        ];

        foreach ($candidates as $candidate) {
            $token = $this->sanitizeToken($candidate);
            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    private function decolectaToken(Request $request, string $provider): ?string
    {
        $providerHeader = $provider === 'reniec'
            ? (string) $request->header('X-Reniec-Token')
            : (string) $request->header('X-Sunat-Token');

        $providerEnv = $provider === 'reniec'
            ? (string) config('services.documents.decolecta.reniec_token')
            : (string) config('services.documents.decolecta.sunat_token');

        $candidates = [
            $providerHeader,
            (string) $request->header('X-Decolecta-Token'),
            $providerEnv,
            (string) config('services.documents.decolecta.token'),
        ];

        foreach ($candidates as $candidate) {
            $token = $this->sanitizeToken($candidate);
            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    private function normalizeDocumentNumber(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?: '';
    }

    private function readDocumentQuery(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->normalizeDocumentNumber((string) $request->query($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function isValidDni(string $dni): bool
    {
        return preg_match('/^\d{8}$/', $dni) === 1;
    }

    private function isValidRuc(string $ruc): bool
    {
        if (preg_match('/^\d{11}$/', $ruc) !== 1) {
            return false;
        }

        if (!in_array(substr($ruc, 0, 2), ['10', '15', '17', '20'], true)) {
            return false;
        }

        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $ruc[$i]) * $weights[$i];
        }

        $check = 11 - ($sum % 11);
        if ($check === 10) {
            $check = 0;
        } elseif ($check === 11) {
            $check = 1;
        }

        return $check === (int) $ruc[10];
    }

    private function providerToken(Request $request, string $provider): ?string
    {
        return $this->documentProvider() === 'apiperu'
            ? $this->apiPeruToken($request)
            : $this->decolectaToken($request, $provider);
    }

    private function providerName(): string
    {
        return $this->documentProvider() === 'apiperu' ? 'APIPERU' : 'DECOLECTA';
    }

    private function normalizeDniResponse(array $data): array
    {
        $firstName = (string) ($data['first_name'] ?? $data['nombres'] ?? $data['nombre'] ?? '');
        $firstLastName = (string) ($data['first_last_name'] ?? $data['apellido_paterno'] ?? $data['apellidoPaterno'] ?? $data['ape_paterno'] ?? '');
        $secondLastName = (string) ($data['second_last_name'] ?? $data['apellido_materno'] ?? $data['apellidoMaterno'] ?? $data['ape_materno'] ?? '');
        $fullName = trim((string) ($data['full_name'] ?? $data['nombre_completo'] ?? "{$firstName} {$firstLastName} {$secondLastName}"));

        return array_filter([
            'numero' => $data['numero'] ?? $data['dni'] ?? null,
            'first_name' => $firstName,
            'first_last_name' => $firstLastName,
            'second_last_name' => $secondLastName,
            'nombres' => $firstName,
            'apellido_paterno' => $firstLastName,
            'apellido_materno' => $secondLastName,
            'nombre_completo' => $fullName,
            'validacion' => $data['validacion'] ?? null,
            'raw' => $data,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function normalizeRucResponse(array $data): array
    {
        return array_filter([
            'numero' => $data['numero'] ?? $data['ruc'] ?? null,
            'razon_social' => $data['razon_social'] ?? $data['razonSocial'] ?? $data['social_reason'] ?? $data['company_name'] ?? $data['nombre_o_razon_social'] ?? null,
            'nombre_o_razon_social' => $data['nombre_o_razon_social'] ?? $data['razon_social'] ?? $data['razonSocial'] ?? $data['social_reason'] ?? $data['company_name'] ?? null,
            'nombre_comercial' => $data['nombre_comercial'] ?? $data['nombreComercial'] ?? $data['trade_name'] ?? $data['commercial_name'] ?? null,
            'estado' => $data['estado'] ?? null,
            'condicion' => $data['condicion'] ?? null,
            'direccion' => $data['direccion'] ?? $data['direccion_completa'] ?? $data['domicilio_fiscal'] ?? $data['domicilio'] ?? null,
            'validacion' => $data['validacion'] ?? null,
            'raw' => $data,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function localDniResponse(string $dni): array
    {
        return [
            'numero' => $dni,
            'nombre_completo' => 'DNI con formato valido',
            'validacion' => 'local',
            'mensaje' => 'RENIEC no esta configurado. Se valido solo el formato del DNI.',
        ];
    }

    private function localRucResponse(string $ruc): array
    {
        return [
            'numero' => $ruc,
            'razon_social' => 'RUC con formato valido',
            'validacion' => 'local',
            'mensaje' => 'SUNAT no esta configurado. Se valido solo el formato y digito verificador del RUC.',
        ];
    }

    private function decolectaVerifyOption()
    {
        $envBundle = trim((string) config('services.documents.ca_bundle'));
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

        if ($this->documentProvider() === 'apiperu') {
            $apiperu = $this->fetchApiperuDni($dni, $token);
            if ($apiperu) {
                return $apiperu;
            }
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

        if ($this->documentProvider() === 'apiperu') {
            $apiperu = $this->fetchApiperuRuc($ruc, $token);
            if ($apiperu) {
                return $apiperu;
            }
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

    private function fetchApiperuDni(string $dni, ?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        try {
            $resp = Http::acceptJson()
                ->withOptions(['verify' => $this->decolectaVerifyOption()])
                ->timeout(15)
                ->connectTimeout(10)
                ->get($this->apiperuBaseUrl() . '/dni/' . $dni, [
                    'token' => $token,
                ]);

            if (!$resp->ok()) {
                return null;
            }

            $payload = $resp->json();
            if (!is_array($payload) || empty($payload['dni'])) {
                return null;
            }

            return $payload;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchApiperuRuc(string $ruc, ?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        try {
            $resp = Http::acceptJson()
                ->withOptions(['verify' => $this->decolectaVerifyOption()])
                ->timeout(15)
                ->connectTimeout(10)
                ->get($this->apiperuBaseUrl() . '/ruc/' . $ruc, [
                    'token' => $token,
                ]);

            if (!$resp->ok()) {
                return null;
            }

            $payload = $resp->json();
            if (!is_array($payload) || empty($payload['ruc'])) {
                return null;
            }

            return $payload;
        } catch (\Throwable) {
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
        $numero = $this->readDocumentQuery($request, ['numero', 'dni', 'documento', 'numero_documento']);
        if (!$this->isValidDni($numero)) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Documento invalido',
                'message' => 'El DNI debe tener exactamente 8 digitos numericos',
            ], 400);
        }

        $token = $this->providerToken($request, 'reniec');
        if (!$token) {
            if (!$this->documentValidationRequired()) {
                return response()->json([
                    'statusCode' => 200,
                    'dni' => $numero,
                    'validado' => true,
                    'validacion_real' => false,
                    'proveedor' => 'FORMATO_LOCAL',
                    'message' => 'Solo se valido el formato del DNI.',
                    'data' => $this->localDniResponse($numero),
                ], 200);
            }

            return response()->json([
                'statusCode' => 503,
                'error' => 'Proveedor no configurado',
                'message' => $this->documentProvider() === 'apiperu'
                    ? 'Configura APIPERU_TOKEN en backend/.env para consultar DNI real'
                    : 'Configura RENIEC_API_TOKEN o DECOLECTA_TOKEN en backend/.env para consultar DNI real',
            ], 503);
        }

        $data = $this->fetchReniecDni($numero, $token);
        if (!$data) {
            if (!$this->documentValidationRequired()) {
                return response()->json([
                    'statusCode' => 200,
                    'dni' => $numero,
                    'validado' => true,
                    'validacion_real' => false,
                    'proveedor' => 'FORMATO_LOCAL',
                    'message' => 'No se pudo conectar con RENIEC. Se valido el formato del DNI para continuar.',
                    'data' => $this->localDniResponse($numero),
                ], 200);
            }

            return response()->json([
                'statusCode' => 404,
                'error' => 'Documento no encontrado',
                'message' => 'No se encontro informacion del DNI en ' . $this->providerName(),
            ], 404);
        }

        return response()->json([
            'statusCode' => 200,
            'dni' => $numero,
            'validado' => true,
            'validacion_real' => true,
            'proveedor' => $this->providerName(),
            'data' => $this->normalizeDniResponse($data),
        ], 200);
    }

    public function consultaRuc(Request $request)
    {
        $numero = $this->readDocumentQuery($request, ['numero', 'ruc', 'documento', 'numero_documento']);
        if (!$this->isValidRuc($numero)) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Documento invalido',
                'message' => 'El RUC debe tener 11 digitos, prefijo valido y digito verificador correcto',
            ], 400);
        }

        $token = $this->providerToken($request, 'sunat');
        if (!$token) {
            if (!$this->documentValidationRequired()) {
                return response()->json([
                    'statusCode' => 200,
                    'ruc' => $numero,
                    'validado' => true,
                    'validacion_real' => false,
                    'proveedor' => 'FORMATO_LOCAL',
                    'message' => 'Solo se valido el formato del RUC.',
                    'data' => $this->localRucResponse($numero),
                ], 200);
            }

            return response()->json([
                'statusCode' => 503,
                'error' => 'Proveedor no configurado',
                'message' => $this->documentProvider() === 'apiperu'
                    ? 'Configura APIPERU_TOKEN en backend/.env para consultar RUC real'
                    : 'Configura SUNAT_API_TOKEN o DECOLECTA_TOKEN en backend/.env para consultar RUC real',
            ], 503);
        }

        $data = $this->fetchSunatRuc($numero, $token);
        if (!$data) {
            if (!$this->documentValidationRequired()) {
                return response()->json([
                    'statusCode' => 200,
                    'ruc' => $numero,
                    'validado' => true,
                    'validacion_real' => false,
                    'proveedor' => 'FORMATO_LOCAL',
                    'message' => 'No se pudo conectar con SUNAT. Se valido el formato del RUC para continuar.',
                    'data' => $this->localRucResponse($numero),
                ], 200);
            }

            return response()->json([
                'statusCode' => 404,
                'error' => 'Documento no encontrado',
                'message' => 'No se encontro informacion del RUC en ' . $this->providerName(),
            ], 404);
        }

        return response()->json([
            'statusCode' => 200,
            'ruc' => $numero,
            'validado' => true,
            'validacion_real' => true,
            'proveedor' => $this->providerName(),
            'data' => $this->normalizeRucResponse($data),
        ], 200);
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

        $data['numero_documento'] = $this->normalizeDocumentNumber((string) $data['numero_documento']);

        if ($data['tipo_documento'] === 'DNI' && !$this->isValidDni($data['numero_documento'])) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Documento invalido',
                'message' => 'El DNI debe tener exactamente 8 digitos numericos',
            ], 400);
        }

        if ($data['tipo_documento'] === 'RUC' && !$this->isValidRuc($data['numero_documento'])) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Documento invalido',
                'message' => 'El RUC debe tener 11 digitos, prefijo valido y digito verificador correcto',
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
        $exist = DB::table('comprobantes')
            ->where('pedido_id', (int) $pedido->id)
            ->where('tipo', (string) $data['comprobante_tipo'])
            ->first();

        if ($exist) {
            $archivos = $this->ensureComprobanteArtifacts($exist, $pedido);
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
                'archivos' => $archivos,
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

        $token = $this->providerToken($request, $data['tipo_documento'] === 'DNI' ? 'reniec' : 'sunat');
        if (!$token) {
            if ($this->documentValidationRequired()) {
                return response()->json([
                    'statusCode' => 503,
                    'error' => 'Proveedor no configurado',
                    'message' => $this->documentProvider() === 'apiperu'
                        ? 'Configura APIPERU_TOKEN en backend/.env para validar el documento antes de emitir el comprobante'
                        : 'Configura RENIEC_API_TOKEN, SUNAT_API_TOKEN o DECOLECTA_TOKEN en backend/.env para validar el documento antes de emitir el comprobante',
                ], 503);
            }
        }

        $identidad = null;
        if (!$token && $data['tipo_documento'] === 'DNI') {
            $identidad = $this->localDniResponse($data['numero_documento']);
        } elseif (!$token && $data['tipo_documento'] === 'RUC') {
            $identidad = $this->localRucResponse($data['numero_documento']);
        } elseif ($data['tipo_documento'] === 'DNI') {
            $identidad = $this->fetchReniecDni($data['numero_documento'], $token);
        } else {
            $identidad = $this->fetchSunatRuc($data['numero_documento'], $token);
        }

        if (!$identidad) {
            if (!$this->documentValidationRequired()) {
                $identidad = $data['tipo_documento'] === 'DNI'
                    ? $this->localDniResponse($data['numero_documento'])
                    : $this->localRucResponse($data['numero_documento']);
            } else {
                return response()->json([
                    'statusCode' => 422,
                    'error' => 'Documento no validado',
                    'message' => $data['tipo_documento'] === 'DNI'
                        ? 'No se pudo validar el DNI en RENIEC'
                        : 'No se pudo validar el RUC en SUNAT',
                ], 422);
            }
        }

        $identidad = $data['tipo_documento'] === 'DNI'
            ? $this->normalizeDniResponse($identidad)
            : $this->normalizeRucResponse($identidad);

        $total = $this->toFloat($pedido->total);
        $fechaEmision = now()->format('Y-m-d H:i:s');
        $clienteNombre = '';
        $verificadoTexto = 'No';
        if ($data['tipo_documento'] === 'DNI') {
            if (is_array($identidad)) {
                $clienteNombre = trim(($identidad['first_name'] ?? '') . ' ' . ($identidad['first_last_name'] ?? '') . ' ' . ($identidad['second_last_name'] ?? ''));
                if ($clienteNombre === '') {
                    $clienteNombre = (string) ($identidad['nombre_completo'] ?? '');
                }
                $verificadoTexto = 'Sí';
            }
        } else {
            if (is_array($identidad)) {
                $clienteNombre = (string) ($identidad['razon_social'] ?? $identidad['nombre_o_razon_social'] ?? $identidad['nombre_comercial'] ?? '');
                $verificadoTexto = 'Sí';
            }
        }

        if (is_array($identidad) && ($identidad['validacion'] ?? '') === 'local') {
            $verificadoTexto = 'Solo formato';
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
            $html .= '<div>Cliente: <b>' . htmlspecialchars($clienteNombre !== '' ? $clienteNombre : 'N/A') . '</b></div>';
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

        $usuario = DB::table('usuarios')
            ->select(['nombre', 'apellido', 'email'])
            ->where('id', $usuarioId)
            ->first();

        $correo = $usuario
            ? $this->comprobanteEmail->send(
                $usuario,
                $pedido,
                (string) $data['comprobante_tipo'],
                $numeroFormateado,
                $pdfAbs,
                (int) $db
            )
            : [
                'enviado' => false,
                'message' => 'El comprobante fue emitido, pero no se encontro al usuario.',
            ];

        return response()->json([
            'statusCode' => 200,
            'message' => $correo['enviado']
                ? 'Comprobante emitido y enviado al correo registrado'
                : 'Comprobante emitido exitosamente',
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
            'correo' => $correo,
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
            $archivos = $this->ensureComprobanteArtifacts($c, (object) ['total' => $c->pedido_total ?? 0]);
            return [
                'id' => (int) $c->id,
                'tipo' => (string) $c->tipo,
                'serie' => (string) $c->serie,
                'numero' => (string) $c->numero_formateado,
                'numero_formateado' => (string) $c->numero_formateado,
                'estado' => 'emitido',
                'total' => $this->toFloat($c->pedido_total),
                'created_at' => $c->created_at,
                'archivos' => $archivos,
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
            $archivos = $this->ensureComprobanteArtifacts($c, (object) ['total' => $c->pedido_total ?? 0]);
            return [
                'id' => (int) $c->id,
                'tipo' => (string) $c->tipo,
                'serie' => (string) $c->serie,
                'numero' => (string) $c->numero_formateado,
                'numero_formateado' => (string) $c->numero_formateado,
                'estado' => 'emitido',
                'total' => $this->toFloat($c->pedido_total),
                'created_at' => $c->created_at,
                'archivos' => $archivos,
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
