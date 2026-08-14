<?php

namespace App\Actions\Reports;

use App\Actions\Audit\RecordAuditAction;
use App\Jobs\GenerateReportExport;
use App\Models\Category;
use App\Models\Item;
use App\Models\ReportExport;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Validation\ValidationException;

class QueueReportExportAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(string $type, string $format, array $filters, User $actor, ?AuditContext $context = null): ReportExport
    {
        OwnershipGuard::validate(User::class, $actor->getKey());

        if (! in_array($type, ['stock', 'movement', 'pos'], true) || ! in_array($format, ['pdf', 'xlsx'], true)) {
            throw ValidationException::withMessages(['report_type' => ['Tipe atau format export tidak valid.']]);
        }
        if (! empty($filters['item_id'])) {
            OwnershipGuard::validate(Item::class, (int) $filters['item_id']);
        }
        if (! empty($filters['category_id'])) {
            OwnershipGuard::validate(Category::class, (int) $filters['category_id']);
        }

        $export = ReportExport::create([
            'user_id' => $actor->getKey(),
            'report_type' => $type,
            'format' => $format,
            'status' => 'queued',
            'progress' => 0,
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]);

        GenerateReportExport::dispatch($export->getKey(), $export->tenant_id)->afterCommit();
        $this->audit->execute('report_export.queued', $actor, $export, newValues: ['type' => $type, 'format' => $format], context: $context);

        return $export;
    }
}
