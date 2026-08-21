<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Category;
use App\Models\Rack;
use App\Models\ShoppingListItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use App\Support\OwnershipGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DeleteMasterDataAction
{
    private const ALLOWED = [Category::class, Rack::class, Supplier::class];

    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(string $modelClass, int $id, User $actor, ?AuditContext $context = null): void
    {
        if (! in_array($modelClass, self::ALLOWED, true)) {
            throw new InvalidArgumentException('Unsupported master model.');
        }
        OwnerActorGuard::assert($actor);
        OwnershipGuard::validate($modelClass, $id);

        DB::transaction(function () use ($modelClass, $id, $actor, $context): void {
            $model = $modelClass::whereKey($id)->lockForUpdate()->firstOrFail();
            $inUse = match ($modelClass) {
                Category::class => $model->items()->withTrashed()->exists(),
                Rack::class => $model->items()->withTrashed()->exists()
                    || StockOpname::where('rack_id', $model->getKey())->exists(),
                Supplier::class => $model->itemSupplierLinks()->exists()
                    || StockMovement::where('supplier_id', $model->getKey())->exists()
                    || ShoppingListItem::where('supplier_id', $model->getKey())->exists(),
            };

            if ($inUse) {
                throw ValidationException::withMessages(['record' => ['Master data masih direferensikan dan tidak dapat dihapus.']]);
            }

            $old = $model->toArray();
            $this->audit->execute('master.deleted', $actor, $model, oldValues: $old, context: $context);
            $model->delete();
        });
    }
}
