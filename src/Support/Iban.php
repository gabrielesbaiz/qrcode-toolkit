<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;

/**
 * IBAN validation, mod 97 by ISO 7064.
 *
 * Implemented without bcmath or gmp so the package has no extension
 * requirement for it.
 */
final class Iban
{
    /**
     * The published length of an IBAN, per country, where we know it.
     *
     * An unknown country code passes the length step rather than failing, so
     * a newly admitted country does not become a support ticket.
     *
     * @var array<string, int>
     */
    private const LENGTHS = [
        'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16,
        'BG' => 22, 'BH' => 22, 'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22, 'CY' => 28,
        'CZ' => 24, 'DE' => 22, 'DK' => 18, 'DO' => 28, 'EE' => 20, 'EG' => 29, 'ES' => 24,
        'FI' => 18, 'FO' => 18, 'FR' => 27, 'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18,
        'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IL' => 23, 'IS' => 26,
        'IT' => 27, 'JO' => 30, 'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LC' => 32, 'LI' => 21,
        'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'MK' => 19,
        'MR' => 27, 'MT' => 31, 'MU' => 30, 'NL' => 18, 'NO' => 15, 'PK' => 24, 'PL' => 28,
        'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'SA' => 24, 'SC' => 31,
        'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'TN' => 24, 'TR' => 26, 'UA' => 29,
        'VA' => 22, 'VG' => 24, 'XK' => 20,
    ];

    /**
     * Normalise and validate an IBAN, returning its canonical form.
     */
    public static function validate(string $iban): string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $iban));

        $length = strlen($normalized);

        if ($length < 15 || $length > 34) {
            throw self::fail($iban, 'it must be between 15 and 34 characters once spaces are removed');
        }

        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $normalized) !== 1) {
            throw self::fail($iban, 'it must start with two letters and two digits, then letters and digits only');
        }

        $country = substr($normalized, 0, 2);
        $expected = self::LENGTHS[$country] ?? null;

        if ($expected !== null && $length !== $expected) {
            throw self::fail($iban, sprintf('a %s IBAN is %d characters, not %d', $country, $expected, $length));
        }

        if (self::remainder(substr($normalized, 4).substr($normalized, 0, 4)) !== 1) {
            throw self::fail($iban, 'it failed the mod 97 checksum, which usually means a transcription error');
        }

        return $normalized;
    }

    /**
     * Fold the rearranged IBAN down to its mod 97 remainder.
     *
     * Letters become two digit numbers, then the digits are consumed in
     * chunks small enough that the running value stays inside a 32 bit int.
     */
    private static function remainder(string $rearranged): int
    {
        $numeric = '';

        foreach (str_split($rearranged) as $character) {
            $numeric .= ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;
        }

        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }

    /**
     * Build the failure, quoting the IBAN back without leaking all of it.
     */
    private static function fail(string $iban, string $reason): QrException
    {
        $masked = strlen($iban) > 10
            ? substr($iban, 0, 6).str_repeat('.', 3).substr($iban, -4)
            : $iban;

        return QrException::because(sprintf('IBAN [%s] is not valid: %s.', $masked, $reason));
    }
}
