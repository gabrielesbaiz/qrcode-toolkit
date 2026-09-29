<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

/**
 * An accumulator of path operations.
 */
final class Path implements Drawable
{
    /**
     * @var array<int, PathOp>
     */
    private array $ops = [];

    public function moveTo(float $x, float $y): static
    {
        $this->ops[] = PathOp::move($x, $y);

        return $this;
    }

    public function lineTo(float $x, float $y): static
    {
        $this->ops[] = PathOp::line($x, $y);

        return $this;
    }

    public function cornerTo(float $x, float $y, float $r, float $elbowX, float $elbowY): static
    {
        // A zero radius corner is a corner that is not there. Dropping it
        // keeps a pure square body free of arc commands entirely.
        if ($r <= 0.0) {
            return $this->lineTo($x, $y);
        }

        $this->ops[] = PathOp::corner($x, $y, $r, $elbowX, $elbowY);

        return $this;
    }

    public function close(): static
    {
        $this->ops[] = PathOp::close();

        return $this;
    }

    /**
     * @return array<int, PathOp>
     */
    public function ops(): array
    {
        return $this->ops;
    }

    /**
     * Determine whether the path is free of curves.
     */
    public function isStraight(): bool
    {
        foreach ($this->ops as $op) {
            if ($op->op === PathOp::CORNER) {
                return false;
            }
        }

        return true;
    }
}
