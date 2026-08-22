<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionCapability;
use App\Exceptions\SubscriptionCapabilityDeniedException;
use App\Models\User;
use App\Support\SubscriptionCapabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireSubscriptionCapability
{
    public function __construct(private readonly SubscriptionCapabilityService $capabilities) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $actor = $request->user();
        $resolved = SubscriptionCapability::tryFrom($capability);
        if (! $actor instanceof User || $resolved === null || ! $this->capabilities->allows($actor, $resolved)) {
            if (! $request->is('api/*')) {
                abort(403, 'Fitur tidak tersedia pada status langganan saat ini.');
            }
            throw new SubscriptionCapabilityDeniedException;
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $actor = $request->user();
        if ($actor instanceof User) {
            $this->capabilities->forget((int) $actor->tenant_id);
        }
    }
}
