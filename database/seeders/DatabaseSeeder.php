<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'nama_toko' => 'Toko Demo Inventori-Q',
            'slug' => 'demo-toko',
            'allow_negative_stock' => false,
            'dead_stock_days' => 90,
        ]);

        TenantContext::run($tenant, fn () => User::create([
            'name' => 'Owner Demo',
            'email' => 'owner@demo.com',
            'no_hp' => '081234567891',
            'password' => 'password',
            'role' => UserRole::Owner,
        ]));
    }
}
