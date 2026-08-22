<?php

namespace App\Exceptions;

final class SubscriptionCapabilityDeniedException extends ApiProblemException
{
    public function __construct()
    {
        parent::__construct('Fitur tidak tersedia pada status langganan saat ini.', 'SUBSCRIPTION_CAPABILITY_DENIED', 403);
    }
}
