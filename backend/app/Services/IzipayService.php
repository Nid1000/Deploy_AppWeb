<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IzipayService
{
    public function enabled(): bool
    {
        return (bool) config('services.izipay.enabled', false);
    }

    public function configured(): bool
    {
        return $this->enabled()
            && $this->apiUserOptional() !== ''
            && $this->apiPasswordOptional() !== ''
            && $this->publicKeyOptional() !== '';
    }

    public function publicKey(): string
    {
        $key = $this->publicKeyOptional();
        if ($key === '') {
            throw new RuntimeException('La clave publica de Izipay no esta configurada.');
        }

        return $key;
    }

    public function staticBaseUrl(): string
    {
        return rtrim((string) config('services.izipay.static_base_url', 'https://static.micuentaweb.pe'), '/');
    }

    public function jsUrl(): string
    {
        $url = trim((string) config('services.izipay.js_url', ''));
        return $url !== ''
            ? $url
            : $this->staticBaseUrl().'/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js';
    }

    public function createPayment(array $params): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException('Izipay no esta habilitado en el servidor.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withBasicAuth($this->apiUser(), $this->apiPassword())
            ->timeout(30)
            ->post($this->apiUrl('/api-payment/V4/Charge/CreatePayment'), [
                'amount' => $this->amountInCents((float) $params['amount']),
                'currency' => $params['currency'] ?? config('services.izipay.currency', 'PEN'),
                'orderId' => (string) $params['orderId'],
                'customer' => array_filter([
                    'email' => $params['customerEmail'] ?? null,
                    'billingDetails' => array_filter([
                        'firstName' => $params['customerFirstName'] ?? null,
                        'lastName' => $params['customerLastName'] ?? null,
                        'phoneNumber' => $params['customerPhone'] ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                ], fn ($value) => $value !== null && $value !== [] && $value !== ''),
            ]);

        return $this->decodePaymentResponse($response);
    }

    public function createFormToken(object $pedido, ?object $usuario = null): string
    {
        $payment = $this->createPayment([
            'amount' => (float) $pedido->total,
            'currency' => config('services.izipay.currency', 'PEN'),
            'orderId' => 'PEDIDO-'.(int) $pedido->id,
            'customerEmail' => $usuario?->email,
            'customerFirstName' => $usuario?->nombre,
            'customerLastName' => $usuario?->apellido,
            'customerPhone' => $usuario?->telefono,
        ]);

        return $payment['formToken'];
    }

    public function verifyHash(string $answer, string $hash): bool
    {
        $key = $this->hmacKey();
        $binary = hash_hmac('sha256', $answer, $key, true);
        $base64 = base64_encode($binary);
        $hex = hash_hmac('sha256', $answer, $key);

        return hash_equals($base64, $hash) || hash_equals($hex, $hash);
    }

    public function decodeAnswer(string $answer): array
    {
        $decoded = json_decode($answer, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isPaid(array $answer): bool
    {
        $orderStatus = strtoupper((string) data_get($answer, 'orderStatus', ''));
        $paymentStatus = strtoupper((string) data_get($answer, 'transactions.0.status', ''));
        $transactionStatus = strtoupper((string) data_get($answer, 'transactions.0.detailedStatus', ''));

        return in_array($orderStatus, ['PAID', 'ACCEPTED'], true)
            || in_array($paymentStatus, ['PAID', 'ACCEPTED'], true)
            || in_array($transactionStatus, ['AUTHORISED', 'AUTHORIZED', 'CAPTURED'], true);
    }

    public function orderIdFromAnswer(array $answer): string
    {
        return (string) (
            data_get($answer, 'orderDetails.orderId')
            ?: data_get($answer, 'orderId')
            ?: ''
        );
    }

    public function transactionUuid(array $answer): ?string
    {
        $uuid = (string) (
            data_get($answer, 'transactions.0.uuid')
            ?: data_get($answer, 'transactions.0.transactionUuid')
            ?: data_get($answer, 'transactionUuid')
            ?: ''
        );

        return $uuid !== '' ? $uuid : null;
    }

    private function decodePaymentResponse(Response $response): array
    {
        if (!$response->successful()) {
            Log::warning('Izipay rechazo la solicitud de pago.', [
                'status' => $response->status(),
                'response' => $response->json() ?: $response->body(),
            ]);

            $message = (string) (
                data_get($response->json(), 'answer.errorMessage')
                ?: data_get($response->json(), 'answer.detailedErrorMessage')
                ?: data_get($response->json(), 'message')
                ?: 'Izipay rechazo la solicitud de pago.'
            );

            throw new RuntimeException($message);
        }

        $payload = $response->json();
        $formToken = (string) (
            data_get($payload, 'answer.formToken')
            ?: data_get($payload, 'formToken')
            ?: ''
        );

        if ($formToken === '') {
            throw new RuntimeException('Izipay no devolvio formToken.');
        }

        return [
            'formToken' => $formToken,
            'raw' => $payload,
        ];
    }

    private function apiUser(): string
    {
        $user = $this->apiUserOptional();
        if ($user === '') {
            throw new RuntimeException('IZIPAY_API_USER no esta configurado.');
        }

        return $user;
    }

    private function apiUserOptional(): string
    {
        return trim((string) (config('services.izipay.api_user') ?: config('services.izipay.username')));
    }

    private function apiPassword(): string
    {
        $password = $this->apiPasswordOptional();
        if ($password === '') {
            throw new RuntimeException('La contrasena API REST de Izipay no esta configurada.');
        }

        return $password;
    }

    private function apiPasswordOptional(): string
    {
        $password = $this->mode() === 'production'
            ? (string) config('services.izipay.api_password_production')
            : (string) config('services.izipay.api_password_test');

        return trim($password !== '' ? $password : (string) config('services.izipay.password'));
    }

    private function publicKeyOptional(): string
    {
        $key = $this->mode() === 'production'
            ? (string) config('services.izipay.public_key_production')
            : (string) config('services.izipay.public_key_test');

        return trim($key !== '' ? $key : (string) config('services.izipay.public_key'));
    }

    private function hmacKey(): string
    {
        $key = $this->mode() === 'production'
            ? (string) config('services.izipay.hmac_key_production')
            : (string) config('services.izipay.hmac_key_test');

        if (trim($key) === '') {
            throw new RuntimeException('La clave HMAC de Izipay no esta configurada.');
        }

        return $key;
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.izipay.api_rest_url'), '/') . '/' . ltrim($path, '/');
    }

    private function mode(): string
    {
        return strtolower((string) config('services.izipay.mode', 'test')) === 'production'
            ? 'production'
            : 'test';
    }

    private function amountInCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
