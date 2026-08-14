<?php

namespace App\Models;

use App\Enums\AdminRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'two_factor_secret', 'two_factor_confirmed_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'role' => AdminRole::class, 'two_factor_confirmed_at' => 'datetime'];
    }
}
