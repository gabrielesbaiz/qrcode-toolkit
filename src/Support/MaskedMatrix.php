<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

/**
 * A matrix with the modules under the logo taken out.
 *
 * The mask is applied here rather than after the shapes are generated so
 * that the run merger and the corner resolver see the hole. Clipping shapes
 * afterwards would cut a capsule in half and leave the rounded style growing
 * corners in the wrong places along the logo edge.
 */
final readonly class MaskedMatrix implements MatrixContract
{
    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $knock  x, y, width, height in module units
     */
    public function __construct(
        private MatrixContract $matrix,
        private array $knock,
    ) {}

    public function size(): int
    {
        return $this->matrix->size();
    }

    public function isDark(int $x, int $y): bool
    {
        return ! $this->isKnockedOut($x, $y) && $this->matrix->isDark($x, $y);
    }

    public function isBody(int $x, int $y): bool
    {
        return ! $this->isKnockedOut($x, $y) && $this->matrix->isBody($x, $y);
    }

    public function isFinder(int $x, int $y): bool
    {
        return $this->matrix->isFinder($x, $y);
    }

    /**
     * Count the body modules the mask removed.
     */
    public function erasedCount(): int
    {
        $count = 0;
        $size = $this->size();

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($this->matrix->isBody($x, $y) && $this->isKnockedOut($x, $y)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Determine whether the module's cell meets the logo rectangle.
     *
     * The test is against the whole unit cell rather than against the shape
     * the module would draw. With module_scale below 1 a partly covered
     * module would otherwise peek out from under the logo's edge; erasing one
     * extra ring is well inside what H level correction absorbs.
     */
    private function isKnockedOut(int $x, int $y): bool
    {
        [$kx, $ky, $kw, $kh] = $this->knock;

        return ($x + 1) > $kx
            && $x < ($kx + $kw)
            && ($y + 1) > $ky
            && $y < ($ky + $kh);
    }
}
