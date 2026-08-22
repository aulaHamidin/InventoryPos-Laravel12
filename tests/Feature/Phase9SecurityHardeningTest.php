<?php

use App\Actions\Audit\RecordAuditAction;
use App\Enums\UserRole;
use App\Http\Middleware\AddSecurityHeaders;
use App\Jobs\GenerateReportExport;
use App\Models\AuditLog;
use App\Models\ReportExport;
use App\Models\StockMovement;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\SensitiveDataRedactor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function apiRouteMiddleware(string $method, string $uri): array
{
    $route = collect(Route::getRoutes()->getRoutes())->first(
        fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri,
    );

    return $route?->gatherMiddleware() ?? [];
}

it('locks the contractual API rate limit values and route buckets', function () {
    [$tenant, $owner] = makeTenantUser();
    $staff = makeTenantScopedUser([
        'name' => 'Rate Staff',
        'email' => 'rate-staff@example.test',
        'no_hp' => '087700001234',
        'password' => 'password',
    ], UserRole::Staff);
    [, $otherOwner] = makeTenantUser();
    $request = Request::create('/api/v1/items');
    $request->setUserResolver(fn () => $owner);

    $limits = [
        'api-login' => [5, Request::create('/api/v1/auth/login', 'POST', ['email' => $owner->email])],
        'api-read' => [300, $request],
        'api-write' => [120, $request],
        'api-export' => [10, $request],
    ];

    foreach ($limits as $name => [$attempts, $limitRequest]) {
        $limit = RateLimiter::limiter($name)($limitRequest);
        expect($limit)->toBeInstanceOf(Limit::class)
            ->and($limit->maxAttempts)->toBe($attempts)
            ->and($limit->decaySeconds)->toBe(60);
    }

    $staffRequest = Request::create('/api/v1/items');
    $staffRequest->setUserResolver(fn () => $staff);
    $otherTenantRequest = Request::create('/api/v1/items');
    $otherTenantRequest->setUserResolver(fn () => $otherOwner);
    $readLimiter = RateLimiter::limiter('api-read');
    expect($readLimiter($request)->key)->not->toBe($readLimiter($staffRequest)->key)
        ->and($readLimiter($request)->key)->not->toBe($readLimiter($otherTenantRequest)->key)
        ->and($owner->tenant_id)->toBe($tenant->id);

    $loginLimiter = RateLimiter::limiter('api-login');
    $normalizedLogin = Request::create('/api/v1/auth/login', 'POST', ['email' => '  '.strtoupper($owner->email).'  ']);
    $normalizedLogin->server->set('REMOTE_ADDR', '203.0.113.20');
    $canonicalLogin = Request::create('/api/v1/auth/login', 'POST', ['email' => $owner->email]);
    $canonicalLogin->server->set('REMOTE_ADDR', '203.0.113.20');
    $otherIpLogin = Request::create('/api/v1/auth/login', 'POST', ['email' => $owner->email]);
    $otherIpLogin->server->set('REMOTE_ADDR', '203.0.113.21');
    expect($loginLimiter($normalizedLogin)->key)->toBe($loginLimiter($canonicalLogin)->key)
        ->not->toContain($owner->email)
        ->and($loginLimiter($otherIpLogin)->key)->not->toBe($loginLimiter($canonicalLogin)->key);

    expect(apiRouteMiddleware('POST', 'api/v1/auth/login'))->toContain('throttle:api-login')
        ->and(apiRouteMiddleware('GET', 'api/v1/items'))->toContain('throttle:api-read')
        ->and(apiRouteMiddleware('POST', 'api/v1/pos/checkout'))->toContain('throttle:api-write')
        ->and(apiRouteMiddleware('POST', 'api/v1/reports/exports'))->toContain('throttle:api-export')
        ->and(apiRouteMiddleware('POST', 'api/v1/auth/logout'))->not->toContain('throttle:api-read', 'throttle:api-write', 'throttle:api-export');
});

it('returns canonical non-enumerating login 429 with retry headers', function () {
    [, $owner] = makeTenantUser();
    $payload = ['email' => $owner->email, 'password' => 'incorrect-password', 'device_name' => 'rate-test'];

    foreach (range(1, 5) as $_) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/auth/login', $payload)
            ->assertUnprocessable();
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/v1/auth/login', $payload)
        ->assertStatus(429)
        ->assertExactJson([
            'status' => 'error',
            'message' => 'Terlalu banyak permintaan. Coba lagi nanti.',
            'error_code' => 'RATE_LIMITED',
            'errors' => [],
        ]);

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and($response->headers->get('X-RateLimit-Limit'))->toBe('5')
        ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('0')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store', 'private')
        ->and(AuditLog::where('action', 'auth.login')->count())->toBe(0);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/v1/auth/login', [...$payload, 'email' => 'different@example.test'])
        ->assertUnprocessable();
});

it('throttles mutations before any business side effect', function () {
    [, $owner] = makeTenantUser();
    Sanctum::actingAs($owner);
    Queue::fake();

    $original = RateLimiter::limiter('api-write');
    RateLimiter::for('api-write', function (Request $request) use ($original): Limit {
        $limit = $original($request);
        $limit->maxAttempts = 1;

        return $limit;
    });

    try {
        $payload = ['item_id' => 999999, 'qty' => 1, 'harga_satuan' => '10.00'];
        $this->postJson('/api/v1/stock/movements/in', $payload)->assertNotFound();
        $this->postJson('/api/v1/stock/movements/in', $payload)
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'RATE_LIMITED');

        expect(StockMovement::withoutGlobalScopes()->count())->toBe(0)
            ->and(AuditLog::withoutGlobalScopes()->count())->toBe(0);
        Queue::assertNothingPushed();
    } finally {
        RateLimiter::for('api-write', $original);
    }
});

it('enforces isolated read and export buckets with zero throttled side effect', function () {
    [, $owner] = makeTenantUser();
    [, $otherOwner] = makeTenantUser();

    $originals = [];
    foreach (['api-read', 'api-export'] as $name) {
        $originals[$name] = RateLimiter::limiter($name);
        RateLimiter::for($name, function (Request $request) use ($originals, $name): Limit {
            $original = $originals[$name];
            $limit = $original($request);
            $limit->maxAttempts = 1;

            return $limit;
        });
    }

    try {
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/items')->assertOk();
        $this->getJson('/api/v1/items')
            ->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertJsonPath('error_code', 'RATE_LIMITED');

        Sanctum::actingAs($otherOwner);
        $this->getJson('/api/v1/items')->assertOk();

        Queue::fake();
        $this->postJson('/api/v1/reports/exports', [
            'report_type' => 'stock', 'format' => 'xlsx',
        ])->assertAccepted();
        $exportCount = ReportExport::withoutGlobalScopes()->count();
        $auditCount = AuditLog::withoutGlobalScopes()->count();

        $this->postJson('/api/v1/reports/exports', [
            'report_type' => 'stock', 'format' => 'xlsx',
        ])->assertStatus(429)->assertJsonPath('error_code', 'RATE_LIMITED');

        expect(ReportExport::withoutGlobalScopes()->count())->toBe($exportCount)
            ->and(AuditLog::withoutGlobalScopes()->count())->toBe($auditCount);
        Queue::assertPushed(GenerateReportExport::class, 1);
    } finally {
        foreach ($originals as $name => $limiter) {
            RateLimiter::for($name, $limiter);
        }
    }
});

it('adds transport headers and no-store while keeping HSTS production HTTPS only', function () {
    [, $owner] = makeTenantUser();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/items')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; form-action 'self'")
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
        ->assertHeaderMissing('X-Powered-By')
        ->assertHeaderMissing('Strict-Transport-Security');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.test', 'password' => 'invalid-password',
    ])->assertHeader('Cache-Control', 'no-store, private');
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $this->get('/app/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    Storage::fake('local');
    TenantContext::set($owner->tenant);
    $path = "report-exports/{$owner->tenant_id}/security-header-proof.xlsx";
    Storage::disk('local')->put($path, 'private-proof');
    $export = ReportExport::create([
        'user_id' => $owner->id,
        'report_type' => 'stock',
        'format' => 'xlsx',
        'status' => 'completed',
        'progress' => 100,
        'filters' => [],
        'path' => $path,
        'file_name' => 'security-header-proof.xlsx',
        'completed_at' => now(),
    ]);
    $this->actingAs($owner)
        ->get("/app/reports/exports/{$export->id}/download")
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
    Sanctum::actingAs($owner);
    $this->get("/api/v1/reports/exports/{$export->id}/download")
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn () => 'production');

    try {
        $request = Request::create('https://example.test/api/v1/items', 'GET');
        $response = app(AddSecurityHeaders::class)->handle($request, fn () => response('ok'));
        expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');

        $httpRequest = Request::create('http://example.test/api/v1/items', 'GET');
        $httpResponse = app(AddSecurityHeaders::class)->handle($httpRequest, fn () => response('ok'));
        expect($httpResponse->headers->has('Strict-Transport-Security'))->toBeFalse();
    } finally {
        app()->detectEnvironment(fn () => $originalEnvironment);
    }
});

it('uses explicit CORS allowlists and never serves private disk files directly', function () {
    config()->set('cors.allowed_origins', ['https://client.example.test']);
    expect(config('cors.supports_credentials'))->toBeFalse()
        ->and(config('cors.allowed_origins'))->not->toContain('*');

    $this->withHeaders([
        'Origin' => 'https://client.example.test',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/items')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://client.example.test')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');

    $blocked = $this->withHeaders([
        'Origin' => 'https://blocked.example.test',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/items');
    expect($blocked->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://blocked.example.test');

    Storage::disk('local')->put('security/private-proof.txt', 'private-evidence');
    $this->get('/storage/security/private-proof.txt')->assertNotFound();
    expect(config('filesystems.disks.local.serve'))->toBeFalse();
});

it('redacts audit metadata without preventing the audit transaction', function () {
    [, $owner] = makeTenantUser();

    $audit = app(RecordAuditAction::class)->execute(
        'security.redaction_test',
        $owner,
        $owner,
        oldValues: ['password' => 'old-secret', 'name' => 'Before'],
        newValues: ['Password_Confirmation' => 'new-secret', 'name' => 'After'],
        context: new AuditContext('127.0.0.1', 'Security Test token=agent-secret', ['authorization' => 'Bearer secret']),
        metadata: ['nested' => ['otp' => '123456', 'status' => 'accepted']],
    );

    expect($audit->old_values['password'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($audit->old_values['name'])->toBe('Before')
        ->and($audit->new_values['Password_Confirmation'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($audit->metadata['authorization'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($audit->metadata['nested']['otp'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($audit->metadata['nested']['status'])->toBe('accepted')
        ->and($audit->user_agent)->not->toContain('agent-secret');

    $unstable = app(RecordAuditAction::class)->execute(
        'security.redaction_fail_closed_test',
        $owner,
        $owner,
        metadata: ['unstable' => new class implements JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                throw new RuntimeException('token=must-never-escape');
            }
        }],
    );
    expect($unstable->exists)->toBeTrue()
        ->and(AuditLog::whereIn('action', [
            'security.redaction_test', 'security.redaction_fail_closed_test',
        ])->count())->toBe(2);
});
