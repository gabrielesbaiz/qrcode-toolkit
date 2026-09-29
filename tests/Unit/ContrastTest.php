<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Options;
use Gabrielesbaiz\QrcodeToolkit\Support\Color;

it('matches the published WCAG ratios', function (string $one, string $two, float $expected): void {
    expect(round(Color::contrast($one, $two), 2))->toBe($expected);
})->with([
    'black on white' => ['#000000', '#ffffff', 21.0],
    'white on white' => ['#ffffff', '#ffffff', 1.0],
    'mid grey on white' => ['#767676', '#ffffff', 4.54],
]);

it('is symmetric', function (): void {
    expect(Color::contrast('#123456', '#ffffff'))->toBe(Color::contrast('#ffffff', '#123456'));
});

it('refuses a combination no scanner will read', function (): void {
    expect(fn (): Options => Options::fromArray(['foreground' => '#eeeeee']))
        ->toThrow(QrException::class, 'below the 3.00:1 minimum');
});

it('allows low contrast when the guard is switched off', function (): void {
    expect(Options::fromArray(['foreground' => '#eeeeee', 'min_contrast' => 0])->foreground)
        ->toBe('#eeeeee');
});

it('honours a stricter threshold', function (): void {
    // 4.02:1 clears the default 3.0 but not a raised 4.5.
    expect(Options::fromArray(['foreground' => '#767676', 'min_contrast' => 4.5])->contrastRatio())
        ->toBeGreaterThan(4.5);

    expect(fn (): Options => Options::fromArray(['foreground' => '#949494', 'min_contrast' => 4.5]))
        ->toThrow(QrException::class);
});

it('measures a transparent background against white', function (): void {
    $options = Options::fromArray(['background' => null]);

    expect($options->contrastRatio())->toBe(Color::contrast('#000000', '#ffffff'));
});

it('still rejects a pale foreground on a transparent background', function (): void {
    expect(fn (): Options => Options::fromArray(['foreground' => '#f5f5f5', 'background' => null]))
        ->toThrow(QrException::class);
});

it('judges a gradient by its weaker stop', function (): void {
    // The dark stop would pass on its own; the pale one is what decides.
    expect(fn (): Options => Options::fromArray([
        'gradient' => ['from' => '#000000', 'to' => '#f0f0f0'],
    ]))->toThrow(QrException::class, 'below the 3.00:1 minimum');
});

it('accepts a gradient where both stops are dark enough', function (): void {
    $options = Options::fromArray([
        'gradient' => ['from' => '#4338ca', 'to' => '#be185d'],
    ]);

    expect($options->contrastRatio())->toBeGreaterThan(3.0);
});
