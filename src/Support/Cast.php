<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use BackedEnum;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;

/**
 * Coercion from the loose values a fluent API accepts.
 *
 * Every setter takes an enum or a string. Funnelling both through one helper
 * is what keeps the error message identical whichever door the value came
 * in by.
 */
final class Cast
{
    /**
     * Coerce a value into a backed enum.
     *
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    public static function enum(string $enum, mixed $value, string $key): BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        if (is_string($value)) {
            $candidate = trim($value);

            /** @var T|null $case */
            $case = $enum::tryFrom($candidate) ?? $enum::tryFrom(strtolower($candidate)) ?? $enum::tryFrom(strtoupper($candidate));

            if ($case !== null) {
                return $case;
            }
        }

        throw QrException::invalidOption($key, $value, array_map(
            static fn (BackedEnum $case): string|int => $case->value,
            $enum::cases(),
        ));
    }

    /**
     * Coerce a value into an integer within a range.
     */
    public static function int(mixed $value, string $key, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            throw QrException::invalidOption($key, $value, ['an integer']);
        }

        $int = (int) $value;

        if ($int < $min || $int > $max) {
            throw QrException::outOfRange($key, $value, $min, $max);
        }

        return $int;
    }

    /**
     * Coerce a value into a float within a range.
     */
    public static function float(mixed $value, string $key, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            throw QrException::invalidOption($key, $value, ['a number']);
        }

        $float = (float) $value;

        if ($float < $min || $float > $max) {
            throw QrException::outOfRange($key, $value, $min, $max);
        }

        return Num::normalize($float);
    }

    /**
     * Coerce a value into a boolean.
     */
    public static function bool(mixed $value, string $key): bool
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($bool === null) {
            throw QrException::invalidOption($key, $value, ['true', 'false']);
        }

        return $bool;
    }
}
