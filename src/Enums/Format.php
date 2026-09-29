<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Enums;

/**
 * The output formats the package can render.
 */
enum Format: string
{
    case Svg = 'svg';
    case Png = 'png';
    case Pdf = 'pdf';
    case Eps = 'eps';

    /**
     * Get the media type used for HTTP responses and data URIs.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::Svg => 'image/svg+xml',
            self::Png => 'image/png',
            self::Pdf => 'application/pdf',
            self::Eps => 'application/postscript',
        };
    }

    /**
     * Get the Content-Type header value, charset included where it matters.
     */
    public function contentTypeHeader(): string
    {
        return $this === self::Svg
            ? 'image/svg+xml; charset=UTF-8'
            : $this->mimeType();
    }

    /**
     * Get the conventional file extension.
     */
    public function extension(): string
    {
        return $this->value;
    }

    /**
     * Determine whether the format keeps the geometry as vectors.
     */
    public function isVector(): bool
    {
        return $this !== self::Png;
    }

    /**
     * Determine whether the payload is binary rather than text.
     */
    public function isBinary(): bool
    {
        return $this === self::Png || $this === self::Pdf;
    }

    /**
     * Determine whether the format can paint a gradient.
     *
     * The vector writers emit solid colours only, so a gradient is flattened
     * to its first stop for PDF and EPS.
     */
    public function supportsGradient(): bool
    {
        return $this === self::Svg || $this === self::Png;
    }

    /**
     * Determine whether the format can carry a transparent background.
     */
    public function supportsAlpha(): bool
    {
        return $this === self::Svg || $this === self::Png;
    }

    /**
     * Determine whether the format can emit CMYK colour.
     *
     * Only the print formats can. A CMYK override on SVG or PNG is recorded as
     * a warning rather than thrown, because the same options object is often
     * rendered to several formats.
     */
    public function supportsCmyk(): bool
    {
        return $this === self::Pdf || $this === self::Eps;
    }

    /**
     * Resolve a format from a file extension.
     */
    public static function fromExtension(string $extension): ?self
    {
        return self::tryFrom(strtolower(ltrim($extension, '.')));
    }
}
