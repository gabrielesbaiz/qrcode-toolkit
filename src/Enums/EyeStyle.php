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
    case Pointy = 'pointy';
    case Module = 'module';

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
            // One rounded corner and three square ones, which is the
            // shape people mean by a "pointy" eye.
            self::Pointy => [3.5, 0.0, 0.0, 0.0],
            self::Module => [0.0, 0.0, 0.0, 0.0],
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
            // A circular cut-out inside the pointed ring.
            self::Pointy => [2.5, 2.5, 2.5, 2.5],
            self::Module => [0.0, 0.0, 0.0, 0.0],
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
            self::Pointy => [1.5, 1.5, 1.5, 1.5],
            self::Module => [0.0, 0.0, 0.0, 0.0],
        };
    }

    /**
     * Determine whether the eye has a front.
     *
     * A circle looks the same whichever way round it is drawn, but a shape
     * with a corner has to decide which way that corner faces. These ones
     * turn with the finder pattern they belong to, so the point of each eye
     * aims at the corner of the symbol it sits in rather than all three
     * aiming the same way.
     */
    public function isOriented(): bool
    {
        return $this === self::Pointy || $this === self::Leaf;
    }

    /**
     * Get the quarter turns clockwise for one finder pattern.
     *
     * The order is the one finderOrigins() returns: top-left, top-right,
     * bottom-left. A symmetric style never turns, so the caller does not
     * have to ask whether it should.
     */
    public function quarterTurnsFor(int $index): int
    {
        if (! $this->isOriented()) {
            return 0;
        }

        return match ($index) {
            1 => 1,   // top-right: a quarter turn clockwise
            2 => 3,   // bottom-left: a quarter turn anticlockwise
            default => 0,
        };
    }

    /**
     * Turn a radius list clockwise.
     *
     * A rectangle's radii are listed from the top-left corner clockwise, so
     * one quarter turn moves each of them one place along: what was at the
     * top left ends up at the top right.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $radii
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function turn(array $radii, int $quarterTurns): array
    {
        $turns = ($quarterTurns % 4 + 4) % 4;

        for ($i = 0; $i < $turns; $i++) {
            $radii = [$radii[3], $radii[0], $radii[1], $radii[2]];
        }

        return $radii;
    }

    /**
     * Determine whether the style draws nothing but axis-aligned rectangles.
     */
    public function isPureSquare(): bool
    {
        return $this === self::Square;
    }

    /**
     * Determine whether the eye is drawn out of the module shape itself.
     *
     * A module eye is not a ring with a hole in it: the seven-by-seven
     * border and the three-by-three pupil are drawn as individual modules
     * in whatever shape the body uses, so a dotted code gets dotted eyes.
     */
    public function usesModules(): bool
    {
        return $this === self::Module;
    }
}
