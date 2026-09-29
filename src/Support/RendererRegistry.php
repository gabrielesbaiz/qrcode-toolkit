<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use Gabrielesbaiz\QrcodeToolkit\Enums\Format;
use Gabrielesbaiz\QrcodeToolkit\Renderers\EpsRenderer;
use Gabrielesbaiz\QrcodeToolkit\Renderers\PdfRenderer;
use Gabrielesbaiz\QrcodeToolkit\Renderers\PngRenderer;
use Gabrielesbaiz\QrcodeToolkit\Renderers\Renderer;
use Gabrielesbaiz\QrcodeToolkit\Renderers\SvgRenderer;

/**
 * Which renderer handles which format.
 *
 * Renderers are built on demand, so an application that only ever emits
 * SVG never touches GD or ImageMagick.
 */
final class RendererRegistry
{
    /**
     * @var array<string, Renderer>
     */
    private array $resolved = [];

    public function __construct(
        private readonly string $pngDriver = 'auto',
        private readonly ?string $fontPath = null,
        private readonly string $fontFamily = 'sans-serif',
    ) {}

    /**
     * Get the renderer for a format.
     */
    public function for(Format $format): Renderer
    {
        return $this->resolved[$format->value] ??= match ($format) {
            Format::Svg => $this->svg(),
            Format::Png => new PngRenderer($this->svg(), $this->pngDriver, $this->fontPath),
            Format::Pdf => new PdfRenderer,
            Format::Eps => new EpsRenderer,
        };
    }

    /**
     * Get the PNG driver that will actually be used.
     */
    public function pngDriver(): string
    {
        $renderer = $this->for(Format::Png);

        return $renderer instanceof PngRenderer ? $renderer->resolve() : $this->pngDriver;
    }

    /**
     * Replace the renderer for a format, for tests and for extension.
     */
    public function set(Format $format, Renderer $renderer): void
    {
        $this->resolved[$format->value] = $renderer;
    }

    /**
     * Build the SVG renderer, which the PNG path also uses.
     */
    private function svg(): SvgRenderer
    {
        $existing = $this->resolved[Format::Svg->value] ?? null;

        return $existing instanceof SvgRenderer ? $existing : new SvgRenderer($this->fontFamily);
    }
}
