<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

/**
 * Something that collects path operations.
 */
interface Drawable
{
    /**
     * Start a new subpath.
     */
    public function moveTo(float $x, float $y): static;

    /**
     * Draw a straight line to a point.
     */
    public function lineTo(float $x, float $y): static;

    /**
     * Draw a quarter circle ending at a point, bending around an elbow.
     */
    public function cornerTo(float $x, float $y, float $r, float $elbowX, float $elbowY): static;

    /**
     * Close the current subpath.
     */
    public function close(): static;

    /**
     * Get the operations collected so far.
     *
     * @return array<int, PathOp>
     */
    public function ops(): array;
}
