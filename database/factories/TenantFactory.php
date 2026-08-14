<?php

namespace Database\Factories;

use App\Enums\OperationalStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'nama_toko' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'operational_status' => OperationalStatus::Active,
            'allow_negative_stock' => false,
            'dead_stock_days' => 90,
        ];
    }
}
