<?php

namespace App\Http\Controllers\Api;

use App\Actions\Deletion\CancelTenantDeletionAction;
use App\Actions\Deletion\RequestTenantDeletionAction;
use App\Enums\TenantDeletionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\TenantDeletionRequest;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\BillingClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TenantDeletionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $actor = $this->owner($request);
        $deletion = TenantDeletionRequest::query()->where('tenant_id', $actor->tenant_id)->latest('id')->first();

        return $this->success($deletion ? $this->projection($deletion) : null);
    }

    public function store(Request $request, RequestTenantDeletionAction $action): JsonResponse
    {
        $actor = $this->owner($request);
        $this->strict($request, ['reason']);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $deletion = $action->execute($actor, $data['reason'], AuditContext::fromRequest($request));
        } catch (ConflictHttpException $exception) {
            throw new ApiProblemException('Permintaan penghapusan aktif sudah ada.', 'DELETION_REQUEST_EXISTS', 409);
        }

        return $this->success($this->projection($deletion), 'Permintaan penghapusan dibuat.', 201);
    }

    public function cancel(Request $request, CancelTenantDeletionAction $action): JsonResponse
    {
        $actor = $this->owner($request);
        $this->strict($request, []);
        $deletion = TenantDeletionRequest::query()->where('tenant_id', $actor->tenant_id)->where('status', TenantDeletionStatus::Requested)->latest('id')->firstOrFail();
        $deletion = $action->execute($actor, $deletion, AuditContext::fromRequest($request));

        return $this->success($this->projection($deletion), 'Permintaan penghapusan dibatalkan.');
    }

    private function owner(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->role === UserRole::Owner && $actor->is_active, 403);

        return $actor;
    }

    private function strict(Request $request, array $allowed): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), $allowed));
        if ($unexpected !== []) {
            throw ValidationException::withMessages(['request' => ['Field tidak diizinkan: '.implode(', ', $unexpected).'.']]);
        }
    }

    private function projection(TenantDeletionRequest $deletion): array
    {
        return [
            'id' => $deletion->getKey(),
            'status' => $deletion->status->value,
            'reason' => $deletion->reason,
            'requested_at' => BillingClock::business($deletion->created_at->toImmutable())->toIso8601String(),
            'purge_after' => $deletion->purge_after ? BillingClock::business($deletion->purge_after)->toIso8601String() : null,
            'can_cancel' => $deletion->status === TenantDeletionStatus::Requested,
        ];
    }
}
