<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasTenantScope;

    protected $fillable = ['kode', 'nama'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
