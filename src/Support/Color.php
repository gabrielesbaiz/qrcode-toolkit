<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;

/**
 * Hex colour handling.
 *
 * Hex is the only accepted input. Named colours would need a lookup table,
 * rgb() and hsl() would need a parser, and an alpha channel would give two
 * ways to express transparency when the package already has one: a null
 * background. Validating rather than escaping also closes the door on
 * url(#...) reaching a fill attribute.
 */
final class Color
{
    /**
     * Determine whether the value is a three or six digit hex colour.
     */
    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1;
    }

    /**
     * Normalise a hex colour to lowercase six digit form.
     */
    public static function normalize(string $key, mixed $value): string
    {
        if (! self::isHex($value)) {
            throw QrException::invalidColor($key, $value);
        }

        /** @var string $value */
        $hex = strtolower(ltrim($value, '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }

    /**
     * Get the components as integers, 0..255.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalize('color', $hex), '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Get the components as floats, 0..1, for PDF and PostScript.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public static function unit(string $hex): array
    {
        [$r, $g, $b] = self::rgb($hex);

        return [$r / 255, $g / 255, $b / 255];
    }

    /**
     * Get the WCAG relative luminance of a colour.
     */
    public static function luminance(string $hex): float
    {
        $channels = [];

        foreach (self::unit($hex) as $channel) {
            $channels[] = $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Get the WCAG contrast ratio between two colours, 1.0 to 21.0.
     */
    public static function contrast(string $one, string $two): float
    {
        $a = self::luminance($one);
        $b = self::luminance($two);

        $lighter = max($a, $b);
        $darker = min($a, $b);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
