<?php

namespace App\Actions\Opname;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ApiProblemException;
use App\Models\Item;
use App\Models\Rack;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOpnameAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(
        string|StockOpnameScope $scope,
        User $actor,
        ?int $rackId = null,
        ?AuditContext $context = null,
    ): StockOpname {
        $scope = $scope instanceof StockOpnameScope ? $scope : StockOpnameScope::tryFrom($scope);
        if ($scope === null) {
            throw ValidationException::withMessages(['scope_type' => ['Scope opname tidak valid.']]);
        }
        if ($scope === StockOpnameScope::Partial && $rackId === null) {
            throw ValidationException::withMessages(['rack_id' => ['Rak wajib dipilih untuk opname partial.']]);
        }
        if ($scope === StockOpnameScope::Full && $rackId !== null) {
            throw ValidationException::withMessages(['rack_id' => ['Rak tidak boleh dikirim untuk opname full.']]);
        }

        OwnerActorGuard::assert($actor);
        if ($rackId !== null) {
            OwnershipGuard::validate(Rack::class, $rackId);
        }

        return DB::transaction(function () use ($scope, $actor, $rackId, $context): StockOpname {
            Tenant::whereKey(TenantContext::id())->lockForUpdate()->firstOrFail();

            if ($rackId !== null) {
                Rack::whereKey($rackId)->lockForUpdate()->firstOrFail();
            }

            $drafts = StockOpname::where('status', StockOpnameStatus::Draft)
                ->lockForUpdate()
                ->get(['scope_type', 'rack_id']);
            $conflict = $drafts->contains(function (StockOpname $draft) use ($scope, $rackId): bool {
                return $scope === StockOpnameScope::Full
                    || $draft->scope_type === StockOpnameScope::Full
                    || (int) $draft->rack_id === (int) $rackId;
            });

            if ($conflict) {
                throw new ApiProblemException(
                    'Scope opname berkonflik dengan sesi aktif.',
                    'OPNAME_SCOPE_CONFLICT',
                    409,
                );
            }

            $items = Item::where('is_active', true)
                ->when($scope === StockOpnameScope::Partial, fn ($query) => $query->where('rack_id', $rackId))
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['scope_type' => ['Tidak ada item aktif dalam scope opname.']]);
            }

            $opname = StockOpname::create([
                'created_by' => $actor->getKey(),
                'scope_type' => $scope,
                'rack_id' => $rackId,
                'status' => StockOpnameStatus::Draft,
                'started_at' => now(),
            ]);

            $now = now();
            $items->pluck('id')->chunk(500)->each(function ($ids) use ($opname, $now): void {
                StockOpnameDetail::insert($ids->map(fn (int $itemId): array => [
                    'tenant_id' => TenantContext::id(),
                    'stock_opname_id' => $opname->getKey(),
                    'item_id' => $itemId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

            $this->audit->execute(
                'stock_opname.created',
                $actor,
                $opname,
                newValues: ['scope_type' => $scope->value, 'rack_id' => $rackId, 'item_count' => $items->count()],
                context: $context,
            );

            return $opname->load(['rack', 'creator'])->loadCount('details');
        }, 3);
    }
}
