<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ImpersonationContext;
use Illuminate\Database\Eloquent\Model;

class ReportExportPolicy extends TenantOwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return ! ImpersonationContext::isSupport() && parent::viewAny($user);
    }

    public function view(User $user, Model $model): bool
    {
        return ! ImpersonationContext::isSupport() && parent::view($user, $model);
    }
}
