<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Category;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateMasterDataAction
{
    private const ALLOWED = [Category::class, Rack::class, Supplier::class];

    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(string $modelClass, int $id, array $data, User $actor, ?AuditContext $context = null): Model
    {
        if (! in_array($modelClass, self::ALLOWED, true)) {
            throw new InvalidArgumentException('Unsupported master model.');
        }
        OwnershipGuard::validate(User::class, $actor->getKey());
        OwnershipGuard::validate($modelClass, $id);

        return DB::transaction(function () use ($modelClass, $id, $data, $actor, $context): Model {
            $model = $modelClass::whereKey($id)->lockForUpdate()->firstOrFail();
            $old = $model->toArray();
            $model->update($data);
            $this->audit->execute('master.updated', $actor, $model, oldValues: $old, newValues: $model->fresh()->toArray(), context: $context);

            return $model;
        });
    }
}
