<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ApisPeruClient
{
    private function base(): PendingRequest
    {
        $client = Http::acceptJson()
            ->timeout(30)
            ->connectTimeout(10)
            ->baseUrl('https://facturacion.apisperu.com');

        $token = trim((string) config('services.apisperu.token', env('APISPERU_TOKEN', '')));

        if ($token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }

    public function sendInvoice(array $payload): Response
    {
        return $this->base()->post('/invoice/send', $payload);
    }

    public function sendSummary(array $payload): Response
    {
        return $this->base()->post('/summary/send', $payload);
    }
}
