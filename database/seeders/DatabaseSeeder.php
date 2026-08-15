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
        $tenant = Tenant::updateOrCreate(['slug' => 'demo-toko'], [
            'nama_toko' => 'Toko Demo Inventori-Q',
            'allow_negative_stock' => false,
            'dead_stock_days' => 90,
        ]);

        TenantContext::run($tenant, function (): void {
            User::updateOrCreate(['email' => 'owner@demo.com'], [
                'name' => 'Owner Demo',
                'no_hp' => '081234567891',
                'password' => 'password',
                'role' => UserRole::Owner,
            ]);

            User::updateOrCreate(['email' => 'staff@demo.com'], [
                'name' => 'Staff Demo',
                'no_hp' => '081234567892',
                'password' => 'password',
                'role' => UserRole::Staff,
            ]);
        });
    }
}
