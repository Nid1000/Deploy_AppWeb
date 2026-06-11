<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaService
{
    public function enabled(): bool
    {
        $flag = (string) env('CHATBOT_ENABLE_OLLAMA', 'false');
        return in_array(strtolower($flag), ['1', 'true', 'yes', 'on'], true);
    }

    public function baseUrl(): string
    {
        return rtrim((string) env('OLLAMA_BASE_URL', ''), '/');
    }

    public function model(): string
    {
        $m = trim((string) env('OLLAMA_MODEL', 'llama3.1'));
        return $m !== '' ? $m : 'llama3.1';
    }

    public function timeoutSeconds(): int
    {
        $t = (int) env('OLLAMA_TIMEOUT_SECONDS', 20);
        return $t > 0 ? $t : 20;
    }

    public function generate(string $prompt): ?string
    {
        if (!$this->enabled()) {
            return null;
        }
        $base = $this->baseUrl();
        if ($base === '') {
            return null;
        }

        try {
            $resp = Http::timeout($this->timeoutSeconds())->post($base.'/api/generate', [
                'model' => $this->model(),
                'prompt' => $prompt,
                'stream' => false,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (!$resp->ok()) {
            return null;
        }

        $json = $resp->json();
        $text = is_array($json) ? (string) ($json['response'] ?? '') : '';
        $text = trim($text);
        return $text !== '' ? $text : null;
    }
}

