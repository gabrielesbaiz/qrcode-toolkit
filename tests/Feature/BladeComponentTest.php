<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\EyeStyle;
use Gabrielesbaiz\QrcodeToolkit\Enums\GradientType;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\View\Components\Qr as QrComponent;
use Illuminate\Support\Facades\Blade;

/**
 * Render a component tag through Blade.
 */
function render(string $template, array $data = []): string
{
    return (string) Blade::render($template, $data);
}

it('renders inline SVG inside a wrapper', function (): void {
    $html = render('<x-qr data="https://laravel.com" />');

    expect($html)->toContain('<svg')
        ->toContain('class="qrcode-toolkit"')
        ->toContain('</span>');
});

it('renders without a wrapper when bare', function (): void {
    $html = trim(render('<x-qr data="https://laravel.com" bare />'));

    expect($html)->toStartWith('<?xml')->not->toContain('<span');
});

it('renders an img when asked', function (): void {
    expect(render('<x-qr data="https://laravel.com" img />'))
        ->toContain('<img ')->toContain('data:image/svg+xml;base64,');
});

it('maps every simple attribute onto its option', function (): void {
    $component = new QrComponent(data: 'https://laravel.com');
    $component->withAttributes([
        'size' => '480',
        'margin' => '2',
        'error-correction' => 'H',
        'modules' => 'dots',
        'module-scale' => '0.8',
        'eyes' => 'circle',
        'color' => '#0f172a',
        'label' => 'Ticket',
        'label-size' => '12',
    ]);

    $options = $component->builder()->options();

    expect($options->size)->toBe(480)
        ->and($options->margin)->toBe(2)
        ->and($options->ecc->value)->toBe('H')
        ->and($options->modules)->toBe(ModuleStyle::Dots)
        ->and($options->moduleScale)->toBe(0.8)
        ->and($options->eyes)->toBe(EyeStyle::Circle)
        ->and($options->foreground)->toBe('#0f172a')
        ->and($options->label)->toBe('Ticket')
        ->and($options->labelSize)->toBe(12);
});

it('maps dashed attributes onto nested options', function (): void {
    $component = new QrComponent(data: 'https://laravel.com');
    $component->withAttributes([
        'gradient-from' => '#4338ca',
        'gradient-to' => '#be185d',
        'gradient-type' => 'radial',
        'gradient-angle' => '30',
        'frame-color' => '#0f172a',
        'frame-width' => '1.5',
        'frame-radius' => '2',
    ]);

    $options = $component->builder()->options();

    expect($options->gradient?->from)->toBe('#4338ca')
        ->and($options->gradient?->type)->toBe(GradientType::Radial)
        ->and($options->gradient?->angle)->toBe(30.0)
        ->and($options->frame?->color)->toBe('#0f172a')
        ->and($options->frame?->width)->toBe(1.5)
        ->and($options->frame?->radius)->toBe(2.0);
});

it('treats a bare frame attribute as the default frame', function (): void {
    $component = new QrComponent(data: 'https://laravel.com', frame: true);
    $component->withAttributes([]);

    expect($component->builder()->options()->frame)->not->toBeNull();
});

it('treats a bare transparent attribute as a null background', function (): void {
    $component = new QrComponent(data: 'https://laravel.com', transparent: true);
    $component->withAttributes([]);

    expect($component->builder()->options()->background)->toBeNull();
});

it('starts from a named profile', function (): void {
    $component = new QrComponent(data: 'https://laravel.com', profile: 'brand');
    $component->withAttributes([]);

    expect($component->builder()->options()->modules)->toBe(ModuleStyle::Dots);
});

it('lets an attribute override the profile', function (): void {
    $component = new QrComponent(data: 'https://laravel.com', profile: 'brand');
    $component->withAttributes(['modules' => 'square']);

    expect($component->builder()->options()->modules)->toBe(ModuleStyle::Square);
});

it('passes unmapped attributes through to the wrapper', function (): void {
    expect(render('<x-qr data="https://laravel.com" class="shadow" id="ticket" />'))
        ->toContain('id="ticket"')
        ->toContain('shadow');
});

it('hides a decorative code from assistive technology', function (): void {
    expect(render('<x-qr data="https://laravel.com" />'))
        ->toContain('role="presentation"')
        ->toContain('aria-hidden="true"');
});

it('labels a code that was given alt text', function (): void {
    $html = render('<x-qr data="https://laravel.com" alt="Ticket A-12" />');

    expect($html)->toContain('role="img"')
        ->toContain('aria-label="Ticket A-12"')
        ->toContain('<title>Ticket A-12</title>')
        ->not->toContain('aria-hidden');
});

it('escapes alt text it injects after rendering', function (): void {
    $html = render('<x-qr data="https://laravel.com" alt=\'Tom &amp; "Jerry"\' />');

    expect($html)->toContain('&amp;')->not->toContain('aria-label="Tom & "Jerry""');
});

it('takes the raw options array as an escape hatch', function (): void {
    $component = new QrComponent(data: 'https://laravel.com', options: ['eyes' => 'leaf']);
    $component->withAttributes([]);

    expect($component->builder()->options()->eyes)->toBe(EyeStyle::Leaf);
});

it('complains when there is nothing to encode', function (): void {
    // Blade wraps whatever a component throws in a ViewException, so the
    // message is the thing worth asserting on here.
    try {
        render('<x-qr />');
        $message = null;
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
    }

    expect($message)->toContain('needs a data attribute');
});

it('is registered under a namespaced tag as well', function (): void {
    expect(render('<x-qr::qr data="https://laravel.com" />'))->toContain('<svg');
});
