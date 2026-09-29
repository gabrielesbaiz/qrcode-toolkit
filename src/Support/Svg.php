<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

/**
 * Escaping for SVG output.
 *
 * ENT_XML1 matters: the ENT_HTML401 default emits &apos; wrongly for XML.
 * ENT_SUBSTITUTE matters more: without it, one invalid UTF-8 byte out of a
 * database turns the whole label into an empty string, and a silently blank
 * label is a bug nobody notices until it is printed.
 */
final class Svg
{
    /**
     * Escape a value for a text node.
     */
    public static function escapeText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape a value for an attribute.
     */
    public static function escapeAttr(string $value): string
    {
        return self::escapeText($value);
    }

    /**
     * Restrict a font family to characters that cannot break out of an attribute.
     */
    public static function fontFamily(string $family): string
    {
        $clean = preg_replace('/[^A-Za-z0-9 ,"\'\-_]/', '', $family) ?? '';

        return trim($clean) === '' ? 'sans-serif' : trim($clean);
    }
}
