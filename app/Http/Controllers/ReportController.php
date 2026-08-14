<?php

namespace App\Http\Controllers;

use App\Actions\Reports\QueueReportExportAction;
use App\Models\ReportExport;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function queue(Request $request, QueueReportExportAction $action): JsonResponse
    {
        $this->authorize('create', ReportExport::class);
        $data = $request->validate([
            'report_type' => ['required', 'in:stock,movement,pos'],
            'format' => ['required', 'in:pdf,xlsx'],
            'filters' => ['sometimes', 'array'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date', 'after_or_equal:filters.date_from'],
            'filters.item_id' => ['nullable', 'integer'],
            'filters.category_id' => ['nullable', 'integer'],
            'filters.movement_type' => ['nullable', 'string'],
            'filters.status' => ['nullable', 'string'],
            'filters.low_stock' => ['nullable', 'boolean'],
        ]);

        return $this->success(
            $action->execute($data['report_type'], $data['format'], $data['filters'] ?? [], $request->user(), AuditContext::fromRequest($request)),
            'Export masuk antrean.',
            202,
        );
    }

    public function status(Request $request, int $export): JsonResponse
    {
        $model = OwnershipGuard::validate(ReportExport::class, $export);
        $this->authorize('view', $model);

        return $this->success($model);
    }

    public function download(Request $request, int $export): StreamedResponse
    {
        $model = OwnershipGuard::validate(ReportExport::class, $export);
        $this->authorize('view', $model);
        abort_unless($model->status === 'completed' && $model->path && Storage::disk('local')->exists($model->path), 404);

        return Storage::disk('local')->download($model->path, $model->file_name);
    }
}
