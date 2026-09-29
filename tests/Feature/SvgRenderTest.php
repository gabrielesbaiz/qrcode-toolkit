<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Renderers\SvgRenderer;

function svg(array $options = []): string
{
    return (new SvgRenderer)->render(layoutFor($options));
}

it('produces a well formed document', function (): void {
    $svg = svg();

    expect($svg)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<svg xmlns="http://www.w3.org/2000/svg"')
        ->toContain('viewBox="0 0 ')
        ->toEndWith("</svg>\n");

    expect(simplexml_load_string($svg))->not->toBeFalse();
});

it('parses as XML for every module and eye style', function (string $module, string $eye): void {
    expect(simplexml_load_string(svg(['modules' => $module, 'eyes' => $eye])))->not->toBeFalse();
})->with(
    array_map(static fn (ModuleStyle $style): string => $style->value, ModuleStyle::cases()),
)->with(
    array_map(static fn (EyeStyle $style): string => $style->value, EyeStyle::cases()),
);

it('keeps the elements in a fixed order', function (): void {
    $svg = svg([
        'gradient' => ['from' => '#4338ca', 'to' => '#be185d'],
        'label' => 'Caption',
        'frame' => ['width' => 1],
    ]);

    $order = ['<defs>', '<rect', '<path', '<text', '<rect'];
    $position = 0;

    foreach ($order as $needle) {
        $found = strpos($svg, $needle, $position);
        expect($found)->not->toBeFalse();
        $position = (int) $found + 1;
    }
});

it('draws the eyes with an even-odd path', function (): void {
    expect(svg())->toContain('fill-rule="evenodd"');
});

it('only asks for crisp edges on a plain square grid', function (): void {
    expect(svg())->toContain('shape-rendering="crispEdges"')
        ->and(svg(['modules' => 'dots']))->not->toContain('crispEdges')
        ->and(svg(['eyes' => 'circle']))->not->toContain('crispEdges');
});

it('writes relative path data for a plain grid and absolute data otherwise', function (): void {
    expect(svg())->toContain('h1v1h-1')
        ->and(svg(['modules' => 'dots']))->toContain('A');
});

it('omits the background rectangle when transparent', function (): void {
    expect(svg(['background' => null]))->not->toContain('<rect x="0" y="0"');
});

it('defines a gradient spanning the symbol, not the canvas', function (): void {
    $svg = svg(['gradient' => ['from' => '#4338ca', 'to' => '#be185d'], 'margin' => 4]);

    expect($svg)->toContain('<linearGradient')
        ->toContain('gradientUnits="userSpaceOnUse"')
        ->toContain('stop-color="#4338ca"')
        ->toContain('stop-color="#be185d"');

    // At angle 0 the gradient runs straight across the symbol box, which
    // begins at the canvas edge and ends at the far side of the quiet zone.
    preg_match('/x1="([\d.-]+)" y1="([\d.-]+)" x2="([\d.-]+)" y2="([\d.-]+)"/', $svg, $m);

    expect((float) $m[1])->toBe(0.0)
        ->and((float) $m[2])->toBe((float) $m[4])
        ->and((float) $m[3])->toBeGreaterThan(0.0);
});

it('runs a 45 degree gradient corner to corner', function (): void {
    $layout = layoutFor(['gradient' => ['from' => '#4338ca', 'to' => '#be185d', 'angle' => 45], 'margin' => 0]);
    $svg = (new SvgRenderer)->render($layout);

    preg_match('/x1="([\d.-]+)" y1="([\d.-]+)" x2="([\d.-]+)" y2="([\d.-]+)"/', $svg, $m);

    expect((float) $m[1])->toBe(0.0)
        ->and((float) $m[2])->toBe(0.0)
        ->and((float) $m[3])->toBe((float) $layout->matrixSize)
        ->and((float) $m[4])->toBe((float) $layout->matrixSize);
});

it('uses a radial gradient element when asked', function (): void {
    expect(svg(['gradient' => ['from' => '#4338ca', 'to' => '#be185d', 'type' => 'radial']]))
        ->toContain('<radialGradient');
});

it('derives the gradient id from the options', function (): void {
    $options = ['gradient' => ['from' => '#4338ca', 'to' => '#be185d']];

    preg_match('/id="(qrg-[0-9a-f]+)"/', svg($options), $one);
    preg_match('/id="(qrg-[0-9a-f]+)"/', svg($options), $two);
    preg_match('/id="(qrg-[0-9a-f]+)"/', svg(array_merge($options, ['size' => 400])), $three);

    expect($one[1])->toBe($two[1])
        ->and($one[1])->not->toBe($three[1]);
});

it('escapes a caption rather than letting it into the markup', function (): void {
    $svg = svg(['label' => '<script>alert("x")</script> & co']);

    expect($svg)->not->toContain('<script>')
        ->toContain('&lt;script&gt;')
        ->toContain('&amp; co');

    expect(simplexml_load_string($svg))->not->toBeFalse();
});

it('keeps an invalid byte from blanking the caption', function (): void {
    $svg = svg(['label' => "Caf\xE9 bar"]);

    expect($svg)->toMatch('/<text[^>]*>.+<\/text>/');
});

it('restricts the font family to safe characters', function (): void {
    expect(svg(['label' => 'Hi']))->toContain('font-family="');
});

it('draws the frame last, stroked and unfilled', function (): void {
    $svg = svg(['frame' => ['color' => '#0f172a', 'width' => 1, 'radius' => 2]]);

    expect($svg)->toContain('stroke="#0f172a"')
        ->toContain('fill="none"')
        ->toContain('rx="2"');
});

it('colours the eyes separately when asked', function (): void {
    $svg = svg(['eye_color' => '#b91c1c', 'eye_inner_color' => '#1d4ed8']);

    expect($svg)->toContain('fill="#b91c1c" fill-rule="evenodd"')
        ->toContain('fill="#1d4ed8"');
});
