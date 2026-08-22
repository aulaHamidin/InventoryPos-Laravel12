<?php

namespace App\Console\Commands;

use App\Support\HardeningEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Throwable;

class ReconcileHardeningLoad extends Command
{
    protected $signature = 'hardening:reconcile {--output= : JSON output path}';

    protected $description = 'Reconcile stock, ledger, payments, actors, and tenant boundaries after F9A load';

    public function handle(HardeningEnvironment $environment): int
    {
        try {
            $database = $environment->assertSafe();
            $redisPrefix = $environment->assertRedisIsolation();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $tenantIds = DB::table('tenants')->where('slug', 'like', 'f9a-hardening-%')->pluck('id');
        if ($tenantIds->isEmpty()) {
            $this->error('Fixture F9A tidak ditemukan.');

            return self::FAILURE;
        }

        $stockMismatch = DB::table('items as i')
            ->leftJoinSub(
                DB::table('item_stock_movements')
                    ->selectRaw("item_id, SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) AS ledger_qty")
                    ->groupBy('item_id'),
                'm',
                'm.item_id',
                '=',
                'i.id',
            )
            ->whereIn('i.tenant_id', $tenantIds)
            ->whereRaw('i.stok_saat_ini <> COALESCE(m.ledger_qty, 0)')
            ->count();
        $negativeStock = DB::table('items')->whereIn('tenant_id', $tenantIds)->where('stok_saat_ini', '<', 0)->count();
        $duplicatePayment = DB::table('pos_payments')
            ->whereIn('tenant_id', $tenantIds)
            ->select('pos_transaction_id')->groupBy('pos_transaction_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $duplicateCheckoutKey = DB::table('pos_transactions')
            ->whereIn('tenant_id', $tenantIds)
            ->select('tenant_id', 'idempotency_key')->groupBy('tenant_id', 'idempotency_key')
            ->havingRaw('COUNT(*) > 1')->get()->count();
        $duplicatePaymentKey = DB::table('pos_payments')
            ->whereIn('tenant_id', $tenantIds)
            ->select('tenant_id', 'idempotency_key')->groupBy('tenant_id', 'idempotency_key')
            ->havingRaw('COUNT(*) > 1')->get()->count();
        $duplicateSaleMovement = DB::table('item_stock_movements')
            ->whereIn('tenant_id', $tenantIds)->where('movement_type', 'sale')
            ->whereNotNull('reference_id')->select('item_id', 'reference_id')
            ->groupBy('item_id', 'reference_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $cashierMismatch = DB::table('pos_transactions as p')
            ->join('users as u', 'u.id', '=', 'p.cashier_id')
            ->whereIn('p.tenant_id', $tenantIds)
            ->whereColumn('p.tenant_id', '<>', 'u.tenant_id')->count();
        $transactionItemMismatch = DB::table('pos_transaction_items as line')
            ->join('pos_transactions as p', 'p.id', '=', 'line.pos_transaction_id')
            ->join('items as i', 'i.id', '=', 'line.item_id')
            ->whereIn('line.tenant_id', $tenantIds)
            ->where(function ($query): void {
                $query->whereColumn('line.tenant_id', '<>', 'p.tenant_id')
                    ->orWhereColumn('line.tenant_id', '<>', 'i.tenant_id');
            })->count();
        $paymentTransactionMismatch = DB::table('pos_payments as pay')
            ->join('pos_transactions as p', 'p.id', '=', 'pay.pos_transaction_id')
            ->whereIn('pay.tenant_id', $tenantIds)
            ->whereColumn('pay.tenant_id', '<>', 'p.tenant_id')->count();
        $paymentActorTenantMismatch = DB::table('pos_payments as pay')
            ->join('users as u', 'u.id', '=', 'pay.confirmed_by')
            ->whereIn('pay.tenant_id', $tenantIds)
            ->whereColumn('pay.tenant_id', '<>', 'u.tenant_id')->count();
        $movementActorMismatch = DB::table('item_stock_movements as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->whereIn('m.tenant_id', $tenantIds)
            ->whereColumn('m.tenant_id', '<>', 'u.tenant_id')->count();
        $itemTenantMismatch = DB::table('item_stock_movements as m')
            ->join('items as i', 'i.id', '=', 'm.item_id')
            ->whereIn('m.tenant_id', $tenantIds)
            ->whereColumn('m.tenant_id', '<>', 'i.tenant_id')->count();
        $saleActorMismatch = DB::table('item_stock_movements as m')
            ->join('pos_transactions as p', function ($join): void {
                $join->on('p.id', '=', 'm.reference_id')
                    ->on('p.tenant_id', '=', 'm.tenant_id');
            })
            ->whereIn('m.tenant_id', $tenantIds)
            ->where('m.reference_type', 'App\\Models\\PosTransaction')
            ->where('m.movement_type', 'sale')
            ->whereColumn('m.user_id', '<>', 'p.cashier_id')->count();
        $manualPaymentActorMismatch = DB::table('pos_payments as pay')
            ->join('pos_transactions as p', function ($join): void {
                $join->on('p.id', '=', 'pay.pos_transaction_id')
                    ->on('p.tenant_id', '=', 'pay.tenant_id');
            })
            ->whereIn('pay.tenant_id', $tenantIds)
            ->whereIn('pay.method', ['qris', 'transfer'])
            ->whereColumn('pay.confirmed_by', '<>', 'p.cashier_id')->count();
        $unexpectedFailedJobs = DB::table('failed_jobs')->count();
        $analyticsQueueDepth = Queue::connection('redis')->size('analytics');
        $exportsQueueDepth = Queue::connection('redis')->size('exports');

        $result = [
            'database' => $database,
            'redis_prefix' => $redisPrefix,
            'generated_at' => now()->toIso8601String(),
            'tenant_count' => $tenantIds->count(),
            'stock_mismatch' => $stockMismatch,
            'negative_stock' => $negativeStock,
            'duplicate_payment' => $duplicatePayment,
            'duplicate_checkout_key' => $duplicateCheckoutKey,
            'duplicate_payment_key' => $duplicatePaymentKey,
            'duplicate_sale_movement' => $duplicateSaleMovement,
            'cashier_tenant_mismatch' => $cashierMismatch,
            'transaction_item_tenant_mismatch' => $transactionItemMismatch,
            'payment_transaction_tenant_mismatch' => $paymentTransactionMismatch,
            'payment_actor_tenant_mismatch' => $paymentActorTenantMismatch,
            'movement_actor_tenant_mismatch' => $movementActorMismatch,
            'movement_item_tenant_mismatch' => $itemTenantMismatch,
            'sale_actor_mismatch' => $saleActorMismatch,
            'manual_payment_actor_mismatch' => $manualPaymentActorMismatch,
            'failed_jobs' => $unexpectedFailedJobs,
            'analytics_queue_depth' => $analyticsQueueDepth,
            'exports_queue_depth' => $exportsQueueDepth,
        ];
        $result['passed'] = collect($result)
            ->except(['database', 'redis_prefix', 'generated_at', 'tenant_count'])
            ->every(fn ($value): bool => $value === 0);

        $path = $this->outputPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->table(['Metric', 'Value'], collect($result)->map(fn ($value, $key): array => [
            $key,
            is_bool($value) ? ($value ? 'true' : 'false') : $value,
        ])->values()->all());

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }

    private function outputPath(): string
    {
        $path = (string) ($this->option('output') ?: storage_path('framework/testing/f9a-reconciliation.json'));

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }
}
