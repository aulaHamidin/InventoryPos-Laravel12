<?php

namespace App\Support;

use JsonSerializable;
use Throwable;
use Traversable;

final class SensitiveDataRedactor
{
    public const REDACTED = '[REDACTED]';

    private const MAX_DEPTH = 32;

    public function redact(mixed $value): mixed
    {
        try {
            return $this->redactValue($value);
        } catch (Throwable) {
            return self::REDACTED;
        }
    }

    private function redactValue(mixed $value, string|int|null $key = null, int $depth = 0): mixed
    {
        if ($key !== null && $this->isSensitiveKey((string) $key)) {
            return self::REDACTED;
        }

        if ($depth >= self::MAX_DEPTH) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redactValue($childValue, $childKey, $depth + 1);
            }

            return $redacted;
        }

        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $this->redactText($value->getMessage()),
                'code' => $value->getCode(),
            ];
        }

        if ($value instanceof JsonSerializable) {
            return $this->redactValue($value->jsonSerialize(), depth: $depth + 1);
        }

        if ($value instanceof Traversable) {
            return $this->redactValue(iterator_to_array($value), depth: $depth + 1);
        }

        if (is_object($value)) {
            return $this->redactValue(get_object_vars($value), depth: $depth + 1);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $key));

        foreach (['password', 'confirmation', 'token', 'authorization', 'cookie', 'csrf', 'api_key', 'apikey', 'secret', 'otp', 'signature'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function redactText(string $text): string
    {
        $patterns = [
            '/\b(Bearer\s+)[A-Za-z0-9._~+\/-]+=*/i',
            '/\b(password|confirmation|token|authorization|cookie|csrf|api[_ -]?key|secret|otp|signature)\s*[:=]\s*[^\s,;]+/i',
        ];

        return (string) preg_replace($patterns, [
            '$1'.self::REDACTED,
            '$1='.self::REDACTED,
        ], $text);
    }
}
