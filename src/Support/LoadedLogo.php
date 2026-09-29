<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

/**
 * A logo that has passed every check.
 *
 * Renderers are handed bytes, never a path, so no renderer can be talked
 * into a second read from the filesystem.
 */
final readonly class LoadedLogo
{
    public function __construct(
        public string $bytes,
        public string $mime,
        public int $width,
        public int $height,
        public string $fingerprint,
    ) {}

    /**
     * Get the logo as a data URI, ready to embed.
     */
    public function toDataUri(): string
    {
        return 'data:'.$this->mime.';base64,'.base64_encode($this->bytes);
    }

    /**
     * Get the aspect ratio, width over height.
     */
    public function aspectRatio(): float
    {
        return $this->height === 0 ? 1.0 : $this->width / $this->height;
    }
}
