<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

use Gabrielesbaiz\QrcodeToolkit\Support\Num;

/**
 * A rectangle with four independent corner radii.
 *
 * Everything this package draws, apart from the diamond, is one of these.
 */
final readonly class Shape
{
    public function __construct(
        public float $x,
        public float $y,
        public float $w,
        public float $h,
        public float $tl = 0.0,
        public float $tr = 0.0,
        public float $br = 0.0,
        public float $bl = 0.0,
    ) {}

    /**
     * Build a square with one radius on every corner.
     */
    public static function square(float $x, float $y, float $size, float $r = 0.0): self
    {
        return new self($x, $y, $size, $size, $r, $r, $r, $r);
    }

    /**
     * Build a rectangle from a radius list.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $radii
     */
    public static function withRadii(float $x, float $y, float $w, float $h, array $radii): self
    {
        return new self($x, $y, $w, $h, $radii[0], $radii[1], $radii[2], $radii[3]);
    }

    /**
     * Bring every radius inside what the rectangle can actually hold.
     *
     * This is not optional. An oversized arc radius is silently rescaled by
     * an SVG renderer but not by the beziers the PDF writer emits, so
     * without clamping here the same shape would come out differently in
     * two formats.
     */
    public function clampRadii(): self
    {
        $limit = min($this->w, $this->h) / 2;

        $tl = max(0.0, min($this->tl, $limit));
        $tr = max(0.0, min($this->tr, $limit));
        $br = max(0.0, min($this->br, $limit));
        $bl = max(0.0, min($this->bl, $limit));

        // Two radii sharing an edge cannot together exceed it.
        $scale = 1.0;

        foreach ([[$tl + $tr, $this->w], [$br + $bl, $this->w], [$tl + $bl, $this->h], [$tr + $br, $this->h]] as [$sum, $side]) {
            if ($sum > $side && $sum > 0.0) {
                $scale = min($scale, $side / $sum);
            }
        }

        if ($scale < 1.0) {
            $tl *= $scale;
            $tr *= $scale;
            $br *= $scale;
            $bl *= $scale;
        }

        return new self($this->x, $this->y, $this->w, $this->h, $tl, $tr, $br, $bl);
    }

    /**
     * Shrink the shape about its centre, radii included.
     */
    public function scaledAbout(float $factor): self
    {
        $w = $this->w * $factor;
        $h = $this->h * $factor;

        return new self(
            $this->x + ($this->w - $w) / 2,
            $this->y + ($this->h - $h) / 2,
            $w,
            $h,
            $this->tl * $factor,
            $this->tr * $factor,
            $this->br * $factor,
            $this->bl * $factor,
        );
    }

    /**
     * Determine whether the shape is a plain rectangle.
     */
    public function isRectangular(): bool
    {
        return $this->tl === 0.0 && $this->tr === 0.0 && $this->br === 0.0 && $this->bl === 0.0;
    }

    /**
     * Write the outline into a path, clockwise from the top-left corner.
     */
    public function writeTo(Drawable $path): void
    {
        $x = $this->x;
        $y = $this->y;
        $r = $this->x + $this->w;
        $b = $this->y + $this->h;

        $path->moveTo($x + $this->tl, $y);

        $path->lineTo($r - $this->tr, $y);
        $path->cornerTo($r, $y + $this->tr, $this->tr, $r, $y);

        $path->lineTo($r, $b - $this->br);
        $path->cornerTo($r - $this->br, $b, $this->br, $r, $b);

        $path->lineTo($x + $this->bl, $b);
        $path->cornerTo($x, $b - $this->bl, $this->bl, $x, $b);

        $path->lineTo($x, $y + $this->tl);
        $path->cornerTo($x + $this->tl, $y, $this->tl, $x, $y);

        $path->close();
    }

    /**
     * Get the shape for serialisation, for golden-file assertions.
     *
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'x' => Num::normalize($this->x),
            'y' => Num::normalize($this->y),
            'w' => Num::normalize($this->w),
            'h' => Num::normalize($this->h),
            'tl' => Num::normalize($this->tl),
            'tr' => Num::normalize($this->tr),
            'br' => Num::normalize($this->br),
            'bl' => Num::normalize($this->bl),
        ];
    }
}
