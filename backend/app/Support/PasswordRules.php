<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    public static function userPassword(): Password
    {
        return Password::min(6)
            ->mixedCase()
            ->numbers();
    }
}
