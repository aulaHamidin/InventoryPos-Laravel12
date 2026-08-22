<?php

namespace App\Console\Commands;

use App\Actions\Pos\CheckoutPosAction;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\HardeningEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class SeedHardeningFixture extends Command
{
    protected $signature = 'hardening:seed
        {--profile=smoke : smoke atau baseline}
        {--manifest= : Absolute/relative output manifest path}';

    protected $description = 'Seed deterministic synthetic F9A data on an isolated hardening database';

    public function handle(HardeningEnvironment $environment, CheckoutPosAction $checkout): int
    {
        try {
            $database = $environment->assertSafe();
            [$tenantCount, $itemCount] = match ((string) $this->option('profile')) {
                'smoke' => [2, 200],
                'baseline' => [10, 2000],
                default => throw new InvalidArgumentException('Profile wajib smoke atau baseline.'),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $manifestPath = $this->manifestPath();
        $password = Str::password(24, symbols: true);
        $now = now()->startOfSecond();
        $eligibleAt = $now->copy()->subDays(31);
        $manifest = [
            'version' => 1,
            'profile' => (string) $this->option('profile'),
            'database' => $database,
            'generated_at' => $now->toIso8601String(),
            'tenants' => [],
        ];

        if (Tenant::query()->where('slug', 'like', 'f9a-hardening-%')->exists()) {
            $this->error('Fixture F9A sudah ada. Jalankan migrate:fresh pada database hardening terisolasi sebelum seed ulang.');

            return self::FAILURE;
        }

        try {
            foreach (range(1, $tenantCount) as $tenantNumber) {
                $manifest['tenants'][] = DB::transaction(function () use (
                    $tenantNumber,
                    $itemCount,
                    $password,
                    $now,
                    $eligibleAt,
                    $checkout,
                ): array {
                    $tenant = Tenant::create([
                        'nama_toko' => sprintf('F9A Synthetic Store %02d', $tenantNumber),
                        'slug' => sprintf('f9a-hardening-%02d', $tenantNumber),
                        'allow_negative_stock' => false,
                        'dead_stock_days' => 90,
                    ]);

                    return TenantContext::run($tenant, function () use (
                        $tenant,
                        $tenantNumber,
                        $itemCount,
                        $password,
                        $now,
                        $eligibleAt,
                        $checkout,
                    ): array {
                        $category = Category::create([
                            'kode' => 'F9A-GENERAL',
                            'nama' => 'F9A Synthetic Category',
                        ]);
                        $owner = User::create([
                            'name' => sprintf('F9A Owner %02d', $tenantNumber),
                            'email' => sprintf('f9a-owner-%02d@example.test', $tenantNumber),
                            'no_hp' => sprintf('087700%06d', $tenantNumber),
                            'password' => $password,
                        ]);
                        $cashiers = collect(range(1, 2))->map(function (int $cashierNumber) use (
                            $tenantNumber,
                            $password,
                        ): User {
                            $user = User::create([
                                'name' => sprintf('F9A Cashier %02d-%d', $tenantNumber, $cashierNumber),
                                'email' => sprintf('f9a-cashier-%02d-%d@example.test', $tenantNumber, $cashierNumber),
                                'no_hp' => sprintf('0877%02d%06d', $tenantNumber, $cashierNumber),
                                'password' => $password,
                            ]);
                            $user->forceFill(['role' => UserRole::Staff])->save();

                            return $user->fresh();
                        });

                        foreach (array_chunk(range(1, $itemCount), 500) as $numbers) {
                            $rows = array_map(fn (int $number): array => [
                                'tenant_id' => $tenant->id,
                                'category_id' => $category->id,
                                'rack_id' => null,
                                'kode' => sprintf('F9A-%02d-%06d', $tenantNumber, $number),
                                'barcode' => sprintf('8999%02d%06d', $tenantNumber, $number),
                                'nama' => sprintf('F9A Synthetic Item %02d-%06d', $tenantNumber, $number),
                                'satuan' => 'Pcs',
                                'harga_beli' => '50.00',
                                'average_cost' => '50.00',
                                'harga_jual' => '100.00',
                                'stok_saat_ini' => 1000000,
                                'stok_minimal' => 50,
                                'threshold_mode' => 'manual',
                                'lead_time_days' => 0,
                                'safety_stock_days' => 0,
                                'exp_date' => null,
                                'movement_class' => 'unclassified',
                                'analytics_calculated_at' => null,
                                'is_active' => true,
                                'created_at' => $eligibleAt,
                                'updated_at' => $now,
                                'deleted_at' => null,
                            ], $numbers);
                            DB::table('items')->insert($rows);
                        }

                        $profileItems = DB::table('items')
                            ->where('tenant_id', $tenant->id)
                            ->orderBy('id')
                            ->limit(min(100, $itemCount))
                            ->get(['id', 'kode', 'barcode']);
                        $analyticsItems = $profileItems->take(50);

                        DB::table('items')->where('tenant_id', $tenant->id)->orderBy('id')->chunkById(500, function ($items) use (
                            $tenant,
                            $cashiers,
                            $eligibleAt,
                        ): void {
                            DB::table('item_stock_movements')->insert($items->map(fn ($item): array => [
                                'tenant_id' => $tenant->id,
                                'item_id' => $item->id,
                                'user_id' => $cashiers->first()->id,
                                'supplier_id' => null,
                                'movement_type' => 'stock_in',
                                'qty' => 1000000,
                                'direction' => 'in',
                                'harga_satuan' => '50.00',
                                'note' => 'F9A synthetic opening balance',
                                'reference_type' => null,
                                'reference_id' => null,
                                'created_at' => $eligibleAt,
                            ])->all());
                        }, 'id');
                        DB::table('item_stock_movements')->insert($analyticsItems->map(fn ($item): array => [
                            'tenant_id' => $tenant->id,
                            'item_id' => $item->id,
                            'user_id' => $cashiers->first()->id,
                            'supplier_id' => null,
                            'movement_type' => 'sale',
                            'qty' => 10,
                            'direction' => 'out',
                            'harga_satuan' => '100.00',
                            'note' => 'F9A synthetic analytics history',
                            'reference_type' => null,
                            'reference_id' => null,
                            'created_at' => $now->copy()->subDay(),
                        ])->all());
                        DB::table('items')->whereIn('id', $analyticsItems->pluck('id'))->decrement('stok_saat_ini', 10);

                        $stockRaceItems = DB::table('items')
                            ->where('tenant_id', $tenant->id)
                            ->orderBy('id')
                            ->offset(100)
                            ->limit(2)
                            ->get(['id', 'kode', 'barcode']);
                        DB::table('items')->whereIn('id', $stockRaceItems->pluck('id'))->update(['stok_saat_ini' => 1]);
                        DB::table('item_stock_movements')
                            ->whereIn('item_id', $stockRaceItems->pluck('id'))
                            ->where('movement_type', 'stock_in')
                            ->update(['qty' => 1]);

                        $statusTransactions = $cashiers->values()->mapWithKeys(function (User $cashier, int $index) use (
                            $checkout,
                            $profileItems,
                        ): array {
                            $item = $profileItems->get(50 + $index) ?? $profileItems->last();
                            $transaction = $checkout->execute([
                                ['item_id' => (int) $item->id, 'qty' => 1, 'discount_amount' => '0.00'],
                            ], (string) Str::uuid(), $cashier);

                            return [(int) $cashier->id => (int) $transaction->id];
                        });

                        return [
                            'id' => $tenant->id,
                            'slug' => $tenant->slug,
                            'owner' => $this->actorManifest($owner, $password),
                            'cashiers' => $cashiers->map(fn (User $user): array => $this->actorManifest(
                                $user,
                                $password,
                                $statusTransactions->get((int) $user->id),
                            ))->all(),
                            'items' => $profileItems->map(fn ($item): array => (array) $item)->all(),
                            'stock_race_items' => $stockRaceItems->map(fn ($item): array => (array) $item)->all(),
                        ];
                    });
                });
            }
        } finally {
            TenantContext::clear();
        }

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        @chmod($manifestPath, 0600);

        $this->info(sprintf(
            'Hardening fixture ready: profile=%s tenants=%d items_per_tenant=%d.',
            $this->option('profile'),
            $tenantCount,
            $itemCount,
        ));
        $this->line('Credential manifest ditulis ke protected path; nilainya tidak dicetak.');

        return self::SUCCESS;
    }

    private function manifestPath(): string
    {
        $path = (string) ($this->option('manifest') ?: storage_path('framework/testing/f9a-hardening-manifest.json'));

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    private function actorManifest(User $user, string $password, ?int $statusTransactionId = null): array
    {
        $manifest = [
            'id' => $user->id,
            'email' => $user->email,
            'password' => $password,
            'token' => $user->createToken('f9a-hardening')->plainTextToken,
        ];

        if ($statusTransactionId !== null) {
            $manifest['status_transaction_id'] = $statusTransactionId;
        }

        return $manifest;
    }
}
