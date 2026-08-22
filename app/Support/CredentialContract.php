<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class CredentialContract
{
    public static function password(string $password): void
    {
        if (mb_strlen($password) < 12) {
            throw ValidationException::withMessages([
                'password' => ['Password minimal 12 karakter.'],
            ]);
        }
    }
}
