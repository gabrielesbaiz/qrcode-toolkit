<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

/**
 * One operation in a path.
 *
 * A corner carries its elbow, the point where the two straight edges would
 * have met, as well as its end point. Without it every backend would have to
 * infer the elbow from the direction of travel; with it, the bezier control
 * points are two multiplications and the SVG, PDF, EPS and GD writers all
 * agree on the same curve.
 */
final readonly class PathOp
{
    public const MOVE = 'M';

    public const LINE = 'L';

    public const CORNER = 'R';

    public const CLOSE = 'Z';

    public function __construct(
        public string $op,
        public float $x = 0.0,
        public float $y = 0.0,
        public float $r = 0.0,
        public float $elbowX = 0.0,
        public float $elbowY = 0.0,
    ) {}

    /**
     * Start a new subpath.
     */
    public static function move(float $x, float $y): self
    {
        return new self(self::MOVE, $x, $y);
    }

    /**
     * Draw a straight line.
     */
    public static function line(float $x, float $y): self
    {
        return new self(self::LINE, $x, $y);
    }

    /**
     * Draw a quarter circle around an elbow.
     */
    public static function corner(float $x, float $y, float $r, float $elbowX, float $elbowY): self
    {
        return new self(self::CORNER, $x, $y, $r, $elbowX, $elbowY);
    }

    /**
     * Close the current subpath.
     */
    public static function close(): self
    {
        return new self(self::CLOSE);
    }
}
