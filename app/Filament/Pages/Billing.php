<?php

namespace App\Filament\Pages;

use App\Actions\Deletion\CancelTenantDeletionAction;
use App\Actions\Deletion\RequestTenantDeletionAction;
use App\Enums\TenantDeletionStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\TenantDeletionRequest;
use App\Support\AuditContext;
use App\Support\SubscriptionCapabilityService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class Billing extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Billing';

    protected static string $view = 'filament.pages.billing';

    public string $deletionReason = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Owner;
    }

    public function requestDeletion(): void
    {
        $this->validate(['deletionReason' => ['required', 'string', 'min:10', 'max:1000']]);
        app(RequestTenantDeletionAction::class)->execute(auth()->user(), $this->deletionReason, AuditContext::fromRequest(request()));
        $this->reset('deletionReason');
        Notification::make()->title('Permintaan penghapusan dibuat')->warning()->send();
    }

    public function cancelDeletion(): void
    {
        $deletion = TenantDeletionRequest::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('status', TenantDeletionStatus::Requested)
            ->latest('id')
            ->firstOrFail();
        app(CancelTenantDeletionAction::class)->execute(auth()->user(), $deletion, AuditContext::fromRequest(request()));
        Notification::make()->title('Permintaan penghapusan dibatalkan')->success()->send();
    }

    protected function getViewData(): array
    {
        $actor = auth()->user();
        $subscription = app(SubscriptionCapabilityService::class)->current($actor->tenant);

        return [
            'subscription' => $subscription,
            'capabilities' => app(SubscriptionCapabilityService::class)->flags($actor),
            'invoices' => Invoice::query()->where('tenant_id', $actor->tenant_id)->with('targetPlan')->latest('id')->limit(20)->get(),
            'deletion' => TenantDeletionRequest::query()->where('tenant_id', $actor->tenant_id)->latest('id')->first(),
        ];
    }
}
