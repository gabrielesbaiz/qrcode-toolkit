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
            eyeRings: $options->eyes->usesModules() ? [] : self::eyeRings($matrix, $options, $symbolX, $symbolY),
            pupils: $options->eyes->usesModules() ? [] : self::pupils($matrix, $options, $symbolX, $symbolY),
            eyeModules: $options->eyes->usesModules() ? self::eyeModules($matrix, $options, $symbolX, $symbolY, ring: true) : [],
            pupilModules: $options->eyes->usesModules() ? self::eyeModules($matrix, $options, $symbolX, $symbolY, ring: false) : [],
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
     * Build an eye out of individual modules, in the body's own shape.
     *
     * The ring is the seven-by-seven border and the pupil the three-by-
     * three centre, exactly as the specification draws a finder pattern —
     * the only difference from the ring-and-cut-out styles is that each
     * cell is drawn as a module, so a dotted code gets dotted eyes.
     *
     * @return array<int, array<int, Shape|Polygon>>
     */
    private static function eyeModules(Matrix $matrix, Options $options, float $ox, float $oy, bool $ring): array
    {
        $scale = $options->moduleScale;
        $inset = (1.0 - $scale) / 2;
        $eyes = [];

        $cells = $ring ? self::ringCells() : self::pupilCells();

        // A grid of just this eye's own cells, so the rounded style can
        // read its neighbours here exactly as it does in the body.
        $grid = array_fill(0, 7, array_fill(0, 7, false));

        foreach ($cells as [$cx, $cy]) {
            $grid[$cy][$cx] = true;
        }

        $local = Matrix::fromRows($grid);

        foreach ($matrix->finderOrigins() as [$fx, $fy]) {
            $shapes = [];

            foreach ($cells as [$cx, $cy]) {
                $left = $ox + $fx + $cx + $inset;
                $top = $oy + $fy + $cy + $inset;

                $shapes[] = match ($options->modules) {
                    ModuleStyle::Dots => Shape::square($left, $top, $scale, $scale / 2),
                    ModuleStyle::Diamond => Polygon::diamond($ox + $fx + $cx + 0.5, $oy + $fy + $cy + 0.5, $scale / 2, $scale / 2),
                    ModuleStyle::Rounded => Shape::withRadii(
                        $left, $top, $scale, $scale,
                        self::localRadii($local, $cx, $cy, 0.5 * $scale),
                    )->clampRadii(),
                    default => new Shape($left, $top, $scale, $scale),
                };
            }

            $eyes[] = $shapes;
        }

        return $eyes;
    }

    /**
     * Corner radii for a cell inside an eye's own little grid.
     *
     * CornerResolver works against isBody(), which is false everywhere
     * inside a finder pattern by design, so the eye brings its own grid.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function localRadii(Matrix $grid, int $x, int $y, float $r): array
    {
        $up = $grid->isDark($x, $y - 1);
        $down = $grid->isDark($x, $y + 1);
        $left = $grid->isDark($x - 1, $y);
        $right = $grid->isDark($x + 1, $y);

        return [
            ! $up && ! $left ? $r : 0.0,
            ! $up && ! $right ? $r : 0.0,
            ! $down && ! $right ? $r : 0.0,
            ! $down && ! $left ? $r : 0.0,
        ];
    }

    /**
     * Get the cells of the seven-by-seven ring.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function ringCells(): array
    {
        $cells = [];

        for ($i = 0; $i < 7; $i++) {
            $cells[] = [$i, 0];
            $cells[] = [$i, 6];
        }

        for ($i = 1; $i < 6; $i++) {
            $cells[] = [0, $i];
            $cells[] = [6, $i];
        }

        return $cells;
    }

    /**
     * Get the cells of the three-by-three pupil.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function pupilCells(): array
    {
        $cells = [];

        for ($y = 2; $y < 5; $y++) {
            for ($x = 2; $x < 5; $x++) {
                $cells[] = [$x, $y];
            }
        }

        return $cells;
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
