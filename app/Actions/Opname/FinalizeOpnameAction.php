<?php

namespace App\Actions\Opname;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;

class FinalizeOpnameAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $opnameId, User $actor, ?AuditContext $context = null): array
    {
        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate(StockOpname::class, $opnameId);

        return DB::transaction(function () use ($opnameId, $actor, $context): array {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if ($opname->status !== StockOpnameStatus::Draft) {
                throw new ApiProblemException('Opname sudah selesai.', 'INVALID_STATE_TRANSITION', 409);
            }

            $details = StockOpnameDetail::where('stock_opname_id', $opnameId)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get();
            $missing = $details->filter(fn (StockOpnameDetail $detail): bool => $detail->counted_at === null);
            if ($details->isEmpty() || $missing->isNotEmpty()) {
                throw new ApiProblemException(
                    'Semua detail wajib dihitung sebelum finalisasi.',
                    'OPNAME_INCOMPLETE',
                    422,
                    ['uncounted_item_ids' => $missing->pluck('item_id')->values()->all()],
                );
            }

            $itemIds = $details->pluck('item_id')->sort()->values();
            $items = Item::withTrashed()->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($items->count() !== $details->count()) {
                throw new ApiProblemException('Item opname tidak lengkap.', 'OPNAME_INCOMPLETE', 422);
            }

            $changes = [];
            foreach ($details as $detail) {
                $item = $items->get($detail->item_id);
                $delta = $detail->qty_fisik - $detail->qty_sistem_at_count;
                $after = $item->stok_saat_ini + $delta;
                if ($after < 0 && ! TenantContext::get()->allow_negative_stock) {
                    throw new ApiProblemException(
                        "Penyesuaian opname akan membuat stok {$item->nama} negatif.",
                        'INSUFFICIENT_STOCK',
                        422,
                        ['item_id' => $item->getKey()],
                    );
                }
                $changes[$item->getKey()] = compact('item', 'detail', 'delta', 'after');
            }

            $adjusted = 0;
            $unchanged = 0;
            $totalIn = 0;
            $totalOut = 0;
            foreach ($changes as ['item' => $item, 'detail' => $detail, 'delta' => $delta, 'after' => $after]) {
                if ($delta === 0) {
                    $unchanged++;

                    continue;
                }

                $direction = $delta > 0 ? 'in' : 'out';
                $qty = abs($delta);
                StockMovement::create([
                    'item_id' => $item->getKey(),
                    'user_id' => $actor->getKey(),
                    'movement_type' => 'opname_adjustment',
                    'qty' => $qty,
                    'direction' => $direction,
                    'harga_satuan' => $item->average_cost,
                    'reference_type' => StockOpname::class,
                    'reference_id' => $opname->getKey(),
                    'note' => $detail->note ?: "Finalisasi opname #{$opname->getKey()}",
                ]);
                $item->update(['stok_saat_ini' => $after]);
                $adjusted++;
                $direction === 'in' ? $totalIn += $qty : $totalOut += $qty;
            }

            $opname->update(['status' => StockOpnameStatus::Completed, 'completed_at' => now()]);
            $summary = [
                'item_count' => $details->count(),
                'adjusted_lines' => $adjusted,
                'unchanged_lines' => $unchanged,
                'total_units_in' => $totalIn,
                'total_units_out' => $totalOut,
            ];
            $this->audit->execute(
                'stock_opname.completed',
                $actor,
                $opname,
                newValues: ['status' => StockOpnameStatus::Completed->value, 'completed_at' => $opname->completed_at],
                context: $context,
                metadata: $summary,
            );

            return ['opname' => $opname->fresh(['rack', 'creator']), 'summary' => $summary];
        }, 3);
    }
}
