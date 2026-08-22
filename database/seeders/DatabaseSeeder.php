<?php

namespace Database\Seeders;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Subscription;
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

        $legacy = Plan::query()->where('code', 'LEGACY-F0-F9')->first();
        if ($legacy !== null) {
            Subscription::query()->firstOrCreate([
                'tenant_id' => $tenant->getKey(),
                'status' => SubscriptionStatus::Active,
            ], [
                'plan_id' => $legacy->getKey(),
                'starts_at' => $tenant->created_at,
                'ends_at' => '9999-12-31 16:59:59',
            ]);
        }

        TenantContext::run($tenant, function (): void {
            $owner = User::updateOrCreate(['email' => 'owner@demo.com'], [
                'name' => 'Owner Demo',
                'no_hp' => '081234567891',
                'password' => 'DemoPassword12!',
            ]);
            $owner->forceFill(['role' => UserRole::Owner])->save();

            $staff = User::updateOrCreate(['email' => 'staff@demo.com'], [
                'name' => 'Staff Demo',
                'no_hp' => '081234567892',
                'password' => 'DemoPassword12!',
            ]);
            $staff->forceFill(['role' => UserRole::Staff])->save();
        });
    }
}
