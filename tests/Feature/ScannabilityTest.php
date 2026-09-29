<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\Scannability;

it('reports a plain code as good', function (): void {
    $report = Qr::make(content())->scannability();

    expect($report)->toBeInstanceOf(Scannability::class)
        ->and($report->verdict())->toBe('good')
        ->and($report->modulesErased)->toBe(0)
        ->and($report->budgetUsed())->toBe(0.0)
        ->and($report->budgetRemaining())->toBe(1.0)
        ->and($report->contrastRatio)->toBe(21.0);
});

it('reports the version and matrix the encoder chose', function (): void {
    $report = Qr::make(content())->scannability();

    expect($report->version)->toBeGreaterThan(0)
        ->and($report->matrixSize)->toBe($report->version * 4 + 17);
});

it('counts the modules a logo erased and charges them to the budget', function (): void {
    $plain = Qr::make(content())->scannability();
    $logo = Qr::make(content())->logo(temporaryPng())->scannability();

    expect($logo->modulesErased)->toBeGreaterThan(0)
        ->and($plain->modulesErased)->toBe(0)
        ->and($logo->budgetUsed())->toBeGreaterThan(0.0)
        ->and($logo->budgetRemaining())->toBeLessThan(1.0);
});

it('reports the raised correction level for a logo', function (): void {
    $report = Qr::make(content())->errorCorrection('L')->logo(temporaryPng())->scannability();

    expect($report->ecc)->toBe(Ecc::H)
        ->and($report->eccRaised)->toBeTrue()
        ->and(implode(' ', $report->reasons()))->toContain('raised from L to H');
});

it('never reports more than the whole budget as spent', function (): void {
    $report = new Scannability(Ecc::L, false, 2, 25, 100, 100, 21.0, 1.0, false);

    expect($report->budgetUsed())->toBe(1.0)
        ->and($report->budgetRemaining())->toBe(0.0);
});

it('works out the physical module size at a print width', function (): void {
    $report = Qr::make(content())->scannability();

    expect($report->moduleSizeMm(50.0))->toBe(50.0 / $report->matrixSize)
        ->and($report->minimumPrintWidthMm())->toBe($report->matrixSize * 0.4);
});

it('calls thin contrast marginal and very thin contrast risky', function (float $contrast, string $verdict): void {
    $report = new Scannability(Ecc::M, false, 2, 25, 300, 0, $contrast, 1.0, false);

    expect($report->verdict())->toBe($verdict);
})->with([
    [21.0, 'good'],
    [4.4, 'marginal'],
    [2.9, 'risky'],
]);

it('calls a heavily shrunk module risky', function (float $scale, string $verdict): void {
    $report = new Scannability(Ecc::M, false, 2, 25, 300, 0, 21.0, $scale, false);

    expect($report->verdict())->toBe($verdict);
})->with([
    [1.0, 'good'],
    [0.6, 'marginal'],
    [0.4, 'risky'],
]);

it('calls a nearly exhausted budget risky', function (): void {
    $report = new Scannability(Ecc::M, false, 2, 25, 1000, 148, 21.0, 1.0, false);

    expect($report->budgetUsed())->toBeGreaterThan(0.9)
        ->and($report->verdict())->toBe('risky');
});

it('notes that a transparent background was judged against white', function (): void {
    $report = Qr::make(content())->transparent()->scannability();

    expect($report->transparentBackground)->toBeTrue()
        ->and(implode(' ', $report->reasons()))->toContain('measured against white');
});

it('serialises to an array a README can print', function (): void {
    $report = Qr::make(content())->modules('dots', 0.9)->scannability();

    expect($report->toArray())->toHaveKeys([
        'verdict', 'ecc', 'ecc_raised', 'version', 'matrix_size',
        'modules_total', 'modules_erased', 'budget_used', 'budget_remaining',
        'contrast_ratio', 'module_scale', 'transparent_background',
        'minimum_print_width_mm', 'reasons',
    ]);
});

it('does not need a render to report', function (): void {
    // The report encodes but never rasterises, so it is cheap enough to
    // call on every code in a batch.
    expect(Qr::make(content())->modules('dots')->scannability()->verdict())->toBeString();
});

it('agrees with the scan-back suite on what is readable', function (): void {
    // Every style the suite decodes must be reported as good or marginal;
    // if one of them came back risky the thresholds would be wrong.
    foreach (['square', 'rounded', 'dots', 'diamond', 'vertical', 'horizontal'] as $style) {
        expect(Qr::make(content())->modules($style)->scannability()->verdict())
            ->toBeIn(['good', 'marginal']);
    }
});
