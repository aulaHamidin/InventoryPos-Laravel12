<?php

namespace App\Models;

use App\Enums\AdminRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $attributes = ['is_active' => true, 'auth_version' => 1];

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_code_hashes', 'two_factor_last_used_step',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
            'is_active' => 'boolean',
            'auth_version' => 'integer',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_recovery_code_hashes' => 'array',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && in_array($this->role, [AdminRole::SuperAdmin, AdminRole::Support], true)
            && $this->is_active;
    }
}
