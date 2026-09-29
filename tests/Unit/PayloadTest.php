<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Support\Epc;
use Gabrielesbaiz\QrcodeToolkit\Support\Iban;
use Gabrielesbaiz\QrcodeToolkit\Support\Payload;

it('builds a WPA network', function (): void {
    expect(Payload::wifi('Office', 'sup3rsecret'))
        ->toBe('WIFI:T:WPA;S:Office;P:sup3rsecret;;');
});

it('omits the password field entirely on an open network', function (): void {
    expect(Payload::wifi('Guest', null, 'nopass'))
        ->toBe('WIFI:T:nopass;S:Guest;;')
        ->not->toContain('P:');
});

it('marks a hidden network', function (): void {
    expect(Payload::wifi('Office', 'sup3rsecret', 'WPA', true))
        ->toContain('H:true;');
});

it('escapes every reserved WiFi character in one pass', function (): void {
    // The backslash has to be replaced alongside the rest, not before it,
    // or the escapes themselves get escaped.
    expect(Payload::escapeWifi('a\\b;c,d:e"f'))
        ->toBe('a\\\\b\;c\\,d\\:e\\"f');
});

it('quotes a value that would otherwise be read as hex', function (): void {
    expect(Payload::escapeWifi('deadbeef'))->toBe('"deadbeef"')
        ->and(Payload::escapeWifi('deadbee'))->toBe('deadbee')
        ->and(Payload::escapeWifi('MyNetwork'))->toBe('MyNetwork');
});

it('carries the enterprise fields', function (): void {
    $payload = Payload::wifi('Corp', 'pw', 'WPA2-EAP', false, [
        'eap' => 'PEAP',
        'identity' => 'gabriele',
        'phase2' => 'MSCHAPV2',
    ]);

    expect($payload)->toContain('E:PEAP;')->toContain('I:gabriele;')->toContain('PH2:MSCHAPV2;');
});

it('rejects an SSID or passphrase of the wrong length', function (): void {
    expect(fn (): string => Payload::wifi(''))->toThrow(QrException::class, '1 to 32 bytes');
    expect(fn (): string => Payload::wifi('Office', 'short'))->toThrow(QrException::class, '8 to 63 bytes');
});

it('builds a vCard with CRLF line endings and no folding', function (): void {
    $card = Payload::vcard(['first' => 'Mario', 'last' => 'Rossi', 'email' => 'm.rossi@example.com']);

    expect($card)->toStartWith("BEGIN:VCARD\r\nVERSION:3.0\r\n")
        ->toContain("\r\nFN:Mario Rossi\r\n")
        ->toContain("\r\nN:Rossi;Mario;;;\r\n")
        ->toContain("\r\nEMAIL;TYPE=INTERNET:m.rossi@example.com\r\n")
        ->toEndWith("\r\nEND:VCARD");
});

it('escapes vCard components separately from their separators', function (): void {
    $card = Payload::vcard(['first' => 'A;B', 'last' => 'C,D']);

    // The semicolons inside the values are escaped; the ones dividing the
    // five name components are not.
    expect($card)->toContain('N:C\\,D;A\;B;;;');
});

it('strips newlines out of a vCard value', function (): void {
    expect(Payload::vcard(['first' => 'A', 'last' => 'B', 'note' => "one\ntwo"]))
        ->toContain('NOTE:one\\ntwo');
});

it('builds a shorter MECARD than the equivalent vCard', function (): void {
    $fields = ['first' => 'Mario', 'last' => 'Rossi', 'email' => 'm.rossi@example.com', 'mobile' => '+393331234567'];

    expect(strlen(Payload::mecard($fields)))->toBeLessThan(strlen(Payload::vcard($fields)));
});

it('formats a MECARD name as surname then given name', function (): void {
    expect(Payload::mecard(['first' => 'Mario', 'last' => 'Rossi']))
        ->toStartWith('MECARD:N:Rossi,Mario;')
        ->toEndWith(';;');
});

it('needs at least one name', function (): void {
    expect(fn (): string => Payload::vcard([]))->toThrow(QrException::class, 'at least a first or last name');
    expect(fn (): string => Payload::mecard([]))->toThrow(QrException::class, 'at least a first or last name');
});

it('validates a real IBAN', function (string $iban): void {
    expect(Iban::validate($iban))->toBe(str_replace(' ', '', strtoupper($iban)));
})->with([
    'IT60X0542811101000000123456',
    'DE89370400440532013000',
    'GB82WEST12345698765432',
    'FR1420041010050500013M02606',
    'NL91ABNA0417164300',
    'it60 x054 2811 1010 0000 0123 456',
]);

it('rejects a broken IBAN, saying which check failed', function (string $iban, string $reason): void {
    expect(fn (): string => Iban::validate($iban))->toThrow(QrException::class, $reason);
})->with([
    'checksum' => ['GB82WEST12345698765433', 'mod 97 checksum'],
    'too short' => ['IT60X05', 'between 15 and 34'],
    'wrong shape' => ['1T60X0542811101000000123456', 'two letters and two digits'],
    'wrong country length' => ['DE8937040044053201300012', 'a DE IBAN is 22 characters'],
]);

it('builds the twelve line EPC block', function (): void {
    $payload = Epc::build('Acme S.r.l.', 'IT60X0542811101000000123456', 12.30, 'Invoice 42', 'BPPBITRRXXX', 'GDDS');
    $lines = explode("\n", $payload);

    expect($lines[0])->toBe('BCD')
        ->and($lines[1])->toBe('002')
        ->and($lines[2])->toBe('1')
        ->and($lines[3])->toBe('SCT')
        ->and($lines[4])->toBe('BPPBITRRXXX')
        ->and($lines[5])->toBe('Acme S.r.l.')
        ->and($lines[6])->toBe('IT60X0542811101000000123456')
        ->and($lines[7])->toBe('EUR12.30')
        ->and($lines[8])->toBe('GDDS')
        ->and($lines[9])->toBe('')
        ->and($lines[10])->toBe('Invoice 42');
});

it('separates EPC lines with a bare line feed', function (): void {
    // CRLF here is a documented cause of banking apps refusing the code.
    expect(Epc::build('Acme', 'DE89370400440532013000'))->not->toContain("\r");
});

it('drops trailing empty EPC lines but keeps the first seven', function (): void {
    expect(explode("\n", Epc::build('Acme', 'DE89370400440532013000')))->toHaveCount(7);
});

it('refuses both a structured reference and unstructured remittance', function (): void {
    expect(fn (): string => Epc::build('Acme', 'DE89370400440532013000', 1.0, 'Invoice 42', null, null, 'RF18539007547034'))
        ->toThrow(QrException::class, 'never both');
});

it('formats the EPC amount to two decimals in euros', function (float|string $amount, string $expected): void {
    expect(explode("\n", Epc::build('Acme', 'DE89370400440532013000', $amount))[7])->toBe($expected);
})->with([
    [12.3, 'EUR12.30'],
    [1.0, 'EUR1.00'],
    ['0.01', 'EUR0.01'],
    [1234.567, 'EUR1234.57'],
]);

it('leaves the EPC amount blank when the payer should enter it', function (): void {
    expect(explode("\n", Epc::build('Acme', 'DE89370400440532013000', null, 'Donation'))[7])->toBe('');
});

it('rejects an EPC amount outside the scheme limits', function (float $amount): void {
    expect(fn (): string => Epc::build('Acme', 'DE89370400440532013000', $amount))->toThrow(QrException::class);
})->with([0.001, 1000000000.0]);

it('transliterates an EPC name into the permitted character set', function (): void {
    expect(explode("\n", Epc::build('Ünïcödé Ltd', 'DE89370400440532013000'))[5])->toBe('Unicode Ltd');
});

it('refuses an EPC payload over the byte limit', function (): void {
    expect(fn (): string => Epc::build('Acme', 'DE89370400440532013000', 1.0, str_repeat('x', 200)))
        ->toThrow(QrException::class);
});

it('validates the BIC when one is given', function (): void {
    expect(fn (): string => Epc::build('Acme', 'DE89370400440532013000', null, null, 'NOTABIC'))
        ->toThrow(QrException::class, 'BIC');
});

it('normalises a birthday to ISO for vCard and compact for MECARD', function (mixed $input, string $vcard, string $mecard): void {
    expect(Payload::birthday($input))->toBe($vcard)
        ->and(Payload::birthday($input, compact: true))->toBe($mecard);
})->with([
    'iso' => ['1985-03-12', '1985-03-12', '19850312'],
    'basic' => ['19850312', '1985-03-12', '19850312'],
    'object' => [new DateTimeImmutable('1985-03-12'), '1985-03-12', '19850312'],
]);

it('refuses a birthday whose day and month could be read either way round', function (): void {
    // Google Contacts silently drops a BDAY it cannot parse, so guessing
    // here would be a bug nobody notices until the wrong date is saved.
    expect(fn (): string => Payload::birthday('12/03/1985'))
        ->toThrow(QrException::class, 'ambiguous');
});

it('refuses a date that does not exist', function (): void {
    expect(fn (): string => Payload::birthday('1985-13-45'))->toThrow(QrException::class, 'not a real date');
});

it('emits an importable BDAY in both card formats', function (): void {
    expect(Payload::vcard(['first' => 'Mario', 'last' => 'Rossi', 'birthday' => '1985-03-12']))
        ->toContain("\r\nBDAY:1985-03-12\r\n");

    expect(Payload::mecard(['first' => 'Mario', 'last' => 'Rossi', 'birthday' => '1985-03-12']))
        ->toContain('BDAY:19850312');
});

it('writes a vCard that satisfies what a contact importer requires', function (): void {
    $card = Payload::vcard([
        'first' => 'Mario', 'last' => 'Rossi', 'org' => 'Acme S.r.l.', 'title' => 'Developer',
        'mobile' => '+393331234567', 'email' => 'mario.rossi@example.com',
        'street' => 'Via Verdi 10', 'city' => 'Milano', 'region' => 'MI',
        'postal_code' => '20121', 'country' => 'Italy', 'birthday' => '1985-03-12',
    ]);

    $lines = explode("\r\n", $card);

    // The properties Google Contacts and the iOS importer both require.
    expect($lines[0])->toBe('BEGIN:VCARD')
        ->and($lines[1])->toBe('VERSION:3.0')
        ->and(end($lines))->toBe('END:VCARD')
        ->and($card)->toContain("\r\nFN:")
        ->and($card)->toContain("\r\nN:");

    // N carries exactly five semicolon-separated components.
    $n = current(preg_grep('/^N:/', $lines));
    expect(explode(';', substr((string) $n, 2)))->toHaveCount(5);

    // Every line is a property; none is a bare continuation.
    foreach ($lines as $line) {
        expect($line)->toMatch('/^[A-Z][A-Z0-9-]*(;[^:]+)?:/');
    }
});
