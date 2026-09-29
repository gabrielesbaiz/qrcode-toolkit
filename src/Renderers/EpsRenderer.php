<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;
use Gabrielesbaiz\QrcodeToolkit\Support\Cmyk;
use Gabrielesbaiz\QrcodeToolkit\Support\Num;

/**
 * Renders the layout to Encapsulated PostScript.
 *
 * The prolog defines one-letter operators so the path emitter inherited
 * from the base class works unchanged, and so the file stays small.
 */
final class EpsRenderer extends VectorRenderer
{
    protected static function formatName(): string
    {
        return 'EPS';
    }

    protected function beginPath(): string
    {
        return 'newpath';
    }

    protected function fillOperator(bool $evenOdd): string
    {
        return $evenOdd ? 'fe' : 'f';
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    protected function rgbOperator(array $rgb): string
    {
        return Num::f($rgb[0]).' '.Num::f($rgb[1]).' '.Num::f($rgb[2]).' rgb';
    }

    protected function cmykOperator(Cmyk $cmyk): string
    {
        [$c, $m, $y, $k] = $cmyk->components();

        return Num::f($c).' '.Num::f($m).' '.Num::f($y).' '.Num::f($k).' cmyk';
    }

    protected function text(string $text, float $x, float $baseline, float $size, float $height, string $colorOperator): string
    {
        // stringwidth measures the set text, so the caption is centred by
        // the interpreter rather than by an estimate here.
        return sprintf(
            "/Helvetica findfont %s scalefont setfont\n%s\n(%s) dup stringwidth pop 2 div %s exch sub %s moveto show",
            Num::f($size),
            $colorOperator,
            $text,
            Num::f($x),
            Num::f($height - $baseline),
        );
    }

    protected function strokeFrame(Shape $rect, float $width, string $colorOperator): string
    {
        return $colorOperator.' '.Num::f($width).' setlinewidth '.$this->pathOf([$rect]).' stroke';
    }

    protected function wrap(string $body, string $label, LayoutResult $layout, float $height): string
    {
        $width = (float) $layout->options->size;

        $lines = [
            '%!PS-Adobe-3.0 EPSF-3.0',
            '%%Creator: gabrielesbaiz/qrcode-toolkit',
            '%%Title: qrcode',
            sprintf('%%%%BoundingBox: 0 0 %d %d', (int) ceil($width), (int) ceil($height)),
            sprintf('%%%%HiResBoundingBox: 0 0 %s %s', Num::f($width), Num::f($height)),
            '%%LanguageLevel: 2',
            '%%Pages: 1',
            '%%EndComments',
            '%%BeginProlog',
            '/m { moveto } bind def',
            '/l { lineto } bind def',
            '/c { curveto } bind def',
            '/h { closepath } bind def',
            '/f { fill } bind def',
            '/fe { eofill } bind def',
            '/rgb { setrgbcolor } bind def',
            '/cmyk { setcmykcolor } bind def',
            '%%EndProlog',
            '%%Page: 1 1',
            'gsave',
            '0 '.Num::f($height).' translate',
            '1 -1 scale',
            $body,
            'grestore',
        ];

        if ($label !== '') {
            $lines[] = $label;
        }

        $lines[] = 'showpage';
        $lines[] = '%%EOF';

        return implode("\n", $lines)."\n";
    }
}
