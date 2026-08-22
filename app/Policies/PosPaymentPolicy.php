<?php

namespace App\Policies;

use App\Enums\SubscriptionCapability;
use App\Models\PosPayment;
use App\Models\User;

class PosPaymentPolicy extends TenantOwnerPolicy
{
    public function refund(User $user, PosPayment $model): bool
    {
        return $this->owns($user, $model, SubscriptionCapability::Operate);
    }
}
