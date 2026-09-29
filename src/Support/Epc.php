<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Illuminate\Support\Str;

/**
 * SEPA Credit Transfer payloads, to the European Payments Council's
 * EPC069-12. Commonly called a GiroCode, which is a brand of Die Deutsche
 * Kreditwirtschaft for the German rollout rather than the standard's name.
 *
 * Twelve lines, separated by a bare line feed. CRLF here is a documented
 * cause of banking apps refusing the code, which is why the separator is
 * written explicitly rather than taken from PHP_EOL.
 */
final class Epc
{
    /**
     * The largest payload an EPC payment code may carry.
     */
    public const MAX_BYTES = 331;

    /**
     * Build the payload.
     */
    public static function build(
        string $name,
        string $iban,
        float|string|null $amount = null,
        ?string $remittance = null,
        ?string $bic = null,
        ?string $purpose = null,
        ?string $reference = null,
        ?string $information = null,
    ): string {
        if ($remittance !== null && $remittance !== '' && $reference !== null && $reference !== '') {
            throw QrException::because(
                'An EPC payload carries either a structured creditor reference or unstructured remittance '
                .'information, never both. Pass one of them.',
            );
        }

        $beneficiary = self::sanitise($name, 70, 'name');

        if ($beneficiary === '') {
            throw QrException::because('An EPC payload needs a beneficiary name.');
        }

        if ($bic !== null && $bic !== '' && preg_match('/^[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$/', strtoupper($bic)) !== 1) {
            throw QrException::because(sprintf('BIC [%s] is not 8 or 11 characters in the published format.', $bic));
        }

        $lines = [
            'BCD',
            '002',
            '1',
            'SCT',
            $bic === null ? '' : strtoupper($bic),
            $beneficiary,
            Iban::validate($iban),
            self::amount($amount),
            $purpose === null ? '' : self::sanitise($purpose, 4, 'purpose'),
            $reference === null ? '' : self::sanitise($reference, 35, 'reference'),
            $remittance === null ? '' : self::sanitise($remittance, 140, 'remittance'),
            $information === null ? '' : self::sanitise($information, 70, 'information'),
        ];

        // Trailing empty lines may be dropped, and dropping them is free
        // payload budget; the first seven are always written.
        while (count($lines) > 7 && end($lines) === '') {
            array_pop($lines);
        }

        $payload = implode("\n", $lines);
        $bytes = strlen($payload);

        if ($bytes > self::MAX_BYTES) {
            throw QrException::because(sprintf(
                'The EPC payload is %d bytes; the specification allows %d. Shorten the remittance information.',
                $bytes,
                self::MAX_BYTES,
            ));
        }

        return $payload;
    }

    /**
     * Format the amount, or an empty line when the payer should enter it.
     */
    private static function amount(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        if (! is_numeric($amount)) {
            throw QrException::because(sprintf('The EPC amount [%s] is not a number.', $amount));
        }

        $value = round((float) $amount, 2);

        if ($value < 0.01 || $value > 999999999.99) {
            throw QrException::outOfRange('amount', $amount, 0.01, 999999999.99);
        }

        return 'EUR'.number_format($value, 2, '.', '');
    }

    /**
     * Reduce a field to the characters the SEPA scheme permits.
     *
     * Line three stays at 1, meaning UTF-8, but the content is held to the
     * basic Latin subset banks actually accept. Accents are transliterated
     * rather than dropped, so a name stays recognisable.
     */
    private static function sanitise(string $value, int $max, string $field): string
    {
        // Str::ascii transliterates without the stray combining marks that
        // iconv's //TRANSLIT leaves behind on some platforms, and the
        // apostrophe is a legal SEPA character so it cannot be stripped
        // afterwards to clean them up.
        $ascii = Str::ascii($value);

        $clean = preg_replace("/[^A-Za-z0-9\\/\\-?:()., '+ ]/", '', $ascii) ?? '';
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean));

        if (strlen($clean) > $max) {
            throw QrException::because(sprintf(
                'The EPC %s field is %d characters; the specification allows %d.',
                $field,
                strlen($clean),
                $max,
            ));
        }

        return $clean;
    }
}
