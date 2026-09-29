<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use BaconQrCode\Encoder\ByteMatrix;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;

/**
 * An immutable view of an encoded QR matrix.
 *
 * Rows are materialised as booleans once, so the hot loops in the layout
 * never touch Bacon's SplFixedArray.
 */
final readonly class Matrix implements MatrixContract
{
    /**
     * @param  array<int, array<int, bool>>  $rows
     */
    private function __construct(
        private array $rows,
        private int $size,
    ) {}

    /**
     * Build from Bacon's encoder output.
     *
     * The comparison is against 1 rather than against 0. Bacon initialises
     * its cells to -1 and only a fully encoded matrix is all zeroes and ones,
     * so "not light" is not the same thing as "dark".
     */
    public static function fromByteMatrix(ByteMatrix $matrix): self
    {
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();

        if ($width !== $height) {
            throw QrException::notSquare($width, $height);
        }

        $rows = [];

        for ($y = 0; $y < $height; $y++) {
            $row = [];

            for ($x = 0; $x < $width; $x++) {
                $row[$x] = $matrix->get($x, $y) === 1;
            }

            $rows[$y] = $row;
        }

        return new self($rows, $width);
    }

    /**
     * Build from a boolean grid, for tests and fixtures.
     *
     * @param  array<int, array<int, bool>>  $rows
     */
    public static function fromRows(array $rows): self
    {
        $size = count($rows);

        foreach ($rows as $row) {
            if (count($row) !== $size) {
                throw QrException::notSquare($size, count($row));
            }
        }

        return new self(array_values(array_map(array_values(...), $rows)), $size);
    }

    public function size(): int
    {
        return $this->size;
    }

    public function isDark(int $x, int $y): bool
    {
        return $this->rows[$y][$x] ?? false;
    }

    public function isBody(int $x, int $y): bool
    {
        return $this->isDark($x, $y) && ! $this->isFinder($x, $y);
    }

    /**
     * Determine whether the coordinate falls inside one of the three finders.
     *
     * The region is exactly 7x7. The eighth row and column are the separator
     * and are always light in a valid symbol, so excluding only the 7x7 both
     * matches the specification and lets a body module at x = 7 see a light
     * neighbour to its left and round its corner correctly.
     */
    public function isFinder(int $x, int $y): bool
    {
        $n = $this->size;

        return ($x < 7 && $y < 7)
            || ($x >= $n - 7 && $y < 7)
            || ($x < 7 && $y >= $n - 7);
    }

    /**
     * Get the top-left corner of each finder pattern.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function finderOrigins(): array
    {
        return [
            [0, 0],
            [$this->size - 7, 0],
            [0, $this->size - 7],
        ];
    }

    /**
     * Count the dark modules, finder patterns included.
     */
    public function darkCount(): int
    {
        $count = 0;

        foreach ($this->rows as $row) {
            foreach ($row as $dark) {
                if ($dark) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
