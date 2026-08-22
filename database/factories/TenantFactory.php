<?php

namespace Database\Factories;

use App\Enums\OperationalStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
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

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant): void {
            $legacy = Plan::query()->where('code', 'LEGACY-F0-F9')->first();
            if ($legacy !== null && ! Subscription::query()->where('tenant_id', $tenant->getKey())->exists()) {
                Subscription::query()->create([
                    'tenant_id' => $tenant->getKey(), 'plan_id' => $legacy->getKey(),
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => $tenant->created_at, 'ends_at' => '9999-12-31 16:59:59',
                ]);
            }
        });
    }
}
