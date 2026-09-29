<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Support\LoadedLogo;

/**
 * Something that turns geometry into bytes.
 */
interface Renderer
{
    /**
     * Render the layout.
     */
    public function render(LayoutResult $layout, ?LoadedLogo $logo = null): string;
}
