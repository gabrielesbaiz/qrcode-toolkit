<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Geometry;

use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Frame;
use Gabrielesbaiz\QrcodeToolkit\LabelBox;
use Gabrielesbaiz\QrcodeToolkit\Options;
use Gabrielesbaiz\QrcodeToolkit\Support\MaskedMatrix;
use Gabrielesbaiz\QrcodeToolkit\Support\Matrix;
use Gabrielesbaiz\QrcodeToolkit\Support\MatrixContract;

/**
 * Turns a matrix and a set of options into geometry.
 *
 * Everything here is in module units, origin top-left, y downwards. A
 * renderer multiplies by one scalar and never thinks about modules again.
 */
final class Layout
{
    /**
     * The gap between the symbol and the caption, in module units.
     */
    private const LABEL_GAP = 0.5;

    /**
     * Build the geometry.
     */
    public static function build(Matrix $matrix, Options $options): LayoutResult
    {
        $warnings = [];

        if ($options->eccWasRaised()) {
            $warnings[] = sprintf(
                'Error correction was raised from %s to %s because a logo erases modules.',
                $options->ecc->value,
                $options->effectiveEcc()->value,
            );
        }

        $size = $matrix->size();
        $frameWidth = $options->frame instanceof Frame ? $options->frame->width : 0.0;
        $symbolSide = $size + 2 * $options->margin;

        $width = $symbolSide + 2 * $frameWidth;

        // The scale is fixed by the width alone, and the width does not
        // depend on the caption, so the caption's height in module units is
        // well defined even though it is specified in pixels.
        $unit = $options->size / $width;
        $labelHeight = $options->label === null ? 0.0 : ($options->labelSize / $unit) + self::LABEL_GAP;

        $height = $symbolSide + $labelHeight + 2 * $frameWidth;

        $symbolX = $frameWidth + $options->margin;
        $symbolY = $frameWidth + $options->margin;

        $logoRect = self::logoRect($matrix, $options, $symbolX, $symbolY);

        if ($logoRect instanceof Shape) {
            self::assertLogoClearsFinders($matrix, $logoRect, $symbolX, $symbolY);
        }

        $source = $matrix;
        $erased = 0;

        if ($logoRect instanceof Shape) {
            $masked = new MaskedMatrix($matrix, [
                $logoRect->x - $symbolX,
                $logoRect->y - $symbolY,
                $logoRect->w,
                $logoRect->h,
            ]);

            $erased = $masked->erasedCount();
            $source = $masked;
        }

        return new LayoutResult(
            width: $width,
            height: $height,
            symbolX: $symbolX,
            symbolY: $symbolY,
            matrixSize: $size,
            body: self::body($source, $options, $symbolX, $symbolY),
            eyeRings: self::eyeRings($matrix, $options, $symbolX, $symbolY),
            pupils: self::pupils($matrix, $options, $symbolX, $symbolY),
            logoRect: $logoRect,
            logoBackgroundRect: $options->logoBackground === null ? null : $logoRect,
            label: self::label($options, $width, $height, $frameWidth, $unit),
            frameRect: self::frameRect($options, $width, $height, $frameWidth),
            options: $options,
            effectiveEcc: $options->effectiveEcc(),
            modulesErased: $erased,
            warnings: $warnings,
        );
    }

    /**
     * Build the shapes for the data modules.
     *
     * @return array<int, Shape|Polygon>
     */
    private static function body(MatrixContract $matrix, Options $options, float $ox, float $oy): array
    {
        if ($options->modules->mergesRuns()) {
            $shapes = $options->modules === ModuleStyle::Vertical
                ? RunMerger::vertical($matrix, $options->moduleScale)
                : RunMerger::horizontal($matrix, $options->moduleScale);

            return array_map(
                static fn (Shape $shape): Shape => (new Shape(
                    $shape->x + $ox, $shape->y + $oy, $shape->w, $shape->h,
                    $shape->tl, $shape->tr, $shape->br, $shape->bl,
                ))->clampRadii(),
                $shapes,
            );
        }

        $size = $matrix->size();
        $scale = $options->moduleScale;
        $inset = (1.0 - $scale) / 2;
        $shapes = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (! $matrix->isBody($x, $y)) {
                    continue;
                }

                $left = $ox + $x + $inset;
                $top = $oy + $y + $inset;

                $shapes[] = match ($options->modules) {
                    ModuleStyle::Square => new Shape($left, $top, $scale, $scale),
                    ModuleStyle::Dots => Shape::square($left, $top, $scale, $scale / 2),
                    ModuleStyle::Diamond => Polygon::diamond($ox + $x + 0.5, $oy + $y + 0.5, $scale / 2, $scale / 2),
                    ModuleStyle::Rounded => Shape::withRadii(
                        $left, $top, $scale, $scale,
                        CornerResolver::radii($matrix, $x, $y, 0.5 * $scale),
                    )->clampRadii(),
                    default => new Shape($left, $top, $scale, $scale),
                };
            }
        }

        return $shapes;
    }

    /**
     * Build the outer ring and cut-out for each finder pattern.
     *
     * @return array<int, array{outer: Shape, cutout: Shape}>
     */
    private static function eyeRings(Matrix $matrix, Options $options, float $ox, float $oy): array
    {
        $rings = [];

        foreach ($matrix->finderOrigins() as [$x, $y]) {
            $rings[] = [
                'outer' => Shape::withRadii($ox + $x, $oy + $y, 7.0, 7.0, $options->eyes->outerRadii())->clampRadii(),
                'cutout' => Shape::withRadii($ox + $x + 1, $oy + $y + 1, 5.0, 5.0, $options->eyes->cutoutRadii())->clampRadii(),
            ];
        }

        return $rings;
    }

    /**
     * Build the pupil of each finder pattern.
     *
     * @return array<int, Shape>
     */
    private static function pupils(Matrix $matrix, Options $options, float $ox, float $oy): array
    {
        $pupils = [];

        foreach ($matrix->finderOrigins() as [$x, $y]) {
            $pupils[] = Shape::withRadii($ox + $x + 2, $oy + $y + 2, 3.0, 3.0, $options->eyes->pupilRadii())->clampRadii();
        }

        return $pupils;
    }

    /**
     * Work out where the logo sits.
     *
     * logo_size is a fraction of the symbol rather than of the canvas, so
     * changing the margin never resizes the logo.
     */
    private static function logoRect(Matrix $matrix, Options $options, float $ox, float $oy): ?Shape
    {
        if ($options->logo === null) {
            return null;
        }

        $side = $options->logoSize * $matrix->size();
        $padded = $side + 2 * $options->logoPadding;
        $centre = $matrix->size() / 2;

        return new Shape(
            $ox + $centre - $padded / 2,
            $oy + $centre - $padded / 2,
            $padded,
            $padded,
        );
    }

    /**
     * Refuse a logo that would sit on top of a finder pattern.
     */
    private static function assertLogoClearsFinders(Matrix $matrix, Shape $logo, float $ox, float $oy): void
    {
        foreach ($matrix->finderOrigins() as [$x, $y]) {
            $fx = $ox + $x;
            $fy = $oy + $y;

            $overlaps = $logo->x < $fx + 7
                && $logo->x + $logo->w > $fx
                && $logo->y < $fy + 7
                && $logo->y + $logo->h > $fy;

            if ($overlaps) {
                throw QrException::logoOverlapsFinder();
            }
        }
    }

    /**
     * Work out where the caption sits.
     */
    private static function label(Options $options, float $width, float $height, float $frameWidth, float $unit): ?LabelBox
    {
        if ($options->label === null) {
            return null;
        }

        $fontSize = $options->labelSize / $unit;

        return new LabelBox(
            text: $options->label,
            x: $width / 2,
            // The baseline sits a little above the bottom edge so that
            // descenders stay inside the canvas.
            baseline: $height - $frameWidth - ($fontSize * 0.25),
            fontSize: $fontSize,
            color: $options->labelColor,
        );
    }

    /**
     * Work out the frame outline, stroked on its centre line.
     */
    private static function frameRect(Options $options, float $width, float $height, float $frameWidth): ?Shape
    {
        if (! $options->frame instanceof Frame) {
            return null;
        }

        $half = $frameWidth / 2;
        $radius = $options->frame->radius;

        return Shape::withRadii(
            $half,
            $half,
            $width - $frameWidth,
            $height - $frameWidth,
            [$radius, $radius, $radius, $radius],
        )->clampRadii();
    }
}
