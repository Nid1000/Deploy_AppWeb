<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class BackendApiClient
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->request()->get($this->url($path), $query);
    }

    public function post(string $path, array $payload = []): Response
    {
        return $this->request()->post($this->url($path), $payload);
    }

    public function postMultipart(string $path, array $payload, string $field, UploadedFile $file): Response
    {
        $handle = fopen($file->getRealPath(), 'r');

        return $this->baseRequest()
            ->attach($field, $handle, $file->getClientOriginalName())
            ->post($this->url($path), $payload);
    }

    public function put(string $path, array $payload = []): Response
    {
        return $this->request()->put($this->url($path), $payload);
    }

    public function patch(string $path, array $payload = []): Response
    {
        return $this->request()->patch($this->url($path), $payload);
    }

    public function delete(string $path, array $payload = []): Response
    {
        return $this->request()->delete($this->url($path), $payload);
    }

    public function request(): PendingRequest
    {
        return $this->baseRequest()->asJson();
    }

    private function baseRequest(): PendingRequest
    {
        $client = Http::acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->baseUrl(rtrim((string) config('services.backend.url'), '/'));

        $token = (string) $this->request->session()->get('auth_token', '');
        if ($token !== '') {
            $client = $client->withToken($token);
        }

        $headers = [];

        $apiPeru = trim((string) env('APIPERU_TOKEN', ''));
        if (in_array(strtolower($apiPeru), ['tu_token_real', 'your_token', 'your_token_here'], true)) {
            $apiPeru = '';
        }
        if ($apiPeru !== '') {
            $headers['X-ApiPeru-Token'] = $apiPeru;
        }

        $decolecta = trim((string) env('DECOLECTA_TOKEN', ''));
        if ($decolecta !== '') {
            $headers['X-Decolecta-Token'] = $decolecta;
        }

        if ($headers !== []) {
            $client = $client->withHeaders($headers);
        }

        return $client;
    }

    public function okData(Response $response, string $key = null, mixed $default = null): mixed
    {
        if (!$response->successful()) {
            return $default;
        }

        $data = $response->json();
        if ($key === null) {
            return $data;
        }

        return data_get($data, $key, $default);
    }

    public function errorMessage(Response $response, string $fallback): string
    {
        $payload = null;
        try {
            $payload = $response->json();
        } catch (\Throwable) {
            $payload = null;
        }

        $message = is_array($payload)
            ? (string) (data_get($payload, 'message') ?: data_get($payload, 'error') ?: '')
            : '';

        if ($message !== '' && !in_array(strtolower($message), ['server error', 'internal server error'], true)) {
            return $message;
        }

        if ($response->serverError()) {
            return $fallback . ' El backend devolvio error ' . $response->status() . '. Revisa logs y cache del backend.';
        }

        return $message !== '' ? $message : $fallback;
    }

    public function publicUrl(null|string $path = null): string
    {
        $base = rtrim((string) config('services.backend.url'), '/');
        if ($path === null || trim($path) === '') {
            return $base;
        }

        return $base . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    private function url(string $path): string
    {
        return '/api/' . ltrim($path, '/');
    }
}
