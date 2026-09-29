<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\Renderers\GdRenderer;
use Gabrielesbaiz\QrcodeToolkit\Renderers\PngRenderer;
use Gabrielesbaiz\QrcodeToolkit\Renderers\SvgRenderer;

beforeEach(function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('The gd extension is not loaded.');
    }
});

it('writes a real PNG', function (): void {
    $png = Qr::make(content())->toPng();

    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n");

    $info = getimagesizefromstring($png);

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(300)
        ->and($info[2])->toBe(IMAGETYPE_PNG);
});

it('honours the requested size', function (): void {
    $info = getimagesizefromstring(Qr::make(content())->size(180)->toPng());

    expect($info[0])->toBe(180);
});

it('makes a captioned code taller than it is wide', function (): void {
    $info = getimagesizefromstring(Qr::make(content())->label('Ticket')->toPng());

    expect($info[1])->toBeGreaterThan($info[0]);
});

it('renders every module and eye style', function (string $module, string $eye): void {
    $png = Qr::make(content())->modules($module)->eyes($eye)->toPng();

    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
})->with(
    array_map(static fn (ModuleStyle $s): string => $s->value, ModuleStyle::cases()),
)->with(
    array_map(static fn (EyeStyle $s): string => $s->value, EyeStyle::cases()),
);

/**
 * Read the alpha values along a horizontal line through an image.
 *
 * @return array<int, int>
 */
function alphaRow(string $png, float $fraction): array
{
    $image = imagecreatefromstring($png);
    $y = (int) round(imagesy($image) * $fraction);

    $alphas = [];

    for ($x = 0; $x < imagesx($image); $x++) {
        $alphas[] = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }

    imagedestroy($image);

    return $alphas;
}

/**
 * Collapse a run of values into the sequence of distinct values seen.
 *
 * @param  array<int, int>  $values
 * @return array<int, int>
 */
function runs(array $values): array
{
    $out = [];

    foreach ($values as $value) {
        if ($out === [] || end($out) !== $value) {
            $out[] = $value;
        }
    }

    return $out;
}

it('punches a genuinely transparent hole in the eye', function (): void {
    $png = Qr::make(content())->transparent()->eyes('square')->margin(4)->size(300)->toPng();

    // A line through the middle of the top-left eye crosses quiet zone,
    // ring, cut-out, pupil, cut-out, ring. Whatever the symbol version,
    // the alpha along it has to alternate rather than stay opaque: an
    // opaque cut-out is the classic GD even-odd bug, and it looks right
    // on a white page while being wrong on every other background.
    $sequence = runs(alphaRow($png, 0.18));

    expect($sequence)->toContain(127)
        ->and($sequence)->toContain(0)
        ->and(count($sequence))->toBeGreaterThanOrEqual(5);
});

it('keeps the whole code opaque on a solid background', function (): void {
    $png = Qr::make(content())->background('#ffffff')->eyes('square')->margin(4)->size(300)->toPng();

    expect(array_unique(alphaRow($png, 0.18)))->toBe([0]);
});

it('paints a gradient across the modules', function (): void {
    $renderer = new GdRenderer;
    $renderer->render(layoutFor(['gradient' => ['from' => '#4338ca', 'to' => '#be185d'], 'modules' => 'dots']));

    expect(implode(' ', $renderer->warnings()))->toContain('sampled at its centre');
});

it('renders the same bytes twice', function (): void {
    expect(Qr::make(content())->modules('dots')->toPng())
        ->toBe(Qr::make(content())->modules('dots')->toPng());
});

it('resolves the driver by probing rather than by asking', function (): void {
    $renderer = new PngRenderer(new SvgRenderer, 'auto');

    expect($renderer->resolve())->toBeIn(['gd', 'imagick']);
});

it('throws rather than downgrading when a named driver cannot work', function (): void {
    PngRenderer::forgetCapability();

    if (PngRenderer::imagickCanRasteriseSvg()) {
        expect((new PngRenderer(new SvgRenderer, 'imagick'))->resolve())->toBe('imagick');

        return;
    }

    expect(fn (): string => (new PngRenderer(new SvgRenderer, 'imagick'))->resolve())
        ->toThrow(QrException::class, 'cannot rasterise SVG');
});

it('rejects an unknown driver name', function (): void {
    expect(fn (): string => (new PngRenderer(new SvgRenderer, 'cairo'))->resolve())
        ->toThrow(QrException::class, 'png_driver');
});

it('composites a logo into the middle', function (): void {
    $png = Qr::make(content())->logo(temporaryPng())->size(300)->toPng();

    $image = imagecreatefromstring($png);
    $centre = imagecolorat($image, 150, 150);
    imagedestroy($image);

    // The logo is solid orange, so the centre must not be black or white.
    $r = ($centre >> 16) & 0xFF;
    $g = ($centre >> 8) & 0xFF;
    $b = $centre & 0xFF;

    expect($r)->toBeGreaterThan(200)->and($g)->toBeGreaterThan(80)->and($b)->toBeLessThan(80);
});
