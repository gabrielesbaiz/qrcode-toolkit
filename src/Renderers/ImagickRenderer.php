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
 * Renders the layout to PNG by rasterising our own SVG.
 *
 * This is the better of the two PNG paths when it is available, because the
 * logo, the caption and the gradient are already correct in the SVG and
 * nothing has to be re-implemented against GD's limitations.
 */
final readonly class ImagickRenderer implements Renderer
{
    public function __construct(private SvgRenderer $svg) {}

    public function render(LayoutResult $layout, ?LoadedLogo $logo = null): string
    {
        if (! class_exists(Imagick::class)) {
            throw QrException::unsupportedFormat('png', 'The imagick extension is not loaded.');
        }

        $svg = $this->svg->render($layout, $logo);

        try {
            $image = new Imagick;

            // The background has to be set before the blob is read; after
            // the read it has no effect on what was rasterised.
            $image->setBackgroundColor(new ImagickPixel('transparent'));
            $image->readImageBlob($svg);
            $image->setImageFormat('png32');

            // Imagick writes a creation timestamp into the PNG, which would
            // make two renders of the same code differ byte for byte.
            $image->stripImage();

            $png = $image->getImageBlob();
            $image->clear();

            return $png;
        } catch (Throwable $exception) {
            throw QrException::unsupportedFormat('png', 'ImageMagick could not rasterise the SVG: '.$exception->getMessage());
        }
    }
}
