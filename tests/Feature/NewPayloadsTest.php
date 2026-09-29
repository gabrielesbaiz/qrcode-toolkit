<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\QrBuilder;
use Gabrielesbaiz\QrcodeToolkit\Support\Payload;
use Zxing\QrReader;

it('builds a vCard 4.0 with tel URIs and an explicit kind', function (): void {
    $card = Payload::vcard(['first' => 'Mario', 'last' => 'Rossi', 'mobile' => '+39 333 1234567'], '4.0');

    expect($card)->toContain("\r\nVERSION:4.0\r\n")
        ->toContain("\r\nKIND:individual\r\n")
        ->toContain('TEL;TYPE="cell";VALUE=uri:tel:+393331234567')
        ->toEndWith("\r\nEND:VCARD");
});

it('keeps vCard 3.0 as the default', function (): void {
    expect(Payload::vcard(['first' => 'Mario', 'last' => 'Rossi']))->toContain('VERSION:3.0')
        ->and(Qr::vcard(['first' => 'Mario', 'last' => 'Rossi'])->content())->toContain('VERSION:3.0')
        ->and(Qr::vcard(['first' => 'Mario', 'last' => 'Rossi'], version: '4.0')->content())->toContain('VERSION:4.0');
});

it('refuses a vCard version it does not write', function (): void {
    expect(fn () => Qr::vcard(['first' => 'Mario'], version: '2.1'))
        ->toThrow(QrException::class, 'Expected one of: 3.0, 4.0');
});

it('writes a calendar event in UTC', function (): void {
    $event = Payload::calendar([
        'summary' => 'Laracon EU',
        'start' => '2026-09-15 09:00',
        'end' => '2026-09-16 17:00',
        'location' => 'Amsterdam',
    ]);

    expect($event)->toStartWith('BEGIN:VEVENT')
        ->toContain("\r\nSUMMARY:Laracon EU\r\n")
        ->toContain("\r\nDTSTART:20260915T090000Z\r\n")
        ->toContain("\r\nDTEND:20260916T170000Z\r\n")
        ->toContain("\r\nLOCATION:Amsterdam\r\n")
        ->toEndWith("\r\nEND:VEVENT");
});

it('writes an all-day event as a date rather than a time', function (): void {
    $event = Payload::calendar(['summary' => 'Release day', 'start' => '2026-09-15', 'all_day' => true]);

    expect($event)->toContain('DTSTART;VALUE=DATE:20260915')->not->toContain('T000000Z');
});

it('accepts a DateTimeInterface and converts it to UTC', function (): void {
    $event = Payload::calendar([
        'summary' => 'Standup',
        'start' => new DateTimeImmutable('2026-09-15 11:00', new DateTimeZone('Europe/Rome')),
    ]);

    // 11:00 in Rome is 09:00 UTC.
    expect($event)->toContain('DTSTART:20260915T090000Z');
});

it('escapes calendar text', function (): void {
    expect(Payload::calendar(['summary' => 'Lunch; then talks, maybe', 'start' => '2026-09-15']))
        ->toContain('SUMMARY:Lunch\; then talks\, maybe');
});

it('refuses a calendar event that is missing or backwards', function (array $fields, string $reason): void {
    expect(fn (): string => Payload::calendar($fields))->toThrow(QrException::class, $reason);
})->with([
    'no summary' => [['start' => '2026-09-15'], 'needs a summary'],
    'no start' => [['summary' => 'x'], 'needs a [start] time'],
    'backwards' => [['summary' => 'x', 'start' => '2026-09-16', 'end' => '2026-09-15'], 'cannot end before it starts'],
    'unreadable' => [['summary' => 'x', 'start' => 'next tuesday-ish'], 'could not be read as a date'],
]);

it('builds an otpauth URI in the shape an authenticator expects', function (): void {
    $uri = Payload::otpauth('Acme', 'mario@example.com', 'JBSWY3DPEHPK3PXP');

    expect($uri)->toStartWith('otpauth://totp/Acme:mario%40example.com?')
        ->toContain('secret=JBSWY3DPEHPK3PXP')
        ->toContain('issuer=Acme')
        ->toContain('algorithm=SHA1')
        ->toContain('digits=6')
        ->toContain('period=30');
});

it('builds a counter-based URI when asked', function (): void {
    $uri = Payload::otpauth('Acme', 'mario', 'JBSWY3DPEHPK3PXP', ['type' => 'hotp', 'counter' => 7, 'digits' => 8]);

    expect($uri)->toStartWith('otpauth://hotp/')
        ->toContain('counter=7')
        ->toContain('digits=8')
        ->not->toContain('period=');
});

it('tidies a secret that was given with spaces', function (): void {
    expect(Payload::otpauth('Acme', 'mario', 'jbsw y3dp ehpk 3pxp'))
        ->toContain('secret=JBSWY3DPEHPK3PXP');
});

it('refuses an otpauth URI that would lock somebody out', function (array $args, string $reason): void {
    expect(fn (): string => Payload::otpauth(...$args))->toThrow(QrException::class, $reason);
})->with([
    'not base32' => [['Acme', 'mario', 'this is not base32!'], 'must be Base32'],
    'raw bytes' => [['Acme', 'mario', "\x01\x02\x03"], 'must be Base32'],
    'bad digits' => [['Acme', 'mario', 'JBSWY3DP', ['digits' => 7]], 'Expected one of: 6, 8'],
    'bad algorithm' => [['Acme', 'mario', 'JBSWY3DP', ['algorithm' => 'MD5']], 'SHA1, SHA256, SHA512'],
    'bad type' => [['Acme', 'mario', 'JBSWY3DP', ['type' => 'yubi']], 'totp, hotp'],
    'hotp without counter' => [['Acme', 'mario', 'JBSWY3DP', ['type' => 'hotp']], 'numeric [counter]'],
    'no issuer' => [['', 'mario', 'JBSWY3DP'], 'issuer and an account'],
]);

it('raises error correction for an enrolment code', function (): void {
    // Scanned once, and a failure locks the user out of their account.
    expect(Qr::otpauth('Acme', 'mario', 'JBSWY3DPEHPK3PXP')->options()->ecc->value)->toBe('Q');
});

it('decodes each new payload back to itself', function (string $method, array $args): void {
    if (! extension_loaded('gd') || ! class_exists(QrReader::class)) {
        $this->markTestSkipped('Needs gd and the decoder.');
    }

    /** @var QrBuilder $builder */
    $builder = Qr::$method(...$args);
    $expected = $builder->content();

    $decoded = [];

    foreach ([400, 600, 800] as $size) {
        $path = tempnam(sys_get_temp_dir(), 'qrnew').'.png';
        file_put_contents($path, $builder->size($size)->toPng());
        $decoded[] = (new QrReader($path))->text();
        @unlink($path);
    }

    expect($decoded)->toContain($expected);
})->with([
    'calendar' => ['calendar', [['summary' => 'Laracon EU', 'start' => '2026-09-15 09:00', 'end' => '2026-09-16 17:00', 'location' => 'Amsterdam']]],
    'otpauth' => ['otpauth', ['Acme', 'mario@example.com', 'JBSWY3DPEHPK3PXP']],
    'vcard4' => ['vcard', [['first' => 'Mario', 'last' => 'Rossi', 'mobile' => '+393331234567'], false, '4.0']],
]);
