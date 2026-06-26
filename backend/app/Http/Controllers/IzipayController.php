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
            'metodo' => 'izipay',
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
    body{font-family:Arial,sans-serif;background:#f8f4ed;margin:0;padding:24px;color:#2b2118}
    main{max-width:520px;margin:0 auto;background:white;border-radius:12px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
    h1{font-size:22px;margin:0 0 12px}
  </style>
</head>
<body>
  <main>
    <h1>Pago seguro Izipay</h1>
    <p>Pedido #{$pedidoId}</p>
    <div class="kr-embedded" kr-form-token="{$formTokenSafe}">
      <div class="kr-pan"></div>
      <div class="kr-expiry"></div>
      <div class="kr-security-code"></div>
      <button class="kr-payment-button"></button>
      <div class="kr-form-error"></div>
    </div>
  </main>
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
            ->whereIn('metodo', ['izipay', 'tarjeta'])
            ->update([
                'metodo' => 'izipay',
                'estado' => $paid ? 'pagado' : 'rechazado',
                'referencia' => $transaction ? $orderId.' / '.$transaction : $orderId,
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
