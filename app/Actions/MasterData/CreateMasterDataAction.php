<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditAction;
use App\Models\Category;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\OwnerActorGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateMasterDataAction
{
    private const ALLOWED = [Category::class, Rack::class, Supplier::class];

    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(string $modelClass, array $data, User $actor, ?AuditContext $context = null): Model
    {
        if (! in_array($modelClass, self::ALLOWED, true)) {
            throw new InvalidArgumentException('Unsupported master model.');
        }
        OwnerActorGuard::assert($actor);

        return DB::transaction(function () use ($modelClass, $data, $actor, $context): Model {
            $model = $modelClass::create($data);
            $this->audit->execute('master.created', $actor, $model, newValues: $model->toArray(), context: $context);

            return $model;
        });
    }
}
