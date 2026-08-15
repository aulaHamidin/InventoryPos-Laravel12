<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasTenantScope;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasTenantScope, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'no_hp', 'password', 'role', 'two_factor_secret', 'two_factor_confirmed_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed', 'role' => UserRole::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'app'
            && $this->role === UserRole::Owner
            && $this->tenant?->canOperate() === true;
    }
}
