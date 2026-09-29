<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;

/**
 * The documentation site quotes real numbers. These are where they came from.
 */
it('quotes the scannability report accurately', function (): void {
    $report = Qr::make('https://laravel.com')
        ->modules('dots', 0.9)
        ->logo(temporaryPng())
        ->scannability();

    expect($report->ecc->value)->toBe('H')
        ->and($report->eccRaised)->toBeTrue()
        ->and($report->version)->toBe(3)
        ->and($report->matrixSize)->toBe(29)
        ->and($report->modulesErased)->toBe(25)
        ->and(round($report->budgetUsed(), 2))->toBe(0.25)
        ->and(round($report->budgetRemaining(), 2))->toBe(0.75)
        ->and($report->contrastRatio)->toBe(21.0)
        ->and(round($report->minimumPrintWidthMm(), 1))->toBe(11.6)
        ->and(round($report->moduleSizeMm(50.0), 2))->toBe(1.72);

    $array = $report->toArray();

    expect($array['modules_total'])->toBe(328)
        ->and($array['budget_used'])->toBe(0.2541)
        ->and($array['budget_remaining'])->toBe(0.7459)
        ->and($array['minimum_print_width_mm'])->toBe(11.6);
});
