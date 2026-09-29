<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Enums\GradientType;
use Gabrielesbaiz\QrcodeToolkit\Frame;
use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Geometry\PathOp;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;
use Gabrielesbaiz\QrcodeToolkit\Gradient;
use Gabrielesbaiz\QrcodeToolkit\LabelBox;
use Gabrielesbaiz\QrcodeToolkit\Support\LoadedLogo;
use Gabrielesbaiz\QrcodeToolkit\Support\Num;
use Gabrielesbaiz\QrcodeToolkit\Support\Svg;

/**
 * Renders the layout to SVG.
 *
 * Element order and whitespace are fixed, because two renders of the same
 * options have to be byte for byte identical.
 */
final readonly class SvgRenderer implements Renderer
{
    public function __construct(private string $fontFamily = 'sans-serif') {}

    public function render(LayoutResult $layout, ?LoadedLogo $logo = null): string
    {
        $options = $layout->options;

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];

        $root = '<svg xmlns="http://www.w3.org/2000/svg"';

        if ($logo instanceof LoadedLogo) {
            $root .= ' xmlns:xlink="http://www.w3.org/1999/xlink"';
        }

        $root .= sprintf(
            ' width="%s" height="%s" viewBox="0 0 %s %s"',
            Num::f((float) $options->size),
            Num::f($layout->outputHeight()),
            Num::f($layout->width),
            Num::f($layout->height),
        );

        if ($options->prefersCrispEdges()) {
            $root .= ' shape-rendering="crispEdges"';
        }

        $lines[] = $root.'>';

        if ($options->gradient instanceof Gradient) {
            $lines[] = '  <defs>';

            foreach ($this->gradientDefinition($layout) as $line) {
                $lines[] = '    '.$line;
            }

            $lines[] = '  </defs>';
        }

        if ($options->background !== null) {
            $lines[] = sprintf(
                '  <rect x="0" y="0" width="%s" height="%s" fill="%s"/>',
                Num::f($layout->width),
                Num::f($layout->height),
                $options->background,
            );
        }

        if ($layout->body !== []) {
            $lines[] = sprintf(
                '  <path fill="%s" d="%s"/>',
                $options->gradient instanceof Gradient ? 'url(#'.$this->gradientId($layout).')' : $options->foreground,
                $this->pathData(PathWriter::ops($layout->body), $options->prefersCrispEdges()),
            );
        }

        $rings = [];

        foreach ($layout->eyeRings as $ring) {
            $rings[] = $ring['outer'];
            $rings[] = $ring['cutout'];
        }

        if ($rings !== []) {
            $lines[] = sprintf(
                '  <path fill="%s" fill-rule="evenodd" d="%s"/>',
                $options->resolvedEyeColor(),
                $this->pathData(PathWriter::ops($rings), $options->prefersCrispEdges()),
            );
        }

        if ($layout->pupils !== []) {
            $lines[] = sprintf(
                '  <path fill="%s" d="%s"/>',
                $options->resolvedEyeInnerColor(),
                $this->pathData(PathWriter::ops($layout->pupils), $options->prefersCrispEdges()),
            );
        }

        if ($layout->logoBackgroundRect instanceof Shape && $options->logoBackground !== null) {
            $lines[] = sprintf(
                '  <rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>',
                Num::f($layout->logoBackgroundRect->x),
                Num::f($layout->logoBackgroundRect->y),
                Num::f($layout->logoBackgroundRect->w),
                Num::f($layout->logoBackgroundRect->h),
                $options->logoBackground,
            );
        }

        if ($logo instanceof LoadedLogo && $layout->logoRect instanceof Shape) {
            $lines[] = $this->image($layout->logoRect, $options->logoPadding, $logo);
        }

        if ($layout->label instanceof LabelBox) {
            $lines[] = sprintf(
                '  <text x="%s" y="%s" text-anchor="middle" font-family="%s" font-size="%s" fill="%s">%s</text>',
                Num::f($layout->label->x),
                Num::f($layout->label->baseline),
                Svg::escapeAttr($this->labelFamily($layout)),
                Num::f($layout->label->fontSize),
                $layout->label->color,
                Svg::escapeText($layout->label->text),
            );
        }

        if ($layout->frameRect instanceof Shape && $options->frame instanceof Frame) {
            $lines[] = sprintf(
                '  <rect x="%s" y="%s" width="%s" height="%s"%s fill="none" stroke="%s" stroke-width="%s"/>',
                Num::f($layout->frameRect->x),
                Num::f($layout->frameRect->y),
                Num::f($layout->frameRect->w),
                Num::f($layout->frameRect->h),
                $options->frame->radius > 0.0 ? ' rx="'.Num::f($options->frame->radius).'"' : '',
                $options->frame->color,
                Num::f($options->frame->width),
            );
        }

        $lines[] = '</svg>';

        return implode("\n", $lines)."\n";
    }

    /**
     * Get the identifier for this option set's gradient.
     *
     * Derived from the options, never from a counter or uniqid. Two renders
     * of the same code agree, and two different codes inlined on one page
     * do not collide, which is the bug this actually prevents.
     */
    public function gradientId(LayoutResult $layout): string
    {
        return 'qrg-'.$layout->options->fingerprint();
    }

    /**
     * Build the gradient element.
     *
     * Coordinates span the symbol rather than the canvas, so the frame and
     * the caption are never tinted.
     *
     * @return array<int, string>
     */
    private function gradientDefinition(LayoutResult $layout): array
    {
        $gradient = $layout->options->gradient;

        if (! $gradient instanceof Gradient) {
            return [];
        }

        [$sx, $sy, $side] = $layout->symbolBox();

        $cx = $sx + $side / 2;
        $cy = $sy + $side / 2;
        $id = $this->gradientId($layout);

        if ($gradient->type === GradientType::Radial) {
            $open = sprintf(
                '<radialGradient id="%s" gradientUnits="userSpaceOnUse" cx="%s" cy="%s" r="%s">',
                $id,
                Num::f($cx),
                Num::f($cy),
                Num::f($side / 2),
            );
        } else {
            $theta = deg2rad($gradient->angle);

            // Circumscribing rather than inscribing, so that a 45 degree
            // gradient really does run corner to corner.
            $reach = ($side / 2) * (abs(cos($theta)) + abs(sin($theta)));

            $open = sprintf(
                '<linearGradient id="%s" gradientUnits="userSpaceOnUse" x1="%s" y1="%s" x2="%s" y2="%s">',
                $id,
                Num::f($cx - cos($theta) * $reach),
                Num::f($cy - sin($theta) * $reach),
                Num::f($cx + cos($theta) * $reach),
                Num::f($cy + sin($theta) * $reach),
            );
        }

        return [
            $open,
            sprintf('  <stop offset="0" stop-color="%s"/>', $gradient->from),
            sprintf('  <stop offset="1" stop-color="%s"/>', $gradient->to),
            $gradient->type === GradientType::Radial ? '</radialGradient>' : '</linearGradient>',
        ];
    }

    /**
     * Build the logo image element.
     */
    private function image(Shape $rect, float $padding, LoadedLogo $logo): string
    {
        $inner = $rect->w - 2 * $padding;

        return sprintf(
            '  <image x="%s" y="%s" width="%s" height="%s" preserveAspectRatio="xMidYMid meet" xlink:href="%s"/>',
            Num::f($rect->x + $padding),
            Num::f($rect->y + $padding),
            Num::f($inner),
            Num::f($inner),
            $logo->toDataUri(),
        );
    }

    /**
     * Get the font family for the caption.
     *
     * SVG cannot embed a font file, so a path can only contribute its name
     * and the viewer has to have the font. PNG is the answer when the exact
     * face matters.
     */
    private function labelFamily(LayoutResult $layout): string
    {
        $font = $layout->options->labelFont;

        if ($font === null) {
            return Svg::fontFamily($this->fontFamily);
        }

        return Svg::fontFamily(pathinfo($font, PATHINFO_FILENAME).', '.$this->fontFamily);
    }

    /**
     * Serialise path operations.
     *
     * A straight-edged path is written with relative commands, which is
     * about forty per cent smaller for a plain grid and just as stable.
     *
     * @param  array<int, PathOp>  $ops
     */
    private function pathData(array $ops, bool $compact): string
    {
        return $compact
            ? $this->relativePathData($ops)
            : $this->absolutePathData($ops);
    }

    /**
     * Serialise with absolute commands.
     *
     * @param  array<int, PathOp>  $ops
     */
    private function absolutePathData(array $ops): string
    {
        $out = [];
        $x = 0.0;
        $y = 0.0;

        foreach ($ops as $op) {
            switch ($op->op) {
                case PathOp::MOVE:
                    $out[] = 'M'.Num::f($op->x).' '.Num::f($op->y);
                    $x = $op->x;
                    $y = $op->y;
                    break;

                case PathOp::LINE:
                    if (Num::f($op->x) === Num::f($x) && Num::f($op->y) === Num::f($y)) {
                        break;
                    }

                    $out[] = 'L'.Num::f($op->x).' '.Num::f($op->y);
                    $x = $op->x;
                    $y = $op->y;
                    break;

                case PathOp::CORNER:
                    $sweep = $this->sweep($x, $y, $op);
                    $out[] = 'A'.Num::f($op->r).' '.Num::f($op->r).' 0 0 '.$sweep.' '.Num::f($op->x).' '.Num::f($op->y);
                    $x = $op->x;
                    $y = $op->y;
                    break;

                default:
                    $out[] = 'Z';
            }
        }

        return implode('', $out);
    }

    /**
     * Serialise with relative commands, for straight-edged paths only.
     *
     * @param  array<int, PathOp>  $ops
     */
    private function relativePathData(array $ops): string
    {
        $out = [];
        $x = 0.0;
        $y = 0.0;

        foreach ($ops as $op) {
            switch ($op->op) {
                case PathOp::MOVE:
                    $out[] = 'M'.Num::f($op->x).' '.Num::f($op->y);
                    $x = $op->x;
                    $y = $op->y;
                    break;

                case PathOp::LINE:
                    $dx = $op->x - $x;
                    $dy = $op->y - $y;

                    if (Num::f($dx) === '0' && Num::f($dy) === '0') {
                        break;
                    }

                    if (Num::f($dy) === '0') {
                        $out[] = 'h'.Num::f($dx);
                    } elseif (Num::f($dx) === '0') {
                        $out[] = 'v'.Num::f($dy);
                    } else {
                        $out[] = 'l'.Num::f($dx).' '.Num::f($dy);
                    }

                    $x = $op->x;
                    $y = $op->y;
                    break;

                default:
                    $out[] = 'z';
            }
        }

        return implode('', $out);
    }

    /**
     * Work out which way a corner turns.
     *
     * The cross product of the incoming and outgoing edges gives the
     * direction; all shapes here are wound clockwise, but computing it
     * keeps the writer honest if that ever changes.
     */
    private function sweep(float $fromX, float $fromY, PathOp $op): int
    {
        $cross = ($op->elbowX - $fromX) * ($op->y - $op->elbowY)
            - ($op->elbowY - $fromY) * ($op->x - $op->elbowX);

        return $cross >= 0 ? 1 : 0;
    }
}
