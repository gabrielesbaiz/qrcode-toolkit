<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use Gabrielesbaiz\QrcodeToolkit\Enums\Format;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Layout;
use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Options;
use Gabrielesbaiz\QrcodeToolkit\Renderers\Renderer;
use Gabrielesbaiz\QrcodeToolkit\Scannability;

/**
 * The services a builder needs, gathered in one place.
 */
final readonly class RenderContext
{
    public function __construct(
        private RendererRegistry $renderers,
        private LogoLoader $logos,
    ) {}

    /**
     * Get the renderer for a format.
     */
    public function renderer(Format $format): Renderer
    {
        return $this->renderers->for($format);
    }

    /**
     * Get the logo gate.
     */
    public function logos(): LogoLoader
    {
        return $this->logos;
    }

    /**
     * Get the PNG driver that would be used.
     */
    public function pngDriver(): string
    {
        return $this->renderers->pngDriver();
    }

    /**
     * Encode the content and build its geometry.
     */
    public function layout(string $content, Options $options): LayoutResult
    {
        return Layout::build(Encoder::encode($content, $options), $options);
    }

    /**
     * Build the readability report for some content.
     */
    public function scannability(string $content, Options $options): Scannability
    {
        $encoded = Encoder::encodeWithVersion($content, $options);
        $matrix = $encoded['matrix'];
        $layout = Layout::build($matrix, $options);

        $bodyModules = 0;
        $size = $matrix->size();

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->isBody($x, $y)) {
                    $bodyModules++;
                }
            }
        }

        return new Scannability(
            ecc: $layout->effectiveEcc,
            eccRaised: $options->eccWasRaised(),
            version: $encoded['version'],
            matrixSize: $size,
            modulesTotal: $bodyModules,
            modulesErased: $layout->modulesErased,
            contrastRatio: $options->contrastRatio(),
            moduleScale: $options->moduleScale,
            transparentBackground: $options->background === null,
            warnings: $layout->warnings,
        );
    }
}
