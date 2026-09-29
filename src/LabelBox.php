<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

/**
 * Where the caption sits, in module units.
 */
final readonly class LabelBox
{
    public function __construct(
        public string $text,
        public float $x,
        public float $baseline,
        public float $fontSize,
        public string $color,
    ) {}
}
