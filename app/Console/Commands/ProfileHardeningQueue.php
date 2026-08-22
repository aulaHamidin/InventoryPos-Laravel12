<?php

namespace App\Console\Commands;

use App\Jobs\GenerateReportExport;
use App\Jobs\RecalculateItemAnalyticsJob;
use App\Models\Item;
use App\Models\ReportExport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\HardeningEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ProfileHardeningQueue extends Command
{
    protected $signature = 'hardening:profile-queue
        {--output= : JSON output path}
        {--timeout=300 : Maximum drain time in seconds}';

    protected $description = 'Dispatch and verify the F9A analytics/export Redis queue profile';

    public function handle(HardeningEnvironment $environment): int
    {
        try {
            $database = $environment->assertSafe();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (config('queue.default') !== 'redis') {
            $this->error('Queue profile wajib memakai QUEUE_CONNECTION=redis.');

            return self::FAILURE;
        }

        try {
            $redisPrefix = $environment->assertRedisIsolation();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $tenants = Tenant::query()->where('slug', 'like', 'f9a-hardening-%')->orderBy('id')->get();
        if ($tenants->count() !== 10) {
            $this->error('Queue profile memerlukan baseline fixture 10 tenant.');

            return self::FAILURE;
        }

        foreach (['analytics', 'exports'] as $queue) {
            if (Queue::connection('redis')->size($queue) !== 0) {
                $this->error("Queue {$queue} harus kosong sebelum profile.");

                return self::FAILURE;
            }
        }

        $startedAt = now();
        $analyticsJobs = 0;
        $exportJobs = 0;
        $exportIds = [];

        foreach ($tenants as $index => $tenant) {
            TenantContext::run($tenant, function () use ($tenant, $index, &$analyticsJobs, &$exportJobs, &$exportIds): void {
                Item::query()->orderBy('id')->limit(50)->pluck('id')->each(function (int $itemId) use ($tenant, &$analyticsJobs): void {
                    RecalculateItemAnalyticsJob::dispatch((int) $tenant->id, $itemId, 'f9a_queue_profile');
                    $analyticsJobs++;
                });

                if ($index < 5) {
                    $owner = User::query()->where('role', 'owner')->firstOrFail();
                    $export = ReportExport::create([
                        'user_id' => $owner->id,
                        'report_type' => 'stock',
                        'format' => 'xlsx',
                        'status' => 'queued',
                        'progress' => 0,
                        'filters' => [],
                    ]);
                    GenerateReportExport::dispatch($export->id, (int) $tenant->id);
                    $exportIds[] = (int) $export->id;
                    $exportJobs++;
                }
            });
        }
        TenantContext::clear();

        $timeout = max(1, min(900, (int) $this->option('timeout')));
        $deadline = microtime(true) + $timeout;
        do {
            $analyticsDepth = Queue::connection('redis')->size('analytics');
            $exportDepth = Queue::connection('redis')->size('exports');
            $settledExports = ReportExport::withoutGlobalScopes()
                ->whereIn('id', $exportIds)
                ->whereIn('status', ['completed', 'failed'])
                ->count();
            if ($analyticsDepth === 0 && $exportDepth === 0 && $settledExports === $exportJobs) {
                break;
            }
            usleep(250_000);
        } while (microtime(true) < $deadline);

        $failed = DB::table('failed_jobs')->whereIn('queue', ['analytics', 'exports'])->count();
        $completedExportModels = ReportExport::withoutGlobalScopes()
            ->whereIn('id', $exportIds)
            ->where('status', 'completed')
            ->get();
        $missingExportFiles = $completedExportModels->filter(
            fn (ReportExport $export): bool => ! $export->path || ! Storage::disk('local')->exists($export->path),
        )->count();
        $crossTenantOutputs = $completedExportModels->filter(
            fn (ReportExport $export): bool => ! $this->exportIsTenantIsolated($export),
        )->count();
        $finishedAt = now();
        $result = [
            'database' => $database,
            'redis_prefix' => $redisPrefix,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => $finishedAt->toIso8601String(),
            'duration_seconds' => $startedAt->diffInMilliseconds($finishedAt) / 1000,
            'analytics_jobs' => $analyticsJobs,
            'export_jobs' => $exportJobs,
            'analytics_queue_depth' => $analyticsDepth,
            'export_queue_depth' => $exportDepth,
            'completed_exports' => $completedExportModels->count(),
            'missing_export_files' => $missingExportFiles,
            'cross_tenant_outputs' => $crossTenantOutputs,
            'failed_jobs' => $failed,
            'passed' => $analyticsDepth === 0
                && $exportDepth === 0
                && $failed === 0
                && $completedExportModels->count() === $exportJobs
                && $missingExportFiles === 0
                && $crossTenantOutputs === 0,
        ];

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
        $path = (string) ($this->option('output') ?: storage_path('framework/testing/f9a-queue-profile.json'));

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    private function exportIsTenantIsolated(ReportExport $export): bool
    {
        $userTenantId = DB::table('users')->where('id', $export->user_id)->value('tenant_id');
        $tenantSlug = DB::table('tenants')->where('id', $export->tenant_id)->value('slug');

        if ((int) $userTenantId !== (int) $export->tenant_id
            || ! is_string($tenantSlug)
            || ! str_starts_with((string) $export->path, "report-exports/{$export->tenant_id}/")) {
            return false;
        }

        try {
            $tenantNumber = (int) str($tenantSlug)->afterLast('-')->toString();
            $sheet = IOFactory::load(Storage::disk('local')->path($export->path))->getActiveSheet();
            $expectedPrefix = sprintf('F9A-%02d-', $tenantNumber);

            foreach ($sheet->rangeToArray('A2:A'.$sheet->getHighestDataRow()) as [$code]) {
                if ($code !== null && ! str_starts_with((string) $code, $expectedPrefix)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
