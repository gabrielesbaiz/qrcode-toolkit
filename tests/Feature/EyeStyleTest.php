<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\Options;

it('offers at least as many eye shapes as the encoder underneath', function (): void {
    // bacon ships SquareEye, SimpleCircleEye, PointyEye, ModuleEye and
    // CompositeEye. Shipping fewer shapes than the library we depend on
    // would be hard to defend in a README.
    expect(EyeStyle::cases())->toHaveCount(6);
});

it('draws a pointy eye with one rounded corner and a round pupil', function (): void {
    expect(EyeStyle::Pointy->outerRadii())->toBe([3.5, 0.0, 0.0, 0.0])
        ->and(EyeStyle::Pointy->cutoutRadii())->toBe([2.5, 2.5, 2.5, 2.5])
        ->and(EyeStyle::Pointy->pupilRadii())->toBe([1.5, 1.5, 1.5, 1.5]);
});

it('builds a module eye out of modules rather than a ring', function (): void {
    $layout = layoutFor(['eyes' => 'module', 'modules' => 'dots']);

    expect($layout->eyeRings)->toBeEmpty()
        ->and($layout->pupils)->toBeEmpty()
        ->and($layout->eyeModules)->toHaveCount(3)
        ->and($layout->pupilModules)->toHaveCount(3);

    // A finder ring is 7x7 minus its 5x5 interior, and a pupil is 3x3.
    expect($layout->eyeModules[0])->toHaveCount(24)
        ->and($layout->pupilModules[0])->toHaveCount(9);
});

it('keeps the ring and pupil for every other eye style', function (EyeStyle $style): void {
    $layout = layoutFor(['eyes' => $style->value]);

    expect($layout->eyeRings)->toHaveCount(3)
        ->and($layout->pupils)->toHaveCount(3)
        ->and($layout->eyeModules)->toBeEmpty();
})->with(array_filter(EyeStyle::cases(), fn (EyeStyle $s): bool => ! $s->usesModules()));

it('draws module eyes in the body shape', function (): void {
    // A dotted code gets dotted eyes; a square one gets square eyes.
    $dots = layoutFor(['eyes' => 'module', 'modules' => 'dots']);
    $square = layoutFor(['eyes' => 'module', 'modules' => 'square']);

    expect($dots->eyeModules[0][0]->tl)->toBeGreaterThan(0.0)
        ->and($square->eyeModules[0][0]->tl)->toBe(0.0);
});

it('takes one eye colour or exactly three', function (): void {
    $one = Options::fromArray(['eye_color' => '#b91c1c']);
    $three = Options::fromArray(['eye_color' => ['#b91c1c', '#1d4ed8', '#15803d']]);

    expect($one->resolvedEyeColors())->toBe(['#b91c1c', '#b91c1c', '#b91c1c'])
        ->and($three->resolvedEyeColors())->toBe(['#b91c1c', '#1d4ed8', '#15803d'])
        ->and($one->eyesShareOneColour())->toBeTrue()
        ->and($three->eyesShareOneColour())->toBeFalse();
});

it('refuses a list that is not one per finder pattern', function (array $colors): void {
    expect(fn (): Options => Options::fromArray(['eye_color' => $colors]))
        ->toThrow(QrException::class, 'one per finder pattern');
})->with([
    'two' => [['#000000', '#111111']],
    'four' => [['#000000', '#111111', '#222222', '#333333']],
]);

it('emits one path per eye only when the colours differ', function (): void {
    $same = Qr::make(content())->eyes('rounded', '#b91c1c')->toSvg();
    $different = Qr::make(content())->with(['eyes' => 'rounded', 'eye_color' => ['#b91c1c', '#1d4ed8', '#15803d']])->toSvg();

    // Sharing a colour keeps the single even-odd path it has always used.
    expect(substr_count($same, 'fill-rule="evenodd"'))->toBe(1)
        ->and(substr_count($different, 'fill-rule="evenodd"'))->toBe(3)
        ->and($different)->toContain('#b91c1c')->toContain('#1d4ed8')->toContain('#15803d');
});

it('colours each pupil independently too', function (): void {
    $svg = Qr::make(content())->with([
        'eyes' => 'circle',
        'eye_inner_color' => ['#b91c1c', '#1d4ed8', '#15803d'],
    ])->toSvg();

    expect($svg)->toContain('#b91c1c')->toContain('#1d4ed8')->toContain('#15803d');
});

it('renders per-eye colours in every format', function (string $format): void {
    $payload = Qr::make(content())
        ->with(['eye_color' => ['#b91c1c', '#1d4ed8', '#15803d']])
        ->render($format);

    expect($payload)->not->toBeEmpty();
})->with(['svg', 'png', 'pdf', 'eps']);

it('still renders module eyes in every format', function (string $format): void {
    $payload = Qr::make(content())->eyes('module')->modules('dots')->render($format);

    expect($payload)->not->toBeEmpty();
})->with(['svg', 'png', 'pdf', 'eps']);
