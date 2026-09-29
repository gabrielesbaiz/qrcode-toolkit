<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;
use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\QrBuilder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

it('never mutates the builder it was called on', function (): void {
    $original = Qr::make(content());
    $changed = $original->size(500)->modules('dots');

    expect($original->options()->size)->toBe(300)
        ->and($original->options()->modules)->toBe(ModuleStyle::Square)
        ->and($changed->options()->size)->toBe(500)
        ->and($changed)->not->toBe($original);
});

it('accepts an enum or a string for every enum setter', function (): void {
    expect(Qr::make(content())->modules(ModuleStyle::Dots)->options()->modules)
        ->toBe(Qr::make(content())->modules('dots')->options()->modules);

    expect(Qr::make(content())->errorCorrection(Ecc::H)->options()->ecc)
        ->toBe(Qr::make(content())->errorCorrection('h')->options()->ecc);
});

it('names the valid values when given a bad one', function (): void {
    expect(fn () => Qr::make(content())->modules('sparkles'))
        ->toThrow(QrException::class, 'Expected one of: square, rounded, dots, diamond, vertical, horizontal.');
});

it('clears the gradient when a flat colour is set', function (): void {
    $builder = Qr::make(content())->gradient('#4338ca', '#be185d')->color('#0f172a');

    expect($builder->options()->gradient)->toBeNull()
        ->and($builder->options()->foreground)->toBe('#0f172a');
});

it('treats transparent as a null background', function (): void {
    expect(Qr::make(content())->transparent()->options()->background)->toBeNull()
        ->and(Qr::make(content())->transparent(false)->options()->background)->toBe('#ffffff');
});

it('marks an explicitly set correction level as explicit', function (): void {
    expect(Qr::make(content())->errorCorrection('L')->options()->eccExplicit)->toBeTrue()
        ->and(Qr::make(content())->options()->eccExplicit)->toBeFalse();
});

it('is stringable and htmlable as inline SVG', function (): void {
    $builder = Qr::make(content());

    expect($builder->toHtml())->toStartWith('<?xml')
        ->and((string) $builder)->toBe($builder->toHtml())
        ->and($builder->toHtml())->toContain('<svg');
});

it('builds a data URI in both encodings', function (): void {
    expect(Qr::make(content())->toDataUri())->toStartWith('data:image/svg+xml;base64,')
        ->and(Qr::make(content())->toDataUri('svg', false))->toStartWith('data:image/svg+xml;charset=UTF-8,')
        ->and(Qr::make(content())->toDataUri('pdf'))->toStartWith('data:application/pdf;base64,');
});

it('builds an img element with sensible defaults', function (): void {
    $img = Qr::make(content())->toImg(['alt' => 'Scan & go', 'class' => 'rounded']);

    expect($img)->toBeInstanceOf(HtmlString::class)
        ->and((string) $img)->toContain('loading="lazy"')
        ->toContain('decoding="async"')
        ->toContain('class="rounded"')
        ->toContain('alt="Scan &amp; go"');
});

it('refuses an attribute name that is not one', function (): void {
    expect(fn () => Qr::make(content())->toImg(['on error' => 'x']))
        ->toThrow(QrException::class, 'not a valid HTML attribute name');
});

it('sets the right content type and hardening headers per format', function (string $format, string $contentType): void {
    $response = Qr::make(content())->response($format);

    expect($response->headers->get('Content-Type'))->toBe($contentType)
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Disposition'))->toBe('inline')
        ->and($response->headers->get('ETag'))->not->toBeNull()
        ->and($response->getStatusCode())->toBe(200);
})->with([
    ['svg', 'image/svg+xml; charset=UTF-8'],
    ['png', 'image/png'],
    ['pdf', 'application/pdf'],
    ['eps', 'application/postscript'],
]);

it('sandboxes an SVG response', function (): void {
    expect(Qr::make(content())->response()->headers->get('Content-Security-Policy'))
        ->toContain('sandbox');
});

it('serves as a response straight from a controller', function (): void {
    $response = Qr::make(content())->toResponse(request());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('image/svg+xml; charset=UTF-8');
});

it('adds the right extension to a download', function (string $given, string $format, string $expected): void {
    $disposition = Qr::make(content())->download($given, $format)->headers->get('Content-Disposition');

    expect($disposition)->toContain('attachment')->toContain($expected);
})->with([
    ['ticket', 'png', 'ticket.png'],
    ['ticket.png', 'png', 'ticket.png'],
    ['invoice', 'pdf', 'invoice.pdf'],
]);

it('strips a path out of a download filename', function (): void {
    $disposition = Qr::make(content())->download('../../etc/passwd', 'svg')->headers->get('Content-Disposition');

    expect($disposition)->not->toContain('..')->toContain('passwd.svg');
});

it('writes to the local filesystem', function (): void {
    $path = sys_get_temp_dir().'/qrt-'.uniqid().'/code.svg';

    expect(Qr::make(content())->save($path))->toBe($path)
        ->and(file_get_contents($path))->toContain('<svg');

    @unlink($path);
    @rmdir(dirname($path));
});

it('writes to a disk', function (): void {
    Storage::fake('codes');

    Qr::make(content())->save('codes/one.png', 'codes');

    Storage::disk('codes')->assertExists('codes/one.png');
    expect(substr((string) Storage::disk('codes')->get('codes/one.png'), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('refuses a path it cannot infer a format from', function (): void {
    expect(fn () => Qr::make(content())->save('/tmp/code.bmp'))
        ->toThrow(QrException::class, 'Cannot tell which format');
});

it('caches a render and reuses it', function (): void {
    $builder = Qr::make(content())->cache(60);

    $first = $builder->toPng();

    expect($builder->toPng())->toBe($first);

    // The payload survives a binary-hostile store because it is base64
    // encoded on the way in.
    expect(cache()->getStore())->not->toBeNull();
});

it('gives different options different cache keys', function (): void {
    $one = Qr::make(content())->cache(60)->size(300)->toSvg();
    $two = Qr::make(content())->cache(60)->size(400)->toSvg();

    expect($one)->not->toBe($two);
});

it('supports macros that return a builder', function (): void {
    QrBuilder::macro('brandish', fn (): QrBuilder => $this->modules('dots')->eyes('rounded'));

    $builder = Qr::make(content())->brandish()->size(400);

    expect($builder->options()->modules)->toBe(ModuleStyle::Dots)
        ->and($builder->options()->size)->toBe(400);
});

it('complains when a macro forgets to return', function (): void {
    QrBuilder::macro('forgetful', function (): void {});

    expect(fn () => Qr::make(content())->forgetful())
        ->toThrow(QrException::class, 'must return a value');
});

it('explains an unknown method rather than throwing a bare BadMethodCall', function (): void {
    expect(fn () => Qr::make(content())->sparkle())
        ->toThrow(QrException::class, 'has no method [sparkle]');
});

it('is conditionable', function (): void {
    $dark = Qr::make(content())->when(true, fn (QrBuilder $qr): QrBuilder => $qr->modules('dots'));
    $light = Qr::make(content())->when(false, fn (QrBuilder $qr): QrBuilder => $qr->modules('dots'));

    expect($dark->options()->modules)->toBe(ModuleStyle::Dots)
        ->and($light->options()->modules)->toBe(ModuleStyle::Square);
});

it('refuses to encode nothing', function (): void {
    expect(fn () => Qr::make('')->toSvg())->toThrow(QrException::class, 'nothing to encode');
});
