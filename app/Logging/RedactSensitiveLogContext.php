<?php

namespace App\Logging;

use App\Support\SensitiveDataRedactor;
use Illuminate\Log\Logger;
use Monolog\LogRecord;

final readonly class RedactSensitiveLogContext
{
    public function __construct(private SensitiveDataRedactor $redactor) {}

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: $this->redactor->redactText($record->message),
            context: $this->redactor->redact($record->context),
            extra: $this->redactor->redact($record->extra),
        ));
    }
}
