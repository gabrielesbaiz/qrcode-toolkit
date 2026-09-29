<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Support\LoadedLogo;
use Imagick;
use ImagickPixel;
use Throwable;

/**
 * Chooses how to produce a PNG.
 */
final class PngRenderer implements Renderer
{
    /**
     * Whether ImageMagick on this machine can really draw an SVG.
     */
    private static ?bool $imagickCapable = null;

    public function __construct(
        private readonly SvgRenderer $svg,
        private readonly string $driver = 'auto',
        private readonly ?string $fontPath = null,
    ) {}

    public function render(LayoutResult $layout, ?LoadedLogo $logo = null): string
    {
        return $this->resolve() === 'imagick'
            ? (new ImagickRenderer($this->svg))->render($layout, $logo)
            : (new GdRenderer($this->fontPath))->render($layout, $logo);
    }

    /**
     * Get the driver that will actually be used.
     */
    public function resolve(): string
    {
        return match ($this->driver) {
            'imagick' => self::imagickCanRasteriseSvg()
                ? 'imagick'
                : throw QrException::unsupportedFormat('png', 'ImageMagick is present but cannot rasterise SVG. Install the librsvg delegate, or set png_driver to gd or auto.'),
            'gd' => extension_loaded('gd')
                ? 'gd'
                : throw QrException::unsupportedFormat('png', 'The gd extension is not loaded.'),
            'auto' => match (true) {
                self::imagickCanRasteriseSvg() => 'imagick',
                extension_loaded('gd') => 'gd',
                default => throw QrException::unsupportedFormat('png', 'Install either ext-gd or ext-imagick to render PNG.'),
            },
            default => throw QrException::invalidOption('png_driver', $this->driver, ['auto', 'imagick', 'gd']),
        };
    }

    /**
     * Determine whether ImageMagick can be trusted with our SVG.
     *
     * Checking that the SVG format is listed is not enough. ImageMagick
     * ships an internal renderer, MSVG, which is registered under the same
     * name and silently paints gradients as flat black. The only reliable
     * test is to rasterise a gradient and look at the result, so that is
     * what this does, once per process.
     */
    public static function imagickCanRasteriseSvg(): bool
    {
        if (self::$imagickCapable !== null) {
            return self::$imagickCapable;
        }

        if (! class_exists(Imagick::class)) {
            return self::$imagickCapable = false;
        }

        $probe = '<?xml version="1.0"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20">'
            .'<defs><linearGradient id="p" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="20" y2="0">'
            .'<stop offset="0" stop-color="#ff0000"/><stop offset="1" stop-color="#0000ff"/>'
            .'</linearGradient></defs>'
            .'<rect width="20" height="20" fill="url(#p)"/></svg>';

        try {
            $image = new Imagick;
            $image->setBackgroundColor(new ImagickPixel('white'));
            $image->readImageBlob($probe);

            $left = $image->getImagePixelColor(2, 10)->getColorAsString();
            $right = $image->getImagePixelColor(17, 10)->getColorAsString();

            $image->clear();

            return self::$imagickCapable = ($left !== $right);
        } catch (Throwable) {
            return self::$imagickCapable = false;
        }
    }

    /**
     * Forget the cached capability check, for tests.
     */
    public static function forgetCapability(): void
    {
        self::$imagickCapable = null;
    }
}
