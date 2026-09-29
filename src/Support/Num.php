<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

/**
 * Deterministic number formatting.
 *
 * Every coordinate that reaches an output file goes through here. The reason
 * is negative zero: round(-0.0001, 3) prints "-0" on some platforms and "0"
 * on others, which is enough to break byte equality between two renders of
 * the same options. Normalising it away is what makes the determinism test
 * pass.
 */
final class Num
{
    /**
     * Format a float for output.
     */
    public static function f(float $value, int $decimals = 3): string
    {
        $rounded = round($value, $decimals);

        if ($rounded === 0.0 || $rounded === -0.0) {
            return '0';
        }

        $formatted = number_format($rounded, $decimals, '.', '');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' || $formatted === '' ? '0' : $formatted;
    }

    /**
     * Normalise a float so that two equal values always serialise the same.
     */
    public static function normalize(float $value, int $decimals = 6): float
    {
        return round($value, $decimals);
    }
}
