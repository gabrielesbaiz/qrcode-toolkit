<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Enums;

/**
 * The shape a single data module takes.
 */
enum ModuleStyle: string
{
    case Square = 'square';
    case Rounded = 'rounded';
    case Dots = 'dots';
    case Diamond = 'diamond';
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';

    /**
     * Determine whether the style draws nothing but axis-aligned rectangles.
     *
     * Only a pure square grid may be rendered with crisp edges; any curve
     * anywhere turns anti-aliasing off into a staircase.
     */
    public function isPureSquare(): bool
    {
        return $this === self::Square;
    }

    /**
     * Determine whether adjacent modules merge into a single capsule.
     */
    public function mergesRuns(): bool
    {
        return $this === self::Vertical || $this === self::Horizontal;
    }

    /**
     * Determine whether each module is drawn on its own, ignoring neighbours.
     */
    public function isPerModule(): bool
    {
        return in_array($this, [self::Square, self::Dots, self::Diamond], true);
    }

    /**
     * Determine whether the corner radii depend on the surrounding modules.
     */
    public function usesNeighbours(): bool
    {
        return $this === self::Rounded;
    }
}
