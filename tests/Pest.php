<?php

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

afterEach(fn () => TenantContext::clear());

function makeTenantUser(UserRole $role = UserRole::Owner, array $tenantAttributes = []): array
{
    $suffix = Str::lower(Str::random(10));
    $tenant = Tenant::create(array_merge([
        'nama_toko' => "Toko {$suffix}",
        'slug' => "toko-{$suffix}",
        'allow_negative_stock' => false,
        'dead_stock_days' => 90,
    ], $tenantAttributes));
    $legacy = Plan::query()->where('code', 'LEGACY-F0-F9')->firstOrFail();
    Subscription::query()->create([
        'tenant_id' => $tenant->getKey(), 'plan_id' => $legacy->getKey(),
        'status' => SubscriptionStatus::Active, 'starts_at' => $tenant->created_at,
        'ends_at' => '9999-12-31 16:59:59',
    ]);

    TenantContext::set($tenant);
    $user = makeTenantScopedUser([
        'name' => "User {$suffix}",
        'email' => "{$suffix}@example.test",
        'no_hp' => '08'.random_int(1000000000, 9999999999),
        'password' => 'password',
    ], $role);

    return [$tenant, $user];
}

function makeTenantScopedUser(array $attributes, UserRole $role = UserRole::Owner): User
{
    $user = User::create($attributes);
    $user->forceFill(['role' => $role])->save();

    return $user->fresh();
}

function makeInventoryItem(array $attributes = []): Item
{
    $suffix = Str::upper(Str::random(6));
    $category = Category::create(['kode' => "CAT-{$suffix}", 'nama' => "Kategori {$suffix}"]);

    return Item::create(array_merge([
        'category_id' => $category->getKey(),
        'kode' => "ITM-{$suffix}",
        'nama' => "Item {$suffix}",
        'satuan' => 'Pcs',
        'harga_beli' => '50.00',
        'average_cost' => '50.00',
        'harga_jual' => '100.00',
        'stok_saat_ini' => 10,
        'stok_minimal' => 2,
        'is_active' => true,
    ], $attributes));
}
