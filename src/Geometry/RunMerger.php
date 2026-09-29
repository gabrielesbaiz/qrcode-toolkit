<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

use Gabrielesbaiz\QrcodeToolkit\Support\MatrixContract;

/**
 * Merges neighbouring modules into capsules.
 *
 * Runs break at finder patterns and at the logo hole for free, because both
 * are already invisible to isBody().
 */
final class RunMerger
{
    /**
     * Merge every vertical run into one shape.
     *
     * @return array<int, Shape>
     */
    public static function vertical(MatrixContract $matrix, float $scale): array
    {
        return self::merge($matrix, $scale, vertical: true);
    }

    /**
     * Merge every horizontal run into one shape.
     *
     * @return array<int, Shape>
     */
    public static function horizontal(MatrixContract $matrix, float $scale): array
    {
        return self::merge($matrix, $scale, vertical: false);
    }

    /**
     * Walk one axis, collecting runs.
     *
     * @return array<int, Shape>
     */
    private static function merge(MatrixContract $matrix, float $scale, bool $vertical): array
    {
        $size = $matrix->size();
        $inset = (1.0 - $scale) / 2;
        $radius = $scale / 2;
        $shapes = [];

        for ($fixed = 0; $fixed < $size; $fixed++) {
            $moving = 0;

            while ($moving < $size) {
                $isBody = $vertical
                    ? $matrix->isBody($fixed, $moving)
                    : $matrix->isBody($moving, $fixed);

                if (! $isBody) {
                    $moving++;

                    continue;
                }

                $start = $moving;

                do {
                    $moving++;
                } while (
                    $moving < $size && ($vertical
                        ? $matrix->isBody($fixed, $moving)
                        : $matrix->isBody($moving, $fixed))
                );

                // A run of n cells, each inset on both sides, spans
                // n - 2 * inset. A run of one becomes a circle, which is how
                // these styles degrade gracefully for isolated modules.
                $length = ($moving - $start) - 2 * $inset;

                $shapes[] = $vertical
                    ? new Shape($fixed + $inset, $start + $inset, $scale, $length, $radius, $radius, $radius, $radius)
                    : new Shape($start + $inset, $fixed + $inset, $length, $scale, $radius, $radius, $radius, $radius);
            }
        }

        return $shapes;
    }
}
