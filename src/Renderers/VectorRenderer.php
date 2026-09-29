<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Frame;
use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Geometry\PathOp;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Polygon;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;
use Gabrielesbaiz\QrcodeToolkit\Gradient;
use Gabrielesbaiz\QrcodeToolkit\LabelBox;
use Gabrielesbaiz\QrcodeToolkit\Support\Cmyk;
use Gabrielesbaiz\QrcodeToolkit\Support\Color;
use Gabrielesbaiz\QrcodeToolkit\Support\LoadedLogo;
use Gabrielesbaiz\QrcodeToolkit\Support\Num;
use Gabrielesbaiz\QrcodeToolkit\Support\Pdf;

/**
 * Shared behaviour for the two hand-written vector formats.
 *
 * PDF and PostScript both put the origin at the bottom left with y going
 * up, both write strings between parentheses, and both take the same path
 * operators bar the fill. The differences are small enough to live in an
 * operator map.
 */
abstract class VectorRenderer implements Renderer
{
    /**
     * Notices about what had to be flattened for this format.
     *
     * @var array<int, string>
     */
    protected array $warnings = [];

    /**
     * Wrap the drawing operations in the format's envelope.
     */
    abstract protected function wrap(string $body, string $label, LayoutResult $layout, float $height): string;

    /**
     * Get the operator that begins a new path.
     */
    abstract protected function beginPath(): string;

    /**
     * Get the fill operator.
     */
    abstract protected function fillOperator(bool $evenOdd): string;

    /**
     * Get the operator that sets an RGB colour.
     *
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    abstract protected function rgbOperator(array $rgb): string;

    /**
     * Get the operator that sets a CMYK colour.
     */
    abstract protected function cmykOperator(Cmyk $cmyk): string;

    /**
     * Draw a caption.
     */
    abstract protected function text(string $text, float $x, float $baseline, float $size, float $height, string $colorOperator): string;

    /**
     * Stroke the frame.
     */
    abstract protected function strokeFrame(Shape $rect, float $width, string $colorOperator): string;

    /**
     * Get the human name of the format, for messages.
     */
    abstract protected static function formatName(): string;

    public function render(LayoutResult $layout, ?LoadedLogo $logo = null): string
    {
        $this->warnings = [];

        $options = $layout->options;
        $unit = $layout->unit();
        $height = $layout->outputHeight();

        if ($options->gradient instanceof Gradient) {
            $this->warnings[] = sprintf(
                'A gradient cannot be expressed in %s, so the body was filled with the first stop (%s).',
                static::formatName(),
                $options->gradient->from,
            );
        }

        if ($logo instanceof LoadedLogo) {
            $this->warnings[] = sprintf(
                'A logo image cannot be embedded in %s, so the area was left clear.',
                static::formatName(),
            );
        }

        $body = '';

        if ($options->background !== null) {
            $body .= $this->color($layout, 'background', $options->background)."\n";
            $body .= $this->rectangle(0.0, 0.0, $layout->width * $unit, $height)."\n";
        }

        $foreground = $options->gradient->from ?? $options->foreground;

        if ($layout->body !== []) {
            $body .= $this->color($layout, 'foreground', $foreground)."\n";
            $body .= $this->shapes($layout->body, $unit, evenOdd: false)."\n";
        }

        $ringColours = $options->resolvedEyeColors();
        $pupilColours = $options->resolvedEyeInnerColors();

        if ($layout->eyeRings !== []) {
            if ($options->eyesShareOneColour()) {
                $rings = [];

                foreach ($layout->eyeRings as $ring) {
                    $rings[] = $ring['outer'];
                    $rings[] = $ring['cutout'];
                }

                $body .= $this->color($layout, 'eye_color', $ringColours[0])."\n";
                $body .= $this->shapes($rings, $unit, evenOdd: true)."\n";
            } else {
                foreach ($layout->eyeRings as $index => $ring) {
                    $body .= $this->color($layout, 'eye_color', $ringColours[$index] ?? $ringColours[0])."\n";
                    $body .= $this->shapes([$ring['outer'], $ring['cutout']], $unit, evenOdd: true)."\n";
                }
            }
        }

        foreach ($layout->eyeModules as $index => $shapes) {
            if ($shapes === []) {
                continue;
            }

            $body .= $this->color($layout, 'eye_color', $ringColours[$index] ?? $ringColours[0])."\n";
            $body .= $this->shapes($shapes, $unit, evenOdd: false)."\n";
        }

        if ($layout->pupils !== []) {
            if ($options->eyesShareOneColour()) {
                $body .= $this->color($layout, 'eye_inner_color', $pupilColours[0])."\n";
                $body .= $this->shapes($layout->pupils, $unit, evenOdd: false)."\n";
            } else {
                foreach ($layout->pupils as $index => $pupil) {
                    $body .= $this->color($layout, 'eye_inner_color', $pupilColours[$index] ?? $pupilColours[0])."\n";
                    $body .= $this->shapes([$pupil], $unit, evenOdd: false)."\n";
                }
            }
        }

        foreach ($layout->pupilModules as $index => $shapes) {
            if ($shapes === []) {
                continue;
            }

            $body .= $this->color($layout, 'eye_inner_color', $pupilColours[$index] ?? $pupilColours[0])."\n";
            $body .= $this->shapes($shapes, $unit, evenOdd: false)."\n";
        }

        // The knock-out already removed the modules; painting the backing
        // rectangle is what makes the hole read as deliberate rather than
        // as a printing fault.
        if ($layout->logoBackgroundRect instanceof Shape && $options->logoBackground !== null) {
            $rect = $layout->logoBackgroundRect;
            $body .= $this->color($layout, 'background', $options->logoBackground)."\n";
            $body .= $this->rectangle($rect->x * $unit, $rect->y * $unit, $rect->w * $unit, $rect->h * $unit)."\n";
        }

        if ($layout->frameRect instanceof Shape && $options->frame instanceof Frame) {
            $body .= $this->strokeFrame(
                $this->scaleShape($layout->frameRect, $unit),
                $options->frame->width * $unit,
                $this->color($layout, 'frame_color', $options->frame->color),
            )."\n";
        }

        $label = '';

        if ($layout->label instanceof LabelBox) {
            if ($options->labelFont !== null) {
                $this->warnings[] = sprintf(
                    'Embedding a font in %s is not supported, so the caption was set in Helvetica.',
                    static::formatName(),
                );
            }

            $label = $this->text(
                Pdf::escapeString($layout->label->text),
                $layout->label->x * $unit,
                $layout->label->baseline * $unit,
                $layout->label->fontSize * $unit,
                $height,
                $this->color($layout, 'label_color', $layout->label->color),
            );
        }

        return $this->wrap(rtrim($body), $label, $layout, $height);
    }

    /**
     * Get the notices raised by the last render.
     *
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Pick the colour operator for a slot, preferring an explicit CMYK value.
     */
    protected function color(LayoutResult $layout, string $slot, string $hex): string
    {
        $cmyk = $layout->options->cmykFor($slot);

        if ($cmyk instanceof Cmyk) {
            return $this->cmykOperator($cmyk);
        }

        if ($layout->options->cmyk !== []) {
            // Some slots were given CMYK values and this one was not, so the
            // press would otherwise get a mix of colour models in one file.
            $this->warnings[] = sprintf(
                'No CMYK value was given for [%s], so %s was converted from RGB. Use the values your printer supplied.',
                $slot,
                $hex,
            );

            return $this->cmykOperator(Cmyk::fromHex($hex));
        }

        return $this->rgbOperator(Color::unit($hex));
    }

    /**
     * Emit a filled axis-aligned rectangle.
     */
    protected function rectangle(float $x, float $y, float $w, float $h): string
    {
        return sprintf(
            '%s %s %s m %s %s l %s %s l %s %s l h %s',
            $this->beginPath(),
            Num::f($x), Num::f($y),
            Num::f($x + $w), Num::f($y),
            Num::f($x + $w), Num::f($y + $h),
            Num::f($x), Num::f($y + $h),
            $this->fillOperator(false),
        );
    }

    /**
     * Emit a filled path for a list of shapes.
     *
     * @param  array<int, Shape|Polygon>  $shapes
     */
    protected function shapes(array $shapes, float $unit, bool $evenOdd): string
    {
        $scaled = [];

        foreach ($shapes as $shape) {
            $scaled[] = $shape instanceof Shape
                ? $this->scaleShape($shape, $unit)
                : new Polygon(array_map(
                    static fn (array $point): array => [$point[0] * $unit, $point[1] * $unit],
                    $shape->points,
                ));
        }

        return $this->pathOf($scaled).' '.$this->fillOperator($evenOdd);
    }

    /**
     * Emit the path operations for a list of shapes, without filling them.
     *
     * @param  array<int, Shape|Polygon>  $shapes
     */
    protected function pathOf(array $shapes): string
    {
        $out = [$this->beginPath()];
        $x = 0.0;
        $y = 0.0;

        foreach (PathWriter::ops($shapes) as $op) {
            switch ($op->op) {
                case PathOp::MOVE:
                    $out[] = Num::f($op->x).' '.Num::f($op->y).' m';
                    $x = $op->x;
                    $y = $op->y;
                    break;

                case PathOp::LINE:
                    $out[] = Num::f($op->x).' '.Num::f($op->y).' l';
                    $x = $op->x;
                    $y = $op->y;
                    break;

                case PathOp::CORNER:
                    [$c1x, $c1y, $c2x, $c2y] = PathWriter::controlPoints($x, $y, $op);
                    $out[] = Num::f($c1x).' '.Num::f($c1y).' '.Num::f($c2x).' '.Num::f($c2y).' '.Num::f($op->x).' '.Num::f($op->y).' c';
                    $x = $op->x;
                    $y = $op->y;
                    break;

                default:
                    $out[] = 'h';
            }
        }

        return trim(implode(' ', $out));
    }

    /**
     * Scale a shape from module units to output units.
     */
    protected function scaleShape(Shape $shape, float $unit): Shape
    {
        return new Shape(
            $shape->x * $unit, $shape->y * $unit, $shape->w * $unit, $shape->h * $unit,
            $shape->tl * $unit, $shape->tr * $unit, $shape->br * $unit, $shape->bl * $unit,
        );
    }
}
