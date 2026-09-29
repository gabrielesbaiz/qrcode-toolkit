<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

/**
 * Escaping for PDF and PostScript literal strings.
 *
 * Both languages write strings between parentheses, so both need the same
 * treatment. An unbalanced closing parenthesis in a label is exactly how a
 * PDF ends up corrupt, and how a crafted label would escape the string
 * object into the surrounding content stream.
 */
final class Pdf
{
    /**
     * Escape a value for a (...) literal.
     */
    public static function escapeString(string $value): string
    {
        $out = '';

        foreach (str_split($value) as $byte) {
            $out .= match ($byte) {
                '\\' => '\\\\',
                '(' => '\\(',
                ')' => '\\)',
                "\r" => '\\r',
                "\n" => '\\n',
                "\t" => '\\t',
                default => self::escapeByte($byte),
            };
        }

        return $out;
    }

    /**
     * Strip anything that could open a new DSC directive in an EPS comment.
     */
    public static function escapeComment(string $value): string
    {
        return trim(preg_replace('/[\r\n%]+/', ' ', $value) ?? '');
    }

    /**
     * Pass printable ASCII through and octal-escape everything else.
     */
    private static function escapeByte(string $byte): string
    {
        $ord = ord($byte);

        return $ord >= 32 && $ord <= 126
            ? $byte
            : sprintf('\\%03o', $ord);
    }
}
