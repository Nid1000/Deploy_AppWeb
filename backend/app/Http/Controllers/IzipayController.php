<?php

namespace App\Http\Controllers;

use App\Services\IzipayService;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IzipayController extends Controller
{
    public function __construct(private readonly IzipayService $izipay)
    {
    }

    public function crear(Request $request)
    {
        $payload = $request->attributes->get('user');
        $usuarioId = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;

        try {
            $data = $request->validate([
                'pedido_id' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Datos invalidos',
                'message' => 'Validacion fallida',
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
                'message' => 'El pedido solicitado no existe',
            ], 404);
        }

        $usuario = DB::table('usuarios')
            ->select(['id', 'nombre', 'apellido', 'email', 'telefono'])
            ->where('id', $usuarioId)
            ->first();

        try {
            $formToken = $this->izipay->createFormToken($pedido, $usuario);
        } catch (\Throwable $e) {
            Log::error('No se pudo crear el formToken de Izipay.', [
                'pedido_id' => (int) $pedido->id,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'statusCode' => 503,
                'error' => 'Izipay no disponible',
                'message' => $e->getMessage(),
            ], 503);
        }

        DB::table('pagos')->where('pedido_id', (int) $pedido->id)->update([
            'metodo' => 'tarjeta',
            'estado' => 'pendiente',
            'referencia' => 'izipay:form-token-created',
        ]);

        $checkoutToken = app(JwtService::class)->sign([
            'purpose' => 'izipay_checkout',
            'pedido_id' => (int) $pedido->id,
            'usuario_id' => $usuarioId,
            'form_token' => $formToken,
        ], 15 * 60);

        return response()->json([
            'statusCode' => 200,
            'checkout_url' => url('/api/pagos/izipay/checkout?token='.$checkoutToken),
            'formToken' => $formToken,
            'publicKey' => $this->izipay->publicKey(),
            'orderId' => 'PEDIDO-'.(int) $pedido->id,
        ], 200);
    }

    public function checkout(Request $request)
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            return response('Token requerido', 400);
        }

        try {
            $payload = app(JwtService::class)->verify($token);
        } catch (\Throwable) {
            return response('Token invalido o expirado', 401);
        }

        if (($payload['purpose'] ?? null) !== 'izipay_checkout') {
            return response('Token invalido', 401);
        }

        $pedidoId = (int) ($payload['pedido_id'] ?? 0);
        $formToken = (string) ($payload['form_token'] ?? '');
        if ($pedidoId <= 0 || $formToken === '') {
            return response('Datos de pago invalidos', 400);
        }

        $pedido = DB::table('pedidos')->select(['id', 'total'])->where('id', $pedidoId)->first();
        $total = number_format((float) ($pedido->total ?? 0), 2);
        $publicKey = htmlspecialchars($this->izipay->publicKey(), ENT_QUOTES, 'UTF-8');
        $formTokenSafe = htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8');
        $jsUrl = htmlspecialchars($this->izipay->jsUrl(), ENT_QUOTES, 'UTF-8');
        $cssUrl = htmlspecialchars((string) preg_replace('/\.js(\?.*)?$/', '.css$1', $this->izipay->jsUrl()), ENT_QUOTES, 'UTF-8');
        $successUrl = htmlspecialchars(url('/api/pagos/izipay/confirmar'), ENT_QUOTES, 'UTF-8');
        $cancelUrl = htmlspecialchars((string) config('services.izipay.cancel_url'), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pago Izipay - Pedido #{$pedidoId}</title>
  <link rel="stylesheet" href="{$cssUrl}">
  <script src="{$jsUrl}"
    kr-public-key="{$publicKey}"
    kr-post-url-success="{$successUrl}"
    kr-post-url-refused="{$cancelUrl}"></script>
  <style>
    *{box-sizing:border-box}
    body{font-family:Arial,Helvetica,sans-serif;background:#f7f4ee;margin:0;color:#1f2933}
    .shell{min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:34px 16px}
    main{width:min(560px,100%);background:#fff;border:1px solid #ece7dd;border-radius:18px;padding:22px;box-shadow:0 18px 45px rgba(35,31,25,.10)}
    .brand{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
    .brand h1{font-size:22px;line-height:1.1;margin:0;color:#111827}
    .brand p{margin:6px 0 0;color:#6b7280;font-size:14px}
    .amount{border-radius:14px;background:#f9fafb;border:1px solid #e5e7eb;padding:10px 14px;text-align:right}
    .amount span{display:block;color:#6b7280;font-size:12px}
    .amount strong{display:block;margin-top:2px;color:#009b9f;font-size:18px}
    .methods{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0 14px}
    .method{min-height:64px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:10px 12px;font-size:13px;color:#374151;box-shadow:0 2px 8px rgba(17,24,39,.04)}
    .method.active{border-color:#00a6a6;box-shadow:0 0 0 1px #00a6a6,0 8px 18px rgba(0,166,166,.12)}
    .method .icon{display:block;color:#00a6a6;font-weight:700;font-size:15px;margin-bottom:5px}
    .method.disabled{color:#9ca3af;background:#fafafa}
    .kr-embedded{display:block;width:100%;margin-top:8px}
    .kr-embedded .kr-pan,
    .kr-embedded .kr-expiry,
    .kr-embedded .kr-security-code,
    .kr-embedded .kr-identity-document-type,
    .kr-embedded .kr-first-installment-delay,
    .kr-embedded .kr-installment-number{width:100%;min-height:46px;margin:10px 0;border:1px solid #d1d5db;border-radius:6px;background:#fff;padding:12px;color:#6b7280}
    .kr-embedded select,
    .kr-embedded input{width:100%;min-height:40px;border:1px solid #d1d5db;border-radius:6px;padding:10px 12px;background:#fff;font-size:14px}
    .kr-payment-button{width:100%;min-height:48px;margin-top:16px;border:0;border-radius:7px;background:#00a6a6;color:#fff;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 12px 24px rgba(0,166,166,.22)}
    .kr-payment-button:hover{background:#008f91}
    .kr-form-error{margin-top:12px;color:#dc2626;font-size:13px}
    .note{margin:10px 0 0;text-align:center;color:#8b8b8b;font-size:11px}
    .powered{margin-top:10px;text-align:center;color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:.08em}
    .powered strong{color:#00a6a6;text-transform:none;letter-spacing:0}
    @media(max-width:520px){.brand{align-items:flex-start;flex-direction:column}.amount{text-align:left;width:100%}.methods{grid-template-columns:1fr}.shell{padding:18px 10px}main{padding:18px}}
  </style>
</head>
<body>
  <div class="shell">
  <main>
    <div class="brand">
      <div>
        <h1>Pago seguro Izipay</h1>
        <p>Pedido #{$pedidoId}</p>
      </div>
      <div class="amount">
        <span>Total</span>
        <strong>S/ {$total}</strong>
      </div>
    </div>
    <div class="methods" aria-label="Metodos de pago">
      <div class="method active"><span class="icon">Tarjeta</span>Debito o credito</div>
      <div class="method disabled"><span class="icon">QR</span>No disponible</div>
      <div class="method disabled"><span class="icon">Yape</span>Usa el checkout</div>
    </div>
    <div class="kr-embedded" kr-form-token="{$formTokenSafe}">
      <div class="kr-pan"></div>
      <div class="kr-expiry"></div>
      <div class="kr-security-code"></div>
      <div class="kr-installment-number"></div>
      <div class="kr-first-installment-delay"></div>
      <button class="kr-payment-button"></button>
      <div class="kr-form-error"></div>
    </div>
    <p class="note">Recuerda activar tus compras por internet antes de pagar.</p>
    <p class="powered">Powered by <strong>izipay</strong></p>
  </main>
  </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function retorno()
    {
        return response('<h1>Pago recibido</h1><p>Puedes volver a la app de Delicias.</p>', 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function confirmar(Request $request)
    {
        return $this->handlePaymentAnswer($request, false);
    }

    public function ipn(Request $request)
    {
        return $this->handlePaymentAnswer($request, true);
    }

    public function cancelado()
    {
        return response('<h1>Pago no completado</h1><p>Puedes volver a la app e intentarlo nuevamente.</p>', 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function handlePaymentAnswer(Request $request, bool $fromIpn)
    {
        $answer = (string) ($request->input('kr-answer') ?: $request->input('kr_answer') ?: '');
        $hash = (string) ($request->input('kr-hash') ?: $request->input('kr_hash') ?: '');

        if ($answer === '' || $hash === '') {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Respuesta incompleta',
                'message' => 'Izipay no envio kr-answer o kr-hash.',
            ], 400);
        }

        try {
            $valid = $this->izipay->verifyHash($answer, $hash);
        } catch (\Throwable $e) {
            Log::warning('No se pudo verificar respuesta Izipay.', [
                'from_ipn' => $fromIpn,
                'error' => $e->getMessage(),
            ]);
            $valid = false;
        }

        if (!$valid) {
            return response()->json([
                'statusCode' => 400,
                'error' => 'Firma invalida',
                'message' => 'La respuesta de Izipay no pudo ser validada.',
            ], 400);
        }

        $decoded = $this->izipay->decodeAnswer($answer);
        $orderId = $this->izipay->orderIdFromAnswer($decoded);
        if (!preg_match('/^PEDIDO-(\d+)$/', $orderId, $matches)) {
            return response()->json([
                'statusCode' => 422,
                'error' => 'Orden invalida',
                'message' => 'No se pudo asociar el pago con un pedido.',
            ], 422);
        }

        $pedidoId = (int) $matches[1];
        $paid = $this->izipay->isPaid($decoded);
        $transaction = $this->izipay->transactionUuid($decoded);

        DB::table('pagos')
            ->where('pedido_id', $pedidoId)
            ->whereIn('metodo', ['tarjeta'])
            ->update([
                'metodo' => 'tarjeta',
                'estado' => $paid ? 'pagado' : 'rechazado',
                'referencia' => $transaction ? 'Izipay '.$orderId.' / '.$transaction : 'Izipay '.$orderId,
                'fecha' => now(),
            ]);

        return response()->json([
            'statusCode' => 200,
            'ok' => true,
            'paid' => $paid,
            'pedido_id' => $pedidoId,
            'order_id' => $orderId,
        ]);
    }
}
