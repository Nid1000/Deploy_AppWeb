<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

class PasswordVerifier
{
    private function passwordLooksHashed(string $hash): bool
    {
        $hash = trim($hash);

        return str_starts_with($hash, '$2y$')
            || str_starts_with($hash, '$2a$')
            || str_starts_with($hash, '$2b$');
    }

    public function verify(string $plain, string $stored): bool
    {
        $stored = (string) $stored;
        if ($stored === '') {
            return false;
        }

        try {
            return Hash::check($plain, $stored);
        } catch (\Throwable) {
        }

        if ($this->passwordLooksHashed($stored)) {
            return password_verify($plain, $stored);
        }

        return hash_equals($stored, $plain);
    }
}
