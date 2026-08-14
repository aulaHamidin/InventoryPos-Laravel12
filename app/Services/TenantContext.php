<?php

namespace App\Services;

use App\Models\Tenant;
use RuntimeException;

/**
 * TenantContext — static accessor for the current tenant.
 *
 * Contract rule: Tenant context is resolved from the authenticated user's
 * tenant_id. It NEVER comes from client request parameters.
 *
 * This service is set by the SetTenantContext middleware and can be
 * accessed anywhere in the application layer.
 */
class TenantContext
{
    protected static ?Tenant $tenant = null;

    /**
     * Set the current tenant context.
     */
    public static function set(Tenant $tenant): void
    {
        static::$tenant = $tenant;
    }

    /**
     * Get the current tenant.
     *
     * @throws RuntimeException if no tenant context is set
     */
    public static function get(): Tenant
    {
        if (! static::$tenant) {
            throw new RuntimeException('No tenant context has been set.');
        }

        return static::$tenant;
    }

    /**
     * Get the current tenant ID.
     *
     * @throws RuntimeException if no tenant context is set
     */
    public static function id(): int
    {
        return static::get()->id;
    }

    /**
     * Check if a tenant context is currently set.
     */
    public static function hasTenant(): bool
    {
        return static::$tenant !== null;
    }

    /**
     * Clear the tenant context.
     * Used primarily in testing and queue workers.
     */
    public static function clear(): void
    {
        static::$tenant = null;
    }

    /**
     * Execute a callback within a specific tenant context.
     * Useful for queue jobs, commands, and testing.
     */
    public static function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = static::$tenant;

        static::set($tenant);

        try {
            return $callback();
        } finally {
            static::$tenant = $previous;
        }
    }
}
