<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

/**
 * The read surface the layout needs from a matrix.
 *
 * Both the encoded matrix and the logo-masked view of it implement this, so
 * the run merger and the corner resolver never know which one they have.
 */
interface MatrixContract
{
    /**
     * Get the number of modules along one edge.
     */
    public function size(): int;

    /**
     * Determine whether the module is dark, finder patterns included.
     */
    public function isDark(int $x, int $y): bool;

    /**
     * Determine whether the module is dark and is not part of a finder pattern.
     */
    public function isBody(int $x, int $y): bool;

    /**
     * Determine whether the coordinate falls inside one of the three finders.
     */
    public function isFinder(int $x, int $y): bool;
}
