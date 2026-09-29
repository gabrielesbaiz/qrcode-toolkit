<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Geometry\Path;
use Gabrielesbaiz\QrcodeToolkit\Geometry\PathOp;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Polygon;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;

/**
 * Shared conversion from shapes to path operations.
 */
final class PathWriter
{
    /**
     * The circle-to-bezier constant, 4/3 * (sqrt(2) - 1).
     */
    public const KAPPA = 0.5522847498307936;

    /**
     * Collect the operations for a list of shapes.
     *
     * @param  array<int, Shape|Polygon>  $shapes
     * @return array<int, PathOp>
     */
    public static function ops(array $shapes): array
    {
        $path = new Path;

        foreach ($shapes as $shape) {
            $shape->writeTo($path);
        }

        return $path->ops();
    }

    /**
     * Get the two cubic control points for a corner.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function controlPoints(float $fromX, float $fromY, PathOp $op): array
    {
        return [
            $fromX + self::KAPPA * ($op->elbowX - $fromX),
            $fromY + self::KAPPA * ($op->elbowY - $fromY),
            $op->x + self::KAPPA * ($op->elbowX - $op->x),
            $op->y + self::KAPPA * ($op->elbowY - $op->y),
        ];
    }

    /**
     * Flatten a corner into straight segments.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function flattenCorner(float $fromX, float $fromY, PathOp $op, int $segments): array
    {
        [$c1x, $c1y, $c2x, $c2y] = self::controlPoints($fromX, $fromY, $op);

        $points = [];

        for ($i = 1; $i <= $segments; $i++) {
            $t = $i / $segments;
            $u = 1 - $t;

            $points[] = [
                $u ** 3 * $fromX + 3 * $u ** 2 * $t * $c1x + 3 * $u * $t ** 2 * $c2x + $t ** 3 * $op->x,
                $u ** 3 * $fromY + 3 * $u ** 2 * $t * $c1y + 3 * $u * $t ** 2 * $c2y + $t ** 3 * $op->y,
            ];
        }

        return $points;
    }
}
