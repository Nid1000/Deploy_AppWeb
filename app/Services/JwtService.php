<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;

class JwtService
{
    public function sign(array $payload, int $ttlSeconds = 86400): string
    {
        $now = Carbon::now()->timestamp;
        $claims = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);

        $secret = env('JWT_SECRET');
        if (!$secret) {
            throw new \RuntimeException('JWT_SECRET no está configurado');
        }

        return JWT::encode($claims, $secret, 'HS256');
    }

    public function verify(string $token): array
    {
        $secret = env('JWT_SECRET');
        if (!$secret) {
            throw new \RuntimeException('JWT_SECRET no está configurado');
        }

        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return json_decode(json_encode($decoded), true);
    }
}

