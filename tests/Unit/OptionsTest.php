<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;
use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Options;

it('applies documented defaults', function (): void {
    $options = Options::fromArray([]);

    expect($options->size)->toBe(300)
        ->and($options->margin)->toBe(4)
        ->and($options->ecc)->toBe(Ecc::M)
        ->and($options->foreground)->toBe('#000000')
        ->and($options->background)->toBe('#ffffff')
        ->and($options->modules)->toBe(ModuleStyle::Square)
        ->and($options->eyes)->toBe(EyeStyle::Square)
        ->and($options->moduleScale)->toBe(1.0);
});

it('rejects an unknown key and suggests the closest one', function (): void {
    expect(fn (): Options => Options::fromArray(['sizee' => 10]))
        ->toThrow(QrException::class, 'Did you mean [size]?');
});

it('normalises hex colours', function (string $input, string $expected): void {
    // The contrast guard is off here because the subject is the colour
    // parsing, not whether cyan on white is readable.
    expect(Options::fromArray(['foreground' => $input, 'min_contrast' => 0])->foreground)->toBe($expected);
})->with([
    ['#000', '#000000'],
    ['#0AF', '#00aaff'],
    ['#00AAFF', '#00aaff'],
]);

it('refuses anything that is not a hex colour', function (string $value): void {
    expect(fn (): Options => Options::fromArray(['foreground' => $value, 'min_contrast' => 0]))
        ->toThrow(QrException::class, 'must be a hex colour');
})->with([
    'named' => ['black'],
    'rgb function' => ['rgb(0,0,0)'],
    'with alpha' => ['#00000080'],
    'url reference' => ['url(#evil)'],
]);

it('enforces every documented range', function (string $key, mixed $value): void {
    expect(fn (): Options => Options::fromArray([$key => $value]))->toThrow(QrException::class);
})->with([
    ['size', 8],
    ['size', 9000],
    ['margin', -1],
    ['margin', 100],
    ['version', 0],
    ['version', 41],
    ['module_scale', 0.05],
    ['module_scale', 1.5],
    ['logo_size', 0.01],
    ['logo_size', 0.9],
    ['label_size', 2],
    ['label_size', 500],
]);

it('treats a null background as transparent', function (): void {
    expect(Options::fromArray(['background' => null])->background)->toBeNull();
});

it('round-trips through toArray without changing the fingerprint', function (): void {
    $options = Options::fromArray([
        'size' => 512,
        'modules' => 'dots',
        'eyes' => 'circle',
        'gradient' => ['from' => '#4338ca', 'to' => '#be185d', 'angle' => 45],
        'frame' => ['color' => '#0f172a', 'width' => 1, 'radius' => 2],
    ]);

    expect(Options::fromArray($options->toArray())->fingerprint())->toBe($options->fingerprint());
});

it('keeps toArray key order stable', function (): void {
    $one = Options::fromArray(['size' => 400, 'margin' => 2]);
    $two = Options::fromArray(['margin' => 2, 'size' => 400]);

    expect(array_keys($two->toArray()))->toBe(array_keys($one->toArray()))
        ->and($two->fingerprint())->toBe($one->fingerprint());
});

it('gives different option sets different fingerprints', function (): void {
    expect(Options::fromArray(['size' => 300])->fingerprint())
        ->not->toBe(Options::fromArray(['size' => 301])->fingerprint());
});

it('raises error correction to H for a logo', function (): void {
    $options = Options::fromArray(['ecc' => 'L', 'logo' => 'logo.png']);

    expect($options->ecc)->toBe(Ecc::L)
        ->and($options->effectiveEcc())->toBe(Ecc::H)
        ->and($options->eccWasRaised())->toBeTrue();
});

it('does not raise error correction without a logo', function (): void {
    $options = Options::fromArray(['ecc' => 'L']);

    expect($options->effectiveEcc())->toBe(Ecc::L)
        ->and($options->eccWasRaised())->toBeFalse();
});

it('honours an explicit EPC level even with a logo set', function (): void {
    $options = Options::fromArray([
        'ecc' => 'M',
        'ecc_explicit' => true,
        'payload_kind' => 'epc',
        'logo' => 'logo.png',
    ]);

    expect($options->effectiveEcc())->toBe(Ecc::M);
});

it('only prefers crisp edges for a plain square grid', function (array $options, bool $expected): void {
    expect(Options::fromArray($options)->prefersCrispEdges())->toBe($expected);
})->with([
    'plain' => [[], true],
    'rounded modules' => [['modules' => 'rounded'], false],
    'circle eyes' => [['eyes' => 'circle'], false],
    'scaled modules' => [['module_scale' => 0.8], false],
    'rounded frame' => [['frame' => ['radius' => 2]], false],
    'square frame' => [['frame' => ['radius' => 0]], true],
]);

it('falls back for unset eye colours', function (): void {
    $options = Options::fromArray(['foreground' => '#123456']);

    expect($options->resolvedEyeColor())->toBe('#123456')
        ->and($options->resolvedEyeInnerColor())->toBe('#123456');

    $tinted = Options::fromArray(['foreground' => '#123456', 'eye_color' => '#654321']);

    expect($tinted->resolvedEyeInnerColor())->toBe('#654321');
});

it('rejects unknown keys inside gradient and frame', function (): void {
    expect(fn (): Options => Options::fromArray(['gradient' => ['from' => '#000', 'to' => '#333', 'wobble' => 1]]))
        ->toThrow(QrException::class, 'gradient.wobble');

    expect(fn (): Options => Options::fromArray(['frame' => ['thickness' => 2]]))
        ->toThrow(QrException::class, 'frame.thickness');
});

it('requires both gradient stops', function (): void {
    expect(fn (): Options => Options::fromArray(['gradient' => ['from' => '#000000']]))
        ->toThrow(QrException::class, 'requires the key [to]');
});

it('normalises the gradient angle into one turn', function (): void {
    expect(Options::fromArray([
        'gradient' => ['from' => '#000', 'to' => '#333', 'angle' => 405.0],
    ])->gradient?->angle)->toBe(45.0);
});

it('leaves with() untouched on the original', function (): void {
    $original = Options::fromArray(['size' => 300]);
    $changed = $original->with(['size' => 500]);

    expect($original->size)->toBe(300)->and($changed->size)->toBe(500);
});
