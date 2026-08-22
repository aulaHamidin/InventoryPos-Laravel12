<?php

use App\Logging\RedactSensitiveLogContext;
use App\Support\SensitiveDataRedactor;
use Illuminate\Events\Dispatcher;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

it('recursively redacts sensitive array object and exception values', function () {
    $payload = [
        'email' => 'safe@example.test',
        'Password-Confirmation' => 'TopSecret12',
        'confirmation' => 'standalone-secret',
        'headers' => [
            'Authorization' => 'Bearer api-token-value',
            'X-CSRF-TOKEN' => 'csrf-value',
        ],
        'object' => (object) [
            'otp' => '123456',
            'label' => 'kept',
        ],
        'exception' => new RuntimeException('token=raw-token-value failed'),
    ];

    $redacted = app(SensitiveDataRedactor::class)->redact($payload);

    expect($redacted['email'])->toBe('safe@example.test')
        ->and($redacted['Password-Confirmation'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($redacted['confirmation'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($redacted['headers']['Authorization'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($redacted['headers']['X-CSRF-TOKEN'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($redacted['object']['otp'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($redacted['object']['label'])->toBe('kept')
        ->and($redacted['exception']['message'])->not->toContain('raw-token-value');
});

it('redacts structured log context before a handler receives it', function () {
    $handler = new TestHandler;
    $monolog = new Logger('security-test', [$handler]);
    $logger = new IlluminateLogger($monolog, new Dispatcher);
    (new RedactSensitiveLogContext(new SensitiveDataRedactor))($logger);

    $logger->info('Authorization: Bearer raw-message-token password=raw-password', [
        'nested' => ['api_key' => 'live-key', 'status' => 'ok'],
        'cookie' => 'session-secret',
    ]);

    $record = $handler->getRecords()[0];

    expect($record->context['nested']['api_key'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($record->context['nested']['status'])->toBe('ok')
        ->and($record->context['cookie'])->toBe(SensitiveDataRedactor::REDACTED)
        ->and($record->message)->not->toContain('raw-message-token', 'raw-password')
        ->and($record->message)->toContain(SensitiveDataRedactor::REDACTED);
});

it('fails closed for cyclic input without throwing into a business transaction', function () {
    $cyclic = ['name' => 'kept'];
    $cyclic['self'] = &$cyclic;

    $redacted = app(SensitiveDataRedactor::class)->redact($cyclic);

    expect($redacted['name'])->toBe('kept')
        ->and(json_encode($redacted, JSON_THROW_ON_ERROR))->toContain(SensitiveDataRedactor::REDACTED);
});
