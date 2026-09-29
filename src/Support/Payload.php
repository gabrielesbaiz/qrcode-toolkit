<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
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
    public static function vcard(array $fields, string $version = '3.0'): string
    {
        if ($version === '4.0') {
            return self::vcard4($fields);
        }

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
     * Build a vCard 4.0 payload.
     *
     * RFC 6350 rather than RFC 2426: telephone numbers are tel: URIs,
     * TYPE values are lower case, and KIND is stated explicitly. It is
     * offered rather than defaulted because a number of Android scanners
     * still mishandle it, which is why 3.0 remains the default.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function vcard4(array $fields): string
    {
        $get = static fn (string $key): string => is_scalar($fields[$key] ?? null) ? trim((string) $fields[$key]) : '';

        /** @var array<int, string> $lines */
        $lines = ['BEGIN:VCARD', 'VERSION:4.0', 'KIND:individual'];

        $name = [$get('last'), $get('first'), $get('middle'), $get('prefix'), $get('suffix')];
        $lines[] = 'N:'.implode(';', array_map(self::escapeVcard(...), $name));

        $formatted = $get('formatted') !== '' ? $get('formatted') : trim($get('first').' '.$get('last'));

        if ($formatted === '') {
            throw QrException::because('A vCard needs at least a first or last name.');
        }

        $lines[] = 'FN:'.self::escapeVcard($formatted);

        foreach (['org' => 'ORG', 'title' => 'TITLE'] as $key => $property) {
            if ($get($key) !== '') {
                $lines[] = $property.':'.self::escapeVcard($get($key));
            }
        }

        // In 4.0 a telephone number is a URI, not free text.
        foreach (['phone' => 'voice', 'mobile' => 'cell', 'work_phone' => 'work,voice', 'fax' => 'fax'] as $key => $type) {
            foreach (self::many($fields[$key] ?? null) as $number) {
                $lines[] = sprintf('TEL;TYPE="%s";VALUE=uri:tel:%s', $type, self::telUri($number));
            }
        }

        foreach (self::many($fields['email'] ?? null) as $email) {
            $lines[] = 'EMAIL:'.self::escapeVcard($email);
        }

        $address = [$get('street'), $get('city'), $get('region'), $get('postal_code'), $get('country')];

        if (implode('', $address) !== '') {
            $lines[] = 'ADR;TYPE=work:;;'.implode(';', array_map(self::escapeVcard(...), $address));
        }

        foreach (['url' => 'URL', 'note' => 'NOTE'] as $key => $property) {
            if ($get($key) !== '') {
                $lines[] = $property.':'.self::escapeVcard($get($key));
            }
        }

        if (isset($fields['birthday']) && $fields['birthday'] !== '') {
            $lines[] = 'BDAY:'.self::birthday($fields['birthday'], compact: true);
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    /**
     * Reduce a telephone number to what a tel: URI accepts.
     */
    private static function telUri(string $number): string
    {
        $plus = str_starts_with(trim($number), '+');

        return ($plus ? '+' : '').(preg_replace('/\D+/', '', $number) ?? '');
    }

    /**
     * Build a calendar event.
     *
     * A bare VEVENT rather than a whole VCALENDAR: it is what scanners
     * offer to add to a diary, and it is what both of the other libraries
     * emit. Times are written in UTC, because a local time with no
     * timezone is read as the scanner's own and quietly moves the event.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function calendar(array $fields): string
    {
        $get = static fn (string $key): string => is_scalar($fields[$key] ?? null) ? trim((string) $fields[$key]) : '';

        $summary = $get('summary');

        if ($summary === '') {
            throw QrException::because('A calendar event needs a summary.');
        }

        $allDay = (bool) ($fields['all_day'] ?? false);

        $start = self::moment($fields['start'] ?? null, 'start', $allDay);
        $end = isset($fields['end'])
            ? self::moment($fields['end'], 'end', $allDay)
            : null;

        if ($end !== null && $end < $start) {
            throw QrException::because('A calendar event cannot end before it starts.');
        }

        $lines = ['BEGIN:VEVENT', 'SUMMARY:'.self::escapeVcard($summary)];

        $lines[] = $allDay ? 'DTSTART;VALUE=DATE:'.$start : 'DTSTART:'.$start;

        if ($end !== null) {
            $lines[] = $allDay ? 'DTEND;VALUE=DATE:'.$end : 'DTEND:'.$end;
        }

        foreach (['location' => 'LOCATION', 'description' => 'DESCRIPTION', 'url' => 'URL'] as $key => $property) {
            if ($get($key) !== '') {
                $lines[] = $property.':'.self::escapeVcard($get($key));
            }
        }

        $lines[] = 'END:VEVENT';

        return implode("\r\n", $lines);
    }

    /**
     * Format one end of an event.
     */
    private static function moment(mixed $value, string $field, bool $allDay): string
    {
        if ($value === null) {
            throw QrException::because(sprintf('A calendar event needs a [%s] time.', $field));
        }

        if ($value instanceof DateTimeInterface) {
            $utc = (new DateTimeImmutable('@'.$value->getTimestamp()))->setTimezone(new DateTimeZone('UTC'));

            return $allDay ? $utc->format('Ymd') : $utc->format('Ymd\THis\Z');
        }

        if (! is_string($value)) {
            throw QrException::because(sprintf('The [%s] time must be a DateTimeInterface or a date string.', $field));
        }

        try {
            $parsed = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception) {
            throw QrException::because(sprintf('The [%s] time [%s] could not be read as a date.', $field, $value));
        }

        return $allDay ? $parsed->format('Ymd') : $parsed->format('Ymd\THis\Z');
    }

    /**
     * Build an otpauth URI, for two-factor enrolment.
     *
     * The shape an authenticator app expects, per the Key URI Format:
     * otpauth://totp/Issuer:account?secret=…&issuer=…&algorithm=…
     *
     * @param  array<string, mixed>  $options
     */
    public static function otpauth(string $issuer, string $account, string $secret, array $options = []): string
    {
        $type = strtolower(self::option($options, 'type', 'totp'));

        if (! in_array($type, ['totp', 'hotp'], true)) {
            throw QrException::invalidOption('type', $type, ['totp', 'hotp']);
        }

        // The secret is Base32 with no padding, and an app given anything
        // else produces codes that are simply wrong — a failure the user
        // discovers by being locked out.
        $clean = strtoupper((string) preg_replace('/[\s-]+/', '', $secret));

        if ($clean === '' || preg_match('/^[A-Z2-7]+=*$/', $clean) !== 1) {
            throw QrException::because(
                'An otpauth secret must be Base32 — the letters A to Z and the digits 2 to 7. '
                .'Most libraries give you one already encoded; do not pass raw bytes.',
            );
        }

        $digits = (int) self::option($options, 'digits', '6');

        if ($digits !== 6 && $digits !== 8) {
            throw QrException::invalidOption('digits', $digits, [6, 8]);
        }

        $algorithm = strtoupper(self::option($options, 'algorithm', 'SHA1'));

        if (! in_array($algorithm, ['SHA1', 'SHA256', 'SHA512'], true)) {
            throw QrException::invalidOption('algorithm', $algorithm, ['SHA1', 'SHA256', 'SHA512']);
        }

        if (trim($issuer) === '' || trim($account) === '') {
            throw QrException::because('An otpauth URI needs both an issuer and an account.');
        }

        $label = rawurlencode(trim($issuer)).':'.rawurlencode(trim($account));

        $query = [
            'secret='.rtrim($clean, '='),
            'issuer='.rawurlencode(trim($issuer)),
            'algorithm='.$algorithm,
            'digits='.$digits,
        ];

        if ($type === 'hotp') {
            $counter = $options['counter'] ?? null;

            if (! is_numeric($counter)) {
                throw QrException::because('A counter-based (hotp) URI needs a numeric [counter].');
            }

            $query[] = 'counter='.(int) $counter;
        } else {
            $period = (int) self::option($options, 'period', '30');

            if ($period < 1 || $period > 300) {
                throw QrException::outOfRange('period', $period, 1, 300);
            }

            $query[] = 'period='.$period;
        }

        return 'otpauth://'.$type.'/'.$label.'?'.implode('&', $query);
    }

    /**
     * Read one otpauth option as a string, whatever it arrived as.
     *
     * @param  array<string, mixed>  $options
     */
    private static function option(array $options, string $key, string $default): string
    {
        $value = $options[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
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
