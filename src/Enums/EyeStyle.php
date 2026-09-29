<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Enums;

/**
 * The shape of the three finder patterns.
 *
 * Each style carries the corner radii for the three concentric parts of an
 * eye: the 7x7 outer ring, the 5x5 light cut-out inside it, and the 3x3
 * pupil at the centre. Holding them as data keeps the layout free of a match
 * on the style.
 */
enum EyeStyle: string
{
    case Square = 'square';
    case Rounded = 'rounded';
    case Circle = 'circle';
    case Leaf = 'leaf';

    /**
     * Get the corner radii of the 7x7 outer ring, in module units.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} top-left, top-right, bottom-right, bottom-left
     */
    public function outerRadii(): array
    {
        return match ($this) {
            self::Square => [0.0, 0.0, 0.0, 0.0],
            self::Rounded => [2.0, 2.0, 2.0, 2.0],
            self::Circle => [3.5, 3.5, 3.5, 3.5],
            self::Leaf => [3.5, 0.0, 3.5, 0.0],
        };
    }

    /**
     * Get the corner radii of the 5x5 cut-out, in module units.
     *
     * These are not simply the outer radii less one module. A concentric
     * offset would give the rounded style a radius of 1, which reads flat at
     * small sizes; 1.25 is a deliberate optical correction.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function cutoutRadii(): array
    {
        return match ($this) {
            self::Square => [0.0, 0.0, 0.0, 0.0],
            self::Rounded => [1.25, 1.25, 1.25, 1.25],
            self::Circle => [2.5, 2.5, 2.5, 2.5],
            self::Leaf => [2.5, 0.0, 2.5, 0.0],
        };
    }

    /**
     * Get the corner radii of the 3x3 pupil, in module units.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function pupilRadii(): array
    {
        return match ($this) {
            self::Square => [0.0, 0.0, 0.0, 0.0],
            self::Rounded => [0.75, 0.75, 0.75, 0.75],
            self::Circle => [1.5, 1.5, 1.5, 1.5],
            self::Leaf => [1.5, 0.0, 1.5, 0.0],
        };
    }

    /**
     * Determine whether the style draws nothing but axis-aligned rectangles.
     */
    public function isPureSquare(): bool
    {
        return $this === self::Square;
    }
}
