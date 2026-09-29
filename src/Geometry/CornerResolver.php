<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

use Gabrielesbaiz\QrcodeToolkit\Support\MatrixContract;

/**
 * Corner radii for the rounded module style.
 *
 * No run merging is needed. Neighbouring modules each draw a full unit
 * square, so their shared edge disappears of its own accord once both
 * facing corners have a radius of zero; the whole style is a local decision
 * per corner.
 */
final class CornerResolver
{
    /**
     * Work out the four radii for one module.
     *
     * The diagonal neighbour is deliberately not consulted. Two modules
     * touching only at a corner each keep their radius, so the join stays a
     * pinch instead of fusing, and that pinch is what people mean when they
     * say a QR code looks "smooth".
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function radii(MatrixContract $matrix, int $x, int $y, float $r): array
    {
        $up = $matrix->isBody($x, $y - 1);
        $down = $matrix->isBody($x, $y + 1);
        $left = $matrix->isBody($x - 1, $y);
        $right = $matrix->isBody($x + 1, $y);

        return [
            ! $up && ! $left ? $r : 0.0,
            ! $up && ! $right ? $r : 0.0,
            ! $down && ! $right ? $r : 0.0,
            ! $down && ! $left ? $r : 0.0,
        ];
    }
}
