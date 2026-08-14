<?php

use App\Actions\Reports\QueueReportExportAction;
use App\Enums\UserRole;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\ReportExportReady;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('queues tenant scoped exports instead of streaming from controller', function () {
    [, $owner] = makeTenantUser();
    makeInventoryItem();
    Queue::fake();

    $export = app(QueueReportExportAction::class)->execute('movement', 'xlsx', [
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->toDateString(),
        'movement_type' => 'stock_in',
    ], $owner);

    expect($export->status)->toBe('queued')->and($export->progress)->toBe(0);
    Queue::assertPushed(GenerateReportExport::class, fn ($job) => $job->exportId === $export->id);
});

it('writes completed report to private storage and notifies the owner', function () {
    [$tenant, $owner] = makeTenantUser();
    makeInventoryItem();
    Storage::fake('local');
    Notification::fake();

    $export = ReportExport::create([
        'user_id' => $owner->id,
        'report_type' => 'stock',
        'format' => 'xlsx',
        'status' => 'queued',
        'progress' => 0,
        'filters' => [],
    ]);

    (new GenerateReportExport($export->id, $tenant->id))->handle();
    $export->refresh();

    expect($export->status)->toBe('completed')
        ->and($export->progress)->toBe(100)
        ->and($export->path)->not->toBeNull();
    Storage::disk('local')->assertExists($export->path);
    Notification::assertSentTo($owner, ReportExportReady::class);
});

it('shows export progress to Owner and protects private files by tenant and role', function () {
    [$tenantA, $ownerA] = makeTenantUser();
    $staffA = User::create([
        'name' => 'Report Staff',
        'email' => 'report-staff@example.test',
        'no_hp' => '084444444444',
        'password' => 'password',
        'role' => UserRole::Staff,
    ]);
    Storage::fake('local');

    $path = "report-exports/{$tenantA->id}/stock-a.xlsx";
    Storage::disk('local')->put($path, 'private-report');
    $exportA = ReportExport::create([
        'user_id' => $ownerA->id,
        'report_type' => 'stock',
        'format' => 'xlsx',
        'status' => 'completed',
        'progress' => 100,
        'filters' => [],
        'path' => $path,
        'file_name' => 'stock-a.xlsx',
        'completed_at' => now(),
    ]);

    $this->actingAs($ownerA)->get('/admin/report-exports')->assertOk();
    $this->actingAs($ownerA)
        ->get("/reports/exports/{$exportA->id}/download")
        ->assertOk()
        ->assertDownload('stock-a.xlsx');

    [, $ownerB] = makeTenantUser();
    $this->actingAs($ownerB)
        ->get("/reports/exports/{$exportA->id}/download")
        ->assertNotFound();

    TenantContext::set($tenantA);
    $this->actingAs($staffA)
        ->get("/reports/exports/{$exportA->id}/download")
        ->assertForbidden();
});

it('exposes print actions on every tenant report surface', function () {
    [, $owner] = makeTenantUser();
    makeInventoryItem();

    $this->actingAs($owner)->get('/admin/items')
        ->assertOk()
        ->assertSee('window.print()', false);
    $this->actingAs($owner)->get('/admin/stock-movements')
        ->assertOk()
        ->assertSee('window.print()', false);
    $this->actingAs($owner)->get('/admin/pos-transactions')
        ->assertOk()
        ->assertSee('window.print()', false);
});
