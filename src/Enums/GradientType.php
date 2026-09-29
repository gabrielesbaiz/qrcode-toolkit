<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Enums;

/**
 * The gradient geometries the SVG and PNG renderers can paint.
 */
enum GradientType: string
{
    case Linear = 'linear';
    case Radial = 'radial';

    /**
     * Get the SVG element that paints this gradient.
     */
    public function svgElement(): string
    {
        return $this === self::Linear ? 'linearGradient' : 'radialGradient';
    }
}
