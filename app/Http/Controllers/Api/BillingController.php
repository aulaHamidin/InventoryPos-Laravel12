<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Support\BillingClock;
use App\Support\SubscriptionCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillingController extends Controller
{
    public function subscription(Request $request, SubscriptionCapabilityService $capabilities): JsonResponse
    {
        $actor = $this->owner($request);
        $subscription = $capabilities->current($actor->tenant);

        return $this->success($subscription === null ? [
            'status' => 'missing', 'plan' => null, 'starts_at' => null, 'ends_at' => null,
            'grace_ends_at' => null, 'capabilities' => $capabilities->flags($actor),
        ] : [
            'status' => $subscription->status->value,
            'plan' => [
                'code' => $subscription->plan->code,
                'name' => $subscription->plan->name,
                'interval' => $subscription->plan->billing_interval->value,
            ],
            'starts_at' => $this->timestamp($subscription->starts_at),
            'ends_at' => $this->timestamp($subscription->ends_at),
            'grace_ends_at' => $subscription->status->value === 'past_due' ? $this->timestamp($subscription->ends_at->addDays(7)) : null,
            'capabilities' => $capabilities->flags($actor),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $actor = $this->owner($request);
        $invoices = Invoice::query()->with('targetPlan:id,code,name,billing_interval')->where('tenant_id', $actor->tenant_id)
            ->latest('id')->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return $this->success([
            'items' => collect($invoices->items())->map(fn (Invoice $invoice): array => [
                'number' => $invoice->invoice_number,
                'plan' => ['code' => $invoice->targetPlan->code, 'name' => $invoice->targetPlan->name],
                'amount' => $invoice->amount,
                'due_at' => $this->timestamp($invoice->due_at),
                'status' => $invoice->status->value,
                'paid_at' => $this->timestamp($invoice->paid_at),
            ])->all(),
            'meta' => ['current_page' => $invoices->currentPage(), 'last_page' => $invoices->lastPage(), 'total' => $invoices->total()],
        ]);
    }

    private function owner(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->role === UserRole::Owner && $actor->is_active, 403);

        return $actor;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value === null ? null : BillingClock::business($value->toImmutable())->toIso8601String();
    }
}
