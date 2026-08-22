<?php

namespace App\Filament\Admin\Pages\Auth;

use Illuminate\Support\Str;

final class Login extends \Filament\Pages\Auth\Login
{
    protected function getRateLimitKey($method, $component = null): string
    {
        $email = Str::lower(trim((string) ($this->data['email'] ?? '')));

        return 'admin-login:'.hash('sha256', $email.'|'.(request()->ip() ?? 'unknown'));
    }
}
