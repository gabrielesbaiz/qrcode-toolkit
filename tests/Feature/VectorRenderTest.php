<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\Renderers\EpsRenderer;
use Gabrielesbaiz\QrcodeToolkit\Renderers\PdfRenderer;

it('writes a PDF header and trailer', function (): void {
    $pdf = Qr::make(content())->toPdf();

    expect($pdf)->toStartWith('%PDF-1.4')
        ->toContain('/Type /Catalog')
        ->toContain('/Type /Pages')
        ->toContain('/Type /Page ')
        ->toContain('startxref')
        ->toEndWith("%%EOF\n");
});

it('writes an xref table whose offsets point at their objects', function (): void {
    $pdf = Qr::make(content())->toPdf();

    preg_match('/startxref\n(\d+)\n/', $pdf, $start);
    expect($start)->not->toBeEmpty();

    $xref = substr($pdf, (int) $start[1]);
    expect($xref)->toStartWith("xref\n");

    preg_match_all('/^(\d{10}) 00000 n $/m', $xref, $entries);

    expect($entries[1])->toHaveCount(5);

    foreach ($entries[1] as $index => $offset) {
        // Every offset must land exactly on its object header.
        expect(substr($pdf, (int) $offset, strlen((string) ($index + 1)) + 7))
            ->toBe(($index + 1).' 0 obj'."\n");
    }
});

it('makes every xref entry exactly twenty bytes', function (): void {
    $pdf = Qr::make(content())->toPdf();
    $xref = substr($pdf, (int) strpos($pdf, "xref\n"));

    foreach (explode("\n", $xref) as $line) {
        if (preg_match('/^\d{10} \d{5} [nf]/', $line) === 1) {
            expect(strlen($line) + 1)->toBe(20);
        }
    }
});

it('declares a media box matching the requested size', function (): void {
    $pdf = Qr::make(content())->size(400)->toPdf();

    expect($pdf)->toContain('/MediaBox [0 0 400 400]');
});

it('uses even-odd fill for the eyes', function (): void {
    expect(Qr::make(content())->toPdf())->toContain(' f*');
});

it('writes an EPS header with a bounding box', function (): void {
    $eps = Qr::make(content())->size(300)->toEps();

    expect($eps)->toStartWith('%!PS-Adobe-3.0 EPSF-3.0')
        ->toContain('%%BoundingBox: 0 0 300 300')
        ->toContain('%%EndProlog')
        ->toContain('eofill')
        ->toContain('showpage')
        ->toEndWith("%%EOF\n");
});

it('escapes parentheses in a caption so the string object stays balanced', function (): void {
    $pdf = Qr::make(content())->label('Ticket (A-12)')->toPdf();

    expect($pdf)->toContain('(Ticket \\(A-12\\)) Tj');
});

it('octal-escapes a non-ASCII caption byte', function (): void {
    expect(Qr::make(content())->label("Caf\u{00e9}")->toPdf())->toMatch('/\\\\\d{3}/');
});

it('keeps a newline out of an EPS comment', function (): void {
    $eps = Qr::make(content())->label("one\ntwo")->toEps();

    foreach (explode("\n", $eps) as $line) {
        if (str_starts_with($line, '%%')) {
            expect($line)->not->toContain('two');
        }
    }
});

it('flattens a gradient to its first stop and says so', function (): void {
    $renderer = new PdfRenderer;
    $renderer->render(layoutFor(['gradient' => ['from' => '#4338ca', 'to' => '#be185d']]));

    expect($renderer->warnings())->toContain('A gradient cannot be expressed in PDF, so the body was filled with the first stop (#4338ca).');
});

it('emits CMYK operators when a slot is given press values', function (): void {
    $pdf = Qr::make(content())->cmyk('foreground', [0, 0, 0, 100])->toPdf();

    expect($pdf)->toContain(' k')->not->toContain('0 0 0 rg');

    $eps = Qr::make(content())->cmyk('foreground', [0, 0, 0, 100])->toEps();

    expect($eps)->toContain('cmyk')->toContain('setcmykcolor');
});

it('warns when it has to convert a colour to CMYK itself', function (): void {
    $renderer = new EpsRenderer;
    $renderer->render(layoutFor(['cmyk' => ['foreground' => [0, 0, 0, 1]]]));

    expect(implode(' ', $renderer->warnings()))->toContain('No CMYK value was given for [background]');
});

it('uses RGB when no CMYK values were given at all', function (): void {
    expect(Qr::make(content())->toPdf())->toContain(' rg');
});
