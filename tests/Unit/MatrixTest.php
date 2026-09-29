<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Options;
use Gabrielesbaiz\QrcodeToolkit\Support\Encoder;
use Gabrielesbaiz\QrcodeToolkit\Support\MaskedMatrix;
use Gabrielesbaiz\QrcodeToolkit\Support\Matrix;

it('reports a square matrix the encoder produced', function (): void {
    $matrix = Encoder::encode(content(), Options::fromArray([]));

    expect($matrix->size())->toBeGreaterThan(20)
        ->and($matrix->size() % 4)->toBe(1);
});

it('places a finder pattern in three corners and not the fourth', function (): void {
    $matrix = Encoder::encode(content(), Options::fromArray([]));
    $n = $matrix->size();

    expect($matrix->isFinder(0, 0))->toBeTrue()
        ->and($matrix->isFinder(6, 6))->toBeTrue()
        ->and($matrix->isFinder($n - 1, 0))->toBeTrue()
        ->and($matrix->isFinder(0, $n - 1))->toBeTrue()
        ->and($matrix->isFinder($n - 1, $n - 1))->toBeFalse()
        ->and($matrix->isFinder(7, 7))->toBeFalse();
});

it('never counts a finder module as body', function (): void {
    $matrix = Encoder::encode(content(), Options::fromArray([]));
    $n = $matrix->size();

    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($matrix->isFinder($x, $y)) {
                expect($matrix->isBody($x, $y))->toBeFalse();
            }
        }
    }
});

it('treats out of bounds as light', function (): void {
    $matrix = Matrix::fromRows([[true, true], [true, true]]);

    expect($matrix->isDark(-1, 0))->toBeFalse()
        ->and($matrix->isDark(0, -1))->toBeFalse()
        ->and($matrix->isDark(2, 0))->toBeFalse();
});

it('counts dark modules', function (): void {
    expect(Matrix::fromRows([[true, false], [true, true]])->darkCount())->toBe(3);
});

it('hides the modules the logo covers', function (): void {
    $rows = array_fill(0, 21, array_fill(0, 21, true));
    $matrix = Matrix::fromRows($rows);

    // A two by two hole in the middle, clear of every finder pattern.
    $masked = new MaskedMatrix($matrix, [9.0, 9.0, 2.0, 2.0]);

    expect($masked->isBody(9, 9))->toBeFalse()
        ->and($masked->isBody(10, 10))->toBeFalse()
        ->and($masked->isBody(8, 9))->toBeTrue()
        ->and($masked->isBody(11, 9))->toBeTrue()
        ->and($masked->erasedCount())->toBe(4);
});

it('tests the whole cell against the logo, not the drawn shape', function (): void {
    $matrix = Matrix::fromRows(array_fill(0, 21, array_fill(0, 21, true)));

    // A hole that only clips the edge of a cell still removes it, so a
    // scaled-down module cannot peek out from under the logo.
    $masked = new MaskedMatrix($matrix, [9.5, 9.5, 1.0, 1.0]);

    expect($masked->isBody(9, 9))->toBeFalse()
        ->and($masked->isBody(10, 10))->toBeFalse();
});

it('keeps the finder patterns visible through the mask', function (): void {
    $matrix = Matrix::fromRows(array_fill(0, 21, array_fill(0, 21, true)));
    $masked = new MaskedMatrix($matrix, [0.0, 0.0, 21.0, 21.0]);

    expect($masked->isFinder(0, 0))->toBeTrue();
});
