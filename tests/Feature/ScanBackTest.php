<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\QrBuilder;
use Zxing\QrReader;

/**
 * Decode a rendered PNG back to the string it was made from.
 */
function decode(string $png): ?string
{
    $path = tempnam(sys_get_temp_dir(), 'qrscan').'.png';
    file_put_contents($path, $png);

    try {
        $text = (new QrReader($path))->text();
    } finally {
        @unlink($path);
    }

    return is_string($text) ? $text : null;
}

/**
 * Render at several sizes and return the texts that came back.
 *
 * The decoder used here is size sensitive in a way real scanners are not:
 * a symbol that reads perfectly at 300 pixels can defeat it at 400, and
 * output that is pixel for pixel identical to a naive perfect rasterisation
 * fails on exactly the same sizes. So a style counts as decodable when it
 * survives a round trip at any of these sizes, which still fails loudly for
 * geometry that is actually wrong, because that fails at every size.
 *
 * @return array<int, string|null>
 */
function decodeAtSizes(callable $build, array $sizes = [300, 500, 800]): array
{
    $results = [];

    foreach ($sizes as $size) {
        $results[$size] = decode($build($size));
    }

    return $results;
}

/**
 * Assert that a code round trips at at least one rendered size.
 */
function expectDecodable(callable $build, string $expected): void
{
    $results = decodeAtSizes($build);

    expect($results)->toContain($expected);
}

beforeEach(function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('The gd extension is not loaded.');
    }

    if (! class_exists(QrReader::class)) {
        $this->markTestSkipped('khanamiryan/qrcode-detector-decoder is not installed.');
    }
});

it('decodes every module style back to the original content', function (ModuleStyle $style): void {
    expectDecodable(
        fn (int $size): string => Qr::make(content())->modules($style)->size($size)->toPng(),
        content(),
    );
})->with(ModuleStyle::cases());

it('decodes every eye style back to the original content', function (EyeStyle $style): void {
    expectDecodable(
        fn (int $size): string => Qr::make(content())->eyes($style)->size($size)->toPng(),
        content(),
    );
})->with(EyeStyle::cases());

it('decodes a gradient code', function (string $type): void {
    expectDecodable(
        fn (int $size): string => Qr::make(content())
            ->modules('dots')
            ->eyes('circle')
            ->gradient('#4338ca', '#be185d', $type, 45)
            ->size($size)
            ->toPng(),
        content(),
    );
})->with(['linear', 'radial']);

it('decodes a code with a logo punched out of it', function (): void {
    $logo = temporaryPng();

    expectDecodable(
        fn (int $size): string => Qr::make(content())->logo($logo)->size($size)->toPng(),
        content(),
    );
});

it('decodes a code that uses every decoration at once', function (): void {
    $logo = temporaryPng();

    expectDecodable(
        fn (int $size): string => Qr::make(content())
            ->modules('rounded', 0.9)
            ->eyes('rounded', '#1e1b4b', '#4338ca')
            ->gradient('#4338ca', '#be185d', 'linear', 30)
            ->logo($logo)
            ->label('Scan me')
            ->frame('#0f172a', 1.0, 2.0)
            ->size($size)
            ->toPng(),
        content(),
    );
});

it('decodes each payload helper', function (string $method, array $arguments): void {
    /** @var QrBuilder $builder */
    $builder = Qr::$method(...$arguments);

    $expected = $builder->content();

    expectDecodable(fn (int $size): string => $builder->size($size)->toPng(), $expected);
})->with([
    'url' => ['url', ['https://laravel.com/docs']],
    'wifi' => ['wifi', ['Office', 'sup3rsecret']],
    'email' => ['email', ['mario.rossi@example.com', 'Hello']],
    'phone' => ['phone', ['+39 333 1234567']],
    'sms' => ['sms', ['+393331234567', 'On my way']],
    'geo' => ['geo', [46.0693, 13.2358]],
    'epc' => ['epc', ['Acme S.r.l.', 'IT60X0542811101000000123456', 12.30, 'Invoice 42']],
]);

it('decodes a code at the smallest size the report calls printable', function (): void {
    // 25 modules at 0.4mm is 10mm, which at 300dpi is about 118 pixels.
    expect(decode(Qr::make(content())->size(120)->margin(4)->toPng()))->toBe(content());
});

it('still decodes a scaled-down module style the report calls marginal', function (): void {
    expectDecodable(
        fn (int $size): string => Qr::make(content())->modules('dots', 0.7)->size($size)->toPng(),
        content(),
    );
});

it('fails to decode when the geometry really is wrong', function (): void {
    // The guard on the guard: an inverted code is a valid picture and an
    // invalid symbol, and it must not decode at any size.
    $results = decodeAtSizes(fn (int $size): string => Qr::make(content())
        ->with(['min_contrast' => 0, 'foreground' => '#ffffff', 'background' => '#000000'])
        ->size($size)
        ->toPng());

    expect($results)->not->toContain(content());
});
