<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

use Gabrielesbaiz\QrcodeToolkit\Enums\Ecc;
use Gabrielesbaiz\QrcodeToolkit\LabelBox;
use Gabrielesbaiz\QrcodeToolkit\Options;

/**
 * The finished geometry, in module units.
 *
 * Renderers get this and the options and nothing else: no matrix, no Bacon
 * types, no knowledge of how a shape was arrived at. That is the seam the
 * golden-file tests assert on.
 *
 * @phpstan-type EyeRing array{outer: Shape, cutout: Shape}
 */
final readonly class LayoutResult
{
    /**
     * @param  array<int, Shape|Polygon>  $body
     * @param  array<int, EyeRing>  $eyeRings
     * @param  array<int, Shape>  $pupils
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public float $width,
        public float $height,
        public float $symbolX,
        public float $symbolY,
        public int $matrixSize,
        public array $body,
        public array $eyeRings,
        public array $pupils,
        public ?Shape $logoRect,
        public ?Shape $logoBackgroundRect,
        public ?LabelBox $label,
        public ?Shape $frameRect,
        public Options $options,
        public Ecc $effectiveEcc,
        public int $modulesErased,
        public array $warnings = [],
    ) {}

    /**
     * Get the scale from module units to output units.
     */
    public function unit(): float
    {
        return $this->options->size / $this->width;
    }

    /**
     * Get the output height in pixels or points.
     */
    public function outputHeight(): float
    {
        return $this->options->size * $this->height / $this->width;
    }

    /**
     * Get the box the symbol itself occupies, margin included.
     *
     * @return array{0: float, 1: float, 2: float} x, y, side
     */
    public function symbolBox(): array
    {
        $side = $this->matrixSize + 2 * $this->options->margin;

        return [$this->symbolX - $this->options->margin, $this->symbolY - $this->options->margin, $side];
    }

    /**
     * Add a warning to a copy of the result.
     */
    public function withWarning(string $warning): self
    {
        return new self(
            $this->width, $this->height, $this->symbolX, $this->symbolY, $this->matrixSize,
            $this->body, $this->eyeRings, $this->pupils, $this->logoRect, $this->logoBackgroundRect,
            $this->label, $this->frameRect, $this->options, $this->effectiveEcc, $this->modulesErased,
            [...$this->warnings, $warning],
        );
    }
}
