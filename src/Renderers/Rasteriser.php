<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Geometry\PathOp;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Polygon;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;

/**
 * Turns shapes into point lists.
 *
 * GD cannot draw an arc as part of a filled path, so every curve has to
 * become a run of short straight segments. The control points come from the
 * same helper the vector writers use, which is what keeps the raster output
 * the same shape as the vector output rather than merely a similar one.
 */
final class Rasteriser
{
    /**
     * The most segments any one corner is broken into.
     */
    private const MAX_SEGMENTS = 24;

    /**
     * Flatten a shape into a list of points, in output units.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function points(Shape|Polygon $shape, float $unit): array
    {
        if ($shape instanceof Polygon) {
            return array_map(
                static fn (array $point): array => [$point[0] * $unit, $point[1] * $unit],
                $shape->points,
            );
        }

        $scaled = new Shape(
            $shape->x * $unit, $shape->y * $unit, $shape->w * $unit, $shape->h * $unit,
            $shape->tl * $unit, $shape->tr * $unit, $shape->br * $unit, $shape->bl * $unit,
        );

        $points = [];
        $x = 0.0;
        $y = 0.0;

        foreach (PathWriter::ops([$scaled]) as $op) {
            switch ($op->op) {
                case PathOp::MOVE:
                case PathOp::LINE:
                    $points[] = [$op->x, $op->y];
                    $x = $op->x;
                    $y = $op->y;
                    break;

                case PathOp::CORNER:
                    foreach (PathWriter::flattenCorner($x, $y, $op, self::segments($op->r)) as $point) {
                        $points[] = $point;
                    }

                    $x = $op->x;
                    $y = $op->y;
                    break;
            }
        }

        return $points;
    }

    /**
     * Flatten a point list into the interleaved array GD wants.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, int>
     */
    public static function flatten(array $points): array
    {
        $out = [];

        foreach ($points as [$x, $y]) {
            $out[] = (int) round($x);
            $out[] = (int) round($y);
        }

        return $out;
    }

    /**
     * Choose how finely to break a corner, from how big it is on screen.
     */
    private static function segments(float $radiusInPixels): int
    {
        return max(3, min(self::MAX_SEGMENTS, (int) ceil($radiusInPixels / 1.5)));
    }
}
