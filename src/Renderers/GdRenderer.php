<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Enums\GradientType;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Frame;
use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Polygon;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;
use Gabrielesbaiz\QrcodeToolkit\Gradient;
use Gabrielesbaiz\QrcodeToolkit\LabelBox;
use Gabrielesbaiz\QrcodeToolkit\Support\Color;
use Gabrielesbaiz\QrcodeToolkit\Support\LoadedLogo;
use GdImage;

/**
 * Renders the layout to PNG with GD.
 *
 * GD has no arcs inside a filled path, no even-odd fill and no gradients,
 * so all three are built here out of what it does have: polygons drawn at
 * four times the final size and sampled back down.
 */
final class GdRenderer implements Renderer
{
    /**
     * Notices about anything that had to be approximated.
     *
     * @var array<int, string>
     */
    private array $warnings = [];

    /**
     * How far the drawing is nudged to centre it after pixel snapping.
     */
    private float $offsetX = 0.0;

    private float $offsetY = 0.0;

    public function __construct(private readonly ?string $fontPath = null) {}

    public function render(LayoutResult $layout, ?LoadedLogo $logo = null): string
    {
        if (! extension_loaded('gd')) {
            throw QrException::unsupportedFormat('png', 'The gd extension is not loaded.');
        }

        $this->warnings = [];

        $options = $layout->options;

        $width = (int) round((float) $options->size);
        $height = (int) round($layout->outputHeight());

        // A four times canvas for a 4096 pixel code would be a 16384 square
        // truecolor image, which is about a gigabyte. The factor steps down
        // rather than the request being refused.
        $factor = match (true) {
            $options->size <= 1024 => 4,
            $options->size <= 2048 => 2,
            default => 1,
        };

        // Modules are snapped to whole pixels. Left to fall where it may, a
        // 400 pixel canvas over 33 module units gives modules 12.12 pixels
        // wide, so some come out 12 and some 13; the code is still valid
        // and a phone reads it, but a strict binarizer can fail on the
        // uneven grid. Rounding the module down to a whole pixel and
        // centring what is left in the quiet zone costs a pixel or two of
        // size and makes every module identical.
        $unit = max(1.0, floor(($options->size * $factor) / $layout->width / $factor)) * $factor;

        $drawn = $layout->width * $unit;
        $offsetX = (($width * $factor) - $drawn) / 2;
        $offsetY = (($height * $factor) - $layout->height * $unit) / 2;

        $canvas = $this->canvas($width * $factor, $height * $factor, $options->background);

        imagesetthickness($canvas, 1);

        $this->offsetX = $offsetX;
        $this->offsetY = $offsetY;

        $this->drawBody($canvas, $layout, $unit);
        $this->drawEyes($canvas, $layout, $unit);

        if ($layout->logoBackgroundRect instanceof Shape && $options->logoBackground !== null) {
            $this->fill($canvas, Rasteriser::points($layout->logoBackgroundRect, $unit), $this->allocate($canvas, $options->logoBackground));
        }

        if ($logo instanceof LoadedLogo && $layout->logoRect instanceof Shape) {
            $this->drawLogo($canvas, $layout->logoRect, $options->logoPadding, $unit, $logo);
        }

        if ($layout->frameRect instanceof Shape && $options->frame instanceof Frame) {
            $this->drawFrame($canvas, $layout, $unit);
        }

        $out = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, $this->allocateTransparent($out));
        imagecopyresampled($out, $canvas, 0, 0, 0, 0, $width, $height, $width * $factor, $height * $factor);
        imagedestroy($canvas);

        // The caption is drawn after downsampling, so the glyphs are set at
        // their real size instead of being scaled from a larger bitmap.
        if ($layout->label instanceof LabelBox) {
            $this->drawLabel($out, $layout, $layout->unit());
        }

        ob_start();
        imagepng($out, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($out);

        return $png;
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
     * Make the working canvas.
     */
    private function canvas(int $width, int $height, ?string $background): GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $fill = $background === null
            ? $this->allocateTransparent($canvas)
            : $this->allocate($canvas, $background);

        imagefilledrectangle($canvas, 0, 0, $width, $height, $fill);
        imagealphablending($canvas, true);

        return $canvas;
    }

    /**
     * Draw the data modules.
     */
    private function drawBody(GdImage $canvas, LayoutResult $layout, float $unit): void
    {
        $options = $layout->options;
        $gradient = $options->gradient;

        if ($gradient instanceof Gradient) {
            $this->warnings[] = 'GD has no gradient fill, so each module was painted with the gradient colour sampled at its centre.';
        }

        $solid = $gradient instanceof Gradient ? null : $this->allocate($canvas, $options->foreground);
        [$sx, $sy, $side] = $layout->symbolBox();

        foreach ($layout->body as $shape) {
            $color = $solid ?? $this->sampleGradient($canvas, $layout, $shape, $sx, $sy, $side);

            $this->fill($canvas, Rasteriser::points($shape, $unit), $color);
        }
    }

    /**
     * Draw the three finder patterns.
     *
     * GD has no even-odd fill, so the ring is made by painting the outer
     * shape and then painting the cut-out back in the background colour.
     * When the background is transparent the fill has to replace rather
     * than composite, or the hole is invisible but still opaque.
     */
    private function drawEyes(GdImage $canvas, LayoutResult $layout, float $unit): void
    {
        $options = $layout->options;
        $ringColours = $options->resolvedEyeColors();
        $pupilColours = $options->resolvedEyeInnerColors();

        $transparent = $options->background === null;

        $hole = $transparent
            ? $this->allocateTransparent($canvas)
            : $this->allocate($canvas, $options->background ?? '#ffffff');

        foreach ($layout->eyeRings as $index => $eye) {
            $ring = $this->allocate($canvas, $ringColours[$index] ?? $ringColours[0]);

            $this->fill($canvas, Rasteriser::points($eye['outer'], $unit), $ring);

            if ($transparent) {
                imagealphablending($canvas, false);
            }

            $this->fill($canvas, Rasteriser::points($eye['cutout'], $unit), $hole);

            if ($transparent) {
                imagealphablending($canvas, true);
            }
        }

        // Module eyes are drawn as modules rather than as a ring with a
        // hole, so there is nothing to knock back out.
        foreach ($layout->eyeModules as $index => $shapes) {
            $ring = $this->allocate($canvas, $ringColours[$index] ?? $ringColours[0]);

            foreach ($shapes as $shape) {
                $this->fill($canvas, Rasteriser::points($shape, $unit), $ring);
            }
        }

        foreach ($layout->pupils as $index => $shape) {
            $this->fill($canvas, Rasteriser::points($shape, $unit), $this->allocate($canvas, $pupilColours[$index] ?? $pupilColours[0]));
        }

        foreach ($layout->pupilModules as $index => $shapes) {
            $pupil = $this->allocate($canvas, $pupilColours[$index] ?? $pupilColours[0]);

            foreach ($shapes as $shape) {
                $this->fill($canvas, Rasteriser::points($shape, $unit), $pupil);
            }
        }
    }

    /**
     * Composite the logo into its rectangle, keeping its aspect ratio.
     */
    private function drawLogo(GdImage $canvas, Shape $rect, float $padding, float $unit, LoadedLogo $logo): void
    {
        $image = @imagecreatefromstring($logo->bytes);

        if ($image === false) {
            $this->warnings[] = 'The logo could not be decoded by GD and was left out.';

            return;
        }

        $box = ($rect->w - 2 * $padding) * $unit;
        $ratio = $logo->aspectRatio();

        $targetWidth = $ratio >= 1.0 ? $box : $box * $ratio;
        $targetHeight = $ratio >= 1.0 ? $box / $ratio : $box;

        $x = ($rect->x + $padding) * $unit + ($box - $targetWidth) / 2;
        $y = ($rect->y + $padding) * $unit + ($box - $targetHeight) / 2;

        imagecopyresampled(
            $canvas,
            $image,
            (int) round($x + $this->offsetX),
            (int) round($y + $this->offsetY),
            0,
            0,
            (int) round($targetWidth),
            (int) round($targetHeight),
            imagesx($image),
            imagesy($image),
        );

        imagedestroy($image);
    }

    /**
     * Stroke the frame, by drawing the outline as a run of thick lines.
     */
    private function drawFrame(GdImage $canvas, LayoutResult $layout, float $unit): void
    {
        $frame = $layout->options->frame;
        $rect = $layout->frameRect;

        if (! $frame instanceof Frame || ! $rect instanceof Shape) {
            return;
        }

        $points = $this->translate(Rasteriser::points($rect, $unit));
        $color = $this->allocate($canvas, $frame->color);

        imagesetthickness($canvas, max(1, (int) round($frame->width * $unit)));

        $count = count($points);

        for ($i = 0; $i < $count; $i++) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[($i + 1) % $count];

            imageline($canvas, (int) round($x1), (int) round($y1), (int) round($x2), (int) round($y2), $color);
        }

        imagesetthickness($canvas, 1);
    }

    /**
     * Draw the caption.
     */
    private function drawLabel(GdImage $image, LayoutResult $layout, float $unit): void
    {
        $label = $layout->label;

        if (! $label instanceof LabelBox) {
            return;
        }

        $color = $this->allocate($image, $label->color);
        $size = $label->fontSize * $unit;
        $x = $label->x * $unit;
        $baseline = $label->baseline * $unit;

        $font = $layout->options->labelFont ?? $this->fontPath;

        if ($font !== null && function_exists('imagettftext') && is_readable($font)) {
            $box = imagettfbbox($size, 0, $font, $label->text);

            if ($box !== false) {
                // imagettfbbox returns eight corner coordinates; the
                // advance width is the difference between the two on the
                // baseline.
                $right = is_numeric($box[2]) ? (float) $box[2] : 0.0;
                $left = is_numeric($box[0]) ? (float) $box[0] : 0.0;
                $width = $right - $left;
                imagettftext($image, $size, 0, (int) round($x - $width / 2), (int) round($baseline), $color, $font, $label->text);

                return;
            }
        }

        // GD's built-in faces come in five fixed sizes and cannot render
        // anything outside Latin-1, so the requested size is approximated.
        $this->warnings[] = 'No TrueType font was available, so the caption was drawn with a built-in font at an approximate size.';

        $builtin = max(1, min(5, (int) round($size / 4)));
        $width = imagefontwidth($builtin) * strlen($label->text);

        imagestring(
            $image,
            $builtin,
            (int) round($x - $width / 2),
            (int) round($baseline - imagefontheight($builtin)),
            $label->text,
            $color,
        );
    }

    /**
     * Sample the gradient at a shape's centre.
     */
    private function sampleGradient(GdImage $canvas, LayoutResult $layout, Shape|Polygon $shape, float $sx, float $sy, float $side): int
    {
        $gradient = $layout->options->gradient;

        if (! $gradient instanceof Gradient) {
            return $this->allocate($canvas, $layout->options->foreground);
        }

        [$cx, $cy] = $this->centre($shape);

        $mx = $sx + $side / 2;
        $my = $sy + $side / 2;

        if ($gradient->type === GradientType::Radial) {
            $t = $side === 0.0 ? 0.0 : (sqrt(($cx - $mx) ** 2 + ($cy - $my) ** 2)) / ($side / 2);
        } else {
            $theta = deg2rad($gradient->angle);
            $reach = ($side / 2) * (abs(cos($theta)) + abs(sin($theta)));
            $projection = ($cx - $mx) * cos($theta) + ($cy - $my) * sin($theta);
            $t = $reach === 0.0 ? 0.0 : ($projection + $reach) / (2 * $reach);
        }

        $t = max(0.0, min(1.0, $t));

        [$r1, $g1, $b1] = Color::rgb($gradient->from);
        [$r2, $g2, $b2] = Color::rgb($gradient->to);

        return $this->allocateRgb(
            $canvas,
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t),
        );
    }

    /**
     * Get the centre of a shape, in module units.
     *
     * @return array{0: float, 1: float}
     */
    private function centre(Shape|Polygon $shape): array
    {
        if ($shape instanceof Shape) {
            return [$shape->x + $shape->w / 2, $shape->y + $shape->h / 2];
        }

        $x = 0.0;
        $y = 0.0;

        foreach ($shape->points as [$px, $py]) {
            $x += $px;
            $y += $py;
        }

        $count = max(1, count($shape->points));

        return [$x / $count, $y / $count];
    }

    /**
     * Fill a polygon.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private function fill(GdImage $canvas, array $points, int $color): void
    {
        if (count($points) < 3) {
            return;
        }

        imagefilledpolygon($canvas, Rasteriser::flatten($this->translate($points)), $color);
    }

    /**
     * Nudge a point list by the centring offset.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function translate(array $points): array
    {
        if ($this->offsetX === 0.0 && $this->offsetY === 0.0) {
            return $points;
        }

        return array_map(
            fn (array $point): array => [$point[0] + $this->offsetX, $point[1] + $this->offsetY],
            $points,
        );
    }

    /**
     * Allocate a colour from a hex string.
     */
    private function allocate(GdImage $canvas, string $hex): int
    {
        [$r, $g, $b] = Color::rgb($hex);

        return $this->allocateRgb($canvas, $r, $g, $b);
    }

    /**
     * Allocate a colour from three channels, clamped to what GD accepts.
     */
    private function allocateRgb(GdImage $canvas, int $r, int $g, int $b): int
    {
        $color = imagecolorallocate(
            $canvas,
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
        );

        return $color === false ? 0 : $color;
    }

    /**
     * Allocate a fully transparent colour.
     */
    private function allocateTransparent(GdImage $canvas): int
    {
        $color = imagecolorallocatealpha($canvas, 255, 255, 255, 127);

        return $color === false ? 0 : $color;
    }
}
