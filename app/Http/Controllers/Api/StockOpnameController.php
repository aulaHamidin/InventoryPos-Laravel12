<?php

namespace App\Http\Controllers\Api;

use App\Actions\Opname\CreateOpnameAction;
use App\Actions\Opname\FinalizeOpnameAction;
use App\Actions\Opname\SaveOpnameCountAction;
use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockOpname::class);
        $paginator = StockOpname::query()
            ->with(['rack:id,kode,nama', 'creator:id,name'])
            ->withCount([
                'details',
                'details as counted_details_count' => fn ($query) => $query->whereNotNull('counted_at'),
            ])
            ->orderByDesc('started_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));
        $paginator->through(fn (StockOpname $opname): array => $this->serializeOpname($opname));

        return $this->success($paginator);
    }

    public function store(Request $request, CreateOpnameAction $action): JsonResponse
    {
        $this->authorize('create', StockOpname::class);
        $data = $request->validate([
            'scope_type' => ['required', 'in:partial,full'],
            'rack_id' => ['required_if:scope_type,partial', 'prohibited_if:scope_type,full', 'nullable', 'integer'],
        ]);
        $opname = $action->execute(
            $data['scope_type'],
            $request->user(),
            isset($data['rack_id']) ? (int) $data['rack_id'] : null,
            AuditContext::fromRequest($request),
        );

        return $this->success($this->serializeOpname($opname), 'Sesi stock opname dibuat.', 201);
    }

    public function updateDetails(Request $request, int $id, SaveOpnameCountAction $action): JsonResponse
    {
        $opname = OwnershipGuard::validate(StockOpname::class, $id);
        $this->authorize('update', $opname);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty_fisik' => ['required', 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ]);
        $details = $action->execute($id, $data['items'], $request->user(), AuditContext::fromRequest($request));

        return $this->success(
            $details->map(fn (StockOpnameDetail $detail): array => $this->serializeDetail($detail))->values(),
            'Hitungan opname disimpan.',
        );
    }

    public function finalize(Request $request, int $id, FinalizeOpnameAction $action): JsonResponse
    {
        $opname = OwnershipGuard::validate(StockOpname::class, $id);
        $this->authorize('update', $opname);
        $result = $action->execute($id, $request->user(), AuditContext::fromRequest($request));
        $result['opname']->loadCount([
            'details',
            'details as counted_details_count' => fn ($query) => $query->whereNotNull('counted_at'),
        ]);

        return $this->success([
            'opname' => $this->serializeOpname($result['opname']),
            'summary' => $result['summary'],
        ], 'Stock opname berhasil difinalisasi.');
    }

    private function serializeOpname(StockOpname $opname): array
    {
        return [
            'id' => $opname->getKey(),
            'scope_type' => $opname->scope_type->value,
            'rack' => $opname->rack ? [
                'id' => $opname->rack->getKey(),
                'kode' => $opname->rack->kode,
                'nama' => $opname->rack->nama,
            ] : null,
            'status' => $opname->status->value,
            'creator' => $opname->creator ? ['id' => $opname->creator->getKey(), 'name' => $opname->creator->name] : null,
            'started_at' => $opname->started_at?->toISOString(),
            'completed_at' => $opname->completed_at?->toISOString(),
            'progress' => [
                'counted' => (int) ($opname->counted_details_count ?? 0),
                'total' => (int) ($opname->details_count ?? 0),
            ],
        ];
    }

    private function serializeDetail(StockOpnameDetail $detail): array
    {
        return [
            'item_id' => $detail->item_id,
            'qty_sistem_at_count' => $detail->qty_sistem_at_count,
            'qty_fisik' => $detail->qty_fisik,
            'counted_at' => $detail->counted_at?->toISOString(),
            'note' => $detail->note,
        ];
    }
}
