<?php

namespace App\Support;

use InvalidArgumentException;

final class Decimal
{
    public const SCALE = 2;

    public static function money(int|float|string $value): string
    {
        $value = is_float($value) ? number_format($value, self::SCALE, '.', '') : (string) $value;

        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException('Invalid non-negative decimal amount.');
        }

        return bcadd($value, '0', self::SCALE);
    }

    public static function add(string $left, string $right): string
    {
        return bcadd($left, $right, self::SCALE);
    }

    public static function sub(string $left, string $right): string
    {
        return bcsub($left, $right, self::SCALE);
    }

    public static function mul(string $left, int|string $right): string
    {
        return bcmul($left, (string) $right, self::SCALE);
    }

    public static function div(string $left, int|string $right): string
    {
        return bcdiv($left, (string) $right, self::SCALE);
    }

    public static function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }

    public static function formatIdr(string $value): string
    {
        [$whole, $fraction] = explode('.', self::money($value));
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?? $whole;

        return 'Rp '.$whole.','.$fraction;
    }
}
