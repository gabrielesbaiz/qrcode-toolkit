<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use DateTimeInterface;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;

/**
 * The payload strings scanners understand.
 *
 * Each format has its own escaping rules and each of them has a trap in it.
 * They are gathered here so the traps are documented and tested once.
 */
final class Payload
{
    /**
     * Build a WIFI: payload.
     *
     * @param  array<string, string>  $enterprise  eap, anonymous, identity, phase2
     */
    public static function wifi(
        string $ssid,
        ?string $password = null,
        string $encryption = 'WPA',
        bool $hidden = false,
        array $enterprise = [],
    ): string {
        $type = strtoupper(trim($encryption));

        if (! in_array($type, ['WPA', 'WEP', 'WPA2-EAP', 'NOPASS'], true)) {
            throw QrException::invalidOption('encryption', $encryption, ['WPA', 'WEP', 'WPA2-EAP', 'nopass']);
        }

        if ($type === 'NOPASS') {
            $type = 'nopass';
        }

        $length = strlen($ssid);

        if ($length < 1 || $length > 32) {
            throw QrException::because(sprintf('A WiFi SSID is 1 to 32 bytes. Got %d.', $length));
        }

        if ($type === 'WPA' && $password !== null) {
            $passwordLength = strlen($password);

            if (($passwordLength < 8 || $passwordLength > 63) && $passwordLength !== 64) {
                throw QrException::because(sprintf(
                    'A WPA passphrase is 8 to 63 bytes, or exactly 64 hex characters. Got %d.',
                    $passwordLength,
                ));
            }
        }

        $payload = 'WIFI:T:'.$type.';S:'.self::escapeWifi($ssid).';';

        // With no password there is no P field at all; an empty one makes
        // several scanners prompt for a passphrase on an open network.
        if ($type !== 'nopass' && $password !== null && $password !== '') {
            $payload .= 'P:'.self::escapeWifi($password).';';
        }

        if ($type === 'WPA2-EAP') {
            foreach (['eap' => 'E', 'anonymous' => 'A', 'identity' => 'I', 'phase2' => 'PH2'] as $key => $field) {
                if (isset($enterprise[$key]) && $enterprise[$key] !== '') {
                    $payload .= $field.':'.self::escapeWifi($enterprise[$key]).';';
                }
            }
        }

        if ($hidden) {
            $payload .= 'H:true;';
        }

        return $payload.';';
    }

    /**
     * Escape a WiFi field value.
     *
     * Two rules, both of which are commonly got wrong. The backslash has to
     * be replaced in the same pass as everything else, or sequential
     * replacements escape the escapes. And a value that is entirely hex and
     * of even length is taken for hex by some scanners, so it is quoted;
     * that is the difference between joining a network and not.
     */
    public static function escapeWifi(string $value): string
    {
        $escaped = strtr($value, [
            '\\' => '\\\\',
            ';' => '\;',
            ',' => '\\,',
            ':' => '\\:',
            '"' => '\\"',
        ]);

        if (preg_match('/^(?:[0-9A-Fa-f]{2})+$/', $value) === 1) {
            return '"'.$escaped.'"';
        }

        return $escaped;
    }

    /**
     * Build a vCard 3.0 payload.
     *
     * Deliberately unfolded. RFC 2426 wants lines wrapped at 75 octets, and
     * several scanners mis-parse the continuation, so the whole card is
     * written on unwrapped lines.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function vcard(array $fields): string
    {
        $get = static fn (string $key): string => is_scalar($fields[$key] ?? null) ? trim((string) $fields[$key]) : '';

        /** @var array<int, string> $lines */
        $lines = ['BEGIN:VCARD', 'VERSION:3.0'];

        $name = [$get('last'), $get('first'), $get('middle'), $get('prefix'), $get('suffix')];
        $lines[] = 'N:'.implode(';', array_map(self::escapeVcard(...), $name));

        $formatted = $get('formatted') !== ''
            ? $get('formatted')
            : trim($get('first').' '.$get('last'));

        if ($formatted === '') {
            throw QrException::because('A vCard needs at least a first or last name.');
        }

        $lines[] = 'FN:'.self::escapeVcard($formatted);

        foreach (['org' => 'ORG', 'title' => 'TITLE'] as $key => $property) {
            if ($get($key) !== '') {
                $lines[] = $property.':'.self::escapeVcard($get($key));
            }
        }

        foreach (['phone' => 'TEL;TYPE=VOICE', 'mobile' => 'TEL;TYPE=CELL', 'work_phone' => 'TEL;TYPE=WORK,VOICE', 'fax' => 'TEL;TYPE=FAX'] as $key => $property) {
            foreach (self::many($fields[$key] ?? null) as $number) {
                $lines[] = $property.':'.self::escapeVcard($number);
            }
        }

        foreach (self::many($fields['email'] ?? null) as $email) {
            $lines[] = 'EMAIL;TYPE=INTERNET:'.self::escapeVcard($email);
        }

        $address = [$get('street'), $get('city'), $get('region'), $get('postal_code'), $get('country')];

        if (implode('', $address) !== '') {
            $lines[] = 'ADR;TYPE=WORK:;;'.implode(';', array_map(self::escapeVcard(...), $address));
        }

        foreach (['url' => 'URL', 'note' => 'NOTE'] as $key => $property) {
            if ($get($key) !== '') {
                $lines[] = $property.':'.self::escapeVcard($get($key));
            }
        }

        if (isset($fields['birthday']) && $fields['birthday'] !== '') {
            $lines[] = 'BDAY:'.self::birthday($fields['birthday'], compact: false);
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    /**
     * Build a MECARD payload.
     *
     * Roughly forty per cent shorter than the equivalent vCard, which is
     * often a whole symbol version at small print sizes.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function mecard(array $fields): string
    {
        $get = static fn (string $key): string => is_scalar($fields[$key] ?? null) ? trim((string) $fields[$key]) : '';

        $parts = [];

        $last = self::escapeMecard($get('last'));
        $first = self::escapeMecard($get('first'));

        if ($last === '' && $first === '') {
            throw QrException::because('A MECARD needs at least a first or last name.');
        }

        // The comma between surname and given name is MECARD's own
        // separator, so it is written unescaped around escaped components.
        $parts[] = 'N:'.$last.','.$first;

        foreach (['mobile', 'phone', 'work_phone'] as $key) {
            foreach (self::many($fields[$key] ?? null) as $number) {
                $parts[] = 'TEL:'.self::escapeMecard($number);
            }
        }

        foreach (self::many($fields['email'] ?? null) as $email) {
            $parts[] = 'EMAIL:'.self::escapeMecard($email);
        }

        $address = array_map(self::escapeMecard(...), [
            '', '', $get('street'), $get('city'), $get('region'), $get('postal_code'), $get('country'),
        ]);

        if (trim(implode('', $address)) !== '') {
            $parts[] = 'ADR:'.implode(',', $address);
        }

        foreach (['url' => 'URL', 'note' => 'NOTE'] as $key => $field) {
            if ($get($key) !== '') {
                $parts[] = $field.':'.self::escapeMecard($get($key));
            }
        }

        if (isset($fields['birthday']) && $fields['birthday'] !== '') {
            $parts[] = 'BDAY:'.self::birthday($fields['birthday'], compact: true);
        }

        return 'MECARD:'.implode(';', $parts).';;';
    }

    /**
     * Escape a vCard property value.
     */
    public static function escapeVcard(string $value): string
    {
        return strtr(str_replace("\r", '', $value), [
            '\\' => '\\\\',
            ';' => '\;',
            ',' => '\\,',
            "\n" => '\\n',
        ]);
    }

    /**
     * Escape a MECARD field value.
     */
    public static function escapeMecard(string $value): string
    {
        return strtr(str_replace(["\r", "\n"], ' ', $value), [
            '\\' => '\\\\',
            ';' => '\;',
            ':' => '\\:',
            ',' => '\\,',
        ]);
    }

    /**
     * Normalise a birthday to the form the card format requires.
     *
     * vCard wants ISO 8601, MECARD wants the same date without separators.
     * A locale-formatted string is refused rather than guessed at: 12/03
     * is the twelfth of March to half the world and the third of December
     * to the other half, and a contact importer that silently takes the
     * wrong one is worse than an exception. Google Contacts drops a BDAY
     * it cannot parse, which is how this goes unnoticed.
     */
    public static function birthday(mixed $value, bool $compact = false): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($compact ? 'Ymd' : 'Y-m-d');
        }

        if (! is_string($value) && ! is_int($value)) {
            throw QrException::because('A birthday must be a DateTimeInterface or a date string in YYYY-MM-DD form.');
        }

        $raw = trim((string) $value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1
            || preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m) === 1) {
            [, $year, $month, $day] = $m;

            if (! checkdate((int) $month, (int) $day, (int) $year)) {
                throw QrException::because(sprintf('[%s] is not a real date.', $raw));
            }

            return $compact ? $year.$month.$day : $year.'-'.$month.'-'.$day;
        }

        throw QrException::because(sprintf(
            'Birthday [%s] is ambiguous. Pass a DateTimeInterface, or a string as YYYY-MM-DD, '
            .'so that the day and the month cannot be read the wrong way round.',
            $raw,
        ));
    }

    /**
     * Normalise a field that accepts one value or several.
     *
     * @return array<int, string>
     */
    private static function many(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $values = is_array($value) ? $value : [$value];
        $out = [];

        foreach ($values as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $out[] = trim((string) $item);
            }
        }

        return $out;
    }
}
