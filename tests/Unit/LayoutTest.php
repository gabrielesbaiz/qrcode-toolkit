<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Geometry\CornerResolver;
use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Polygon;
use Gabrielesbaiz\QrcodeToolkit\Geometry\RunMerger;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;
use Gabrielesbaiz\QrcodeToolkit\Support\Matrix;

it('sizes the canvas as matrix plus margin on both sides', function (): void {
    $layout = layoutFor(['margin' => 4]);

    expect($layout->width)->toBe((float) ($layout->matrixSize + 8))
        ->and($layout->height)->toBe($layout->width)
        ->and($layout->symbolX)->toBe(4.0);
});

it('makes a captioned code taller than it is wide', function (): void {
    $layout = layoutFor(['label' => 'Ticket 12']);

    expect($layout->height)->toBeGreaterThan($layout->width)
        ->and($layout->outputHeight())->toBeGreaterThan(300.0);
});

it('grows the canvas by the frame on every side', function (): void {
    $plain = layoutFor([]);
    $framed = layoutFor(['frame' => ['width' => 2]]);

    expect($framed->width)->toBe($plain->width + 4.0);
});

it('produces exactly three eyes and three pupils', function (): void {
    $layout = layoutFor([]);

    expect($layout->eyeRings)->toHaveCount(3)
        ->and($layout->pupils)->toHaveCount(3);
});

it('nests the eye ring, cut-out and pupil correctly', function (): void {
    $layout = layoutFor([]);
    $eye = $layout->eyeRings[0];
    $pupil = $layout->pupils[0];

    expect($eye['outer']->w)->toBe(7.0)
        ->and($eye['cutout']->w)->toBe(5.0)
        ->and($pupil->w)->toBe(3.0)
        ->and($eye['cutout']->x - $eye['outer']->x)->toBe(1.0)
        ->and($pupil->x - $eye['cutout']->x)->toBe(1.0);
});

it('draws a flush grid at full module scale', function (): void {
    $layout = layoutFor(['modules' => 'square']);

    /** @var Shape $first */
    $first = $layout->body[0];

    expect($first->w)->toBe(1.0)
        ->and($first->h)->toBe(1.0)
        ->and($first->isRectangular())->toBeTrue();
});

it('insets each module about its centre when scaled', function (): void {
    $layout = layoutFor(['modules' => 'square', 'module_scale' => 0.5]);

    /** @var Shape $first */
    $first = $layout->body[0];

    expect($first->w)->toBe(0.5)
        ->and(fmod($first->x, 1.0))->toBe(0.25);
});

it('emits the right shape for each module style', function (string $style, string $class): void {
    expect(layoutFor(['modules' => $style])->body[0])->toBeInstanceOf($class);
})->with([
    ['square', Shape::class],
    ['rounded', Shape::class],
    ['dots', Shape::class],
    ['diamond', Polygon::class],
    ['vertical', Shape::class],
    ['horizontal', Shape::class],
]);

it('merges runs into fewer shapes than there are modules', function (string $style): void {
    $plain = count(layoutFor(['modules' => 'square'])->body);
    $merged = count(layoutFor(['modules' => $style])->body);

    expect($merged)->toBeLessThan($plain);
})->with(['vertical', 'horizontal']);

it('merges a vertical run into one capsule', function (): void {
    $rows = array_fill(0, 21, array_fill(0, 21, false));

    foreach ([9, 10, 11] as $y) {
        $rows[$y][10] = true;
    }

    $shapes = RunMerger::vertical(Matrix::fromRows($rows), 1.0);

    expect($shapes)->toHaveCount(1)
        ->and($shapes[0]->h)->toBe(3.0)
        ->and($shapes[0]->w)->toBe(1.0);
});

it('turns a lone module into a circle under run merging', function (): void {
    $rows = array_fill(0, 21, array_fill(0, 21, false));
    $rows[10][10] = true;

    $shapes = RunMerger::vertical(Matrix::fromRows($rows), 1.0);

    expect($shapes[0]->w)->toBe($shapes[0]->h)
        ->and($shapes[0]->tl)->toBe(0.5);
});

it('rounds only the corners with no neighbour', function (): void {
    $rows = array_fill(0, 21, array_fill(0, 21, false));
    $rows[10][10] = true;
    $rows[10][11] = true;

    $matrix = Matrix::fromRows($rows);

    // The left cell of a horizontal pair keeps its left corners and
    // squares off the two facing its neighbour.
    [$tl, $tr, $br, $bl] = CornerResolver::radii($matrix, 10, 10, 0.5);

    expect($tl)->toBe(0.5)->and($bl)->toBe(0.5)
        ->and($tr)->toBe(0.0)->and($br)->toBe(0.0);
});

it('ignores diagonal neighbours when rounding', function (): void {
    $rows = array_fill(0, 21, array_fill(0, 21, false));
    $rows[10][10] = true;
    $rows[11][11] = true;

    // Two modules touching only at a corner keep the pinch between them.
    [$tl, $tr, $br, $bl] = CornerResolver::radii(Matrix::fromRows($rows), 10, 10, 0.5);

    expect([$tl, $tr, $br, $bl])->each->toBe(0.5);
});

it('knocks modules out under the logo and reports how many', function (): void {
    $plain = layoutFor([]);
    $withLogo = layoutFor(['logo' => 'logo.png', 'logo_size' => 0.25]);

    expect($withLogo->modulesErased)->toBeGreaterThan(0)
        ->and($withLogo->effectiveEcc)->toBe(Ecc::H)
        ->and($withLogo->warnings)->not->toBeEmpty()
        ->and($plain->modulesErased)->toBe(0);
});

it('sizes the logo against the symbol, not the canvas', function (): void {
    $tight = layoutFor(['logo' => 'logo.png', 'logo_size' => 0.2, 'margin' => 1]);
    $roomy = layoutFor(['logo' => 'logo.png', 'logo_size' => 0.2, 'margin' => 8]);

    expect($roomy->logoRect?->w)->toBe($tight->logoRect?->w);
});

it('refuses a logo that would cover a finder pattern', function (): void {
    expect(fn (): LayoutResult => layoutFor(['logo' => 'logo.png', 'logo_size' => 0.3, 'logo_padding' => 4, 'margin' => 0], 'hi'))
        ->toThrow(QrException::class, 'overlaps a finder pattern');
});

it('never scales the eyes with the modules', function (): void {
    $scaled = layoutFor(['module_scale' => 0.5]);

    expect($scaled->eyeRings[0]['outer']->w)->toBe(7.0);
});

it('centres the caption', function (): void {
    $layout = layoutFor(['label' => 'Scan me']);

    expect($layout->label?->x)->toBe($layout->width / 2);
});

it('reports the style it was built with', function (): void {
    expect(layoutFor(['modules' => 'dots'])->options->modules)->toBe(ModuleStyle::Dots);
});
