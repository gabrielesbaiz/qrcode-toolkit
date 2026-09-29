<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

use Gabrielesbaiz\QrcodeToolkit\Support\Num;

/**
 * A closed straight-edged outline.
 */
final readonly class Polygon
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function __construct(public array $points) {}

    /**
     * Build a diamond inscribed in a cell.
     */
    public static function diamond(float $cx, float $cy, float $rx, float $ry): self
    {
        return new self([
            [$cx, $cy - $ry],
            [$cx + $rx, $cy],
            [$cx, $cy + $ry],
            [$cx - $rx, $cy],
        ]);
    }

    /**
     * Write the outline into a path.
     */
    public function writeTo(Drawable $path): void
    {
        foreach ($this->points as $index => [$x, $y]) {
            $index === 0 ? $path->moveTo($x, $y) : $path->lineTo($x, $y);
        }

        $path->close();
    }

    /**
     * Get the points for serialisation.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (array $point): array => [Num::normalize($point[0]), Num::normalize($point[1])],
            $this->points,
        );
    }
}
