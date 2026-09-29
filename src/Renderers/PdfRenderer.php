<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Renderers;

use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;
use Gabrielesbaiz\QrcodeToolkit\Support\Cmyk;
use Gabrielesbaiz\QrcodeToolkit\Support\Num;

/**
 * Renders the layout to PDF, by hand.
 *
 * Writing the file directly rather than through a PDF library keeps the
 * package free of a dependency that would exist only to draw rectangles,
 * and it keeps the output byte-stable: there is no creation date anywhere,
 * because a timestamp would defeat the determinism the cache relies on.
 */
final class PdfRenderer extends VectorRenderer
{
    protected static function formatName(): string
    {
        return 'PDF';
    }

    protected function beginPath(): string
    {
        return '';
    }

    protected function fillOperator(bool $evenOdd): string
    {
        return $evenOdd ? 'f*' : 'f';
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    protected function rgbOperator(array $rgb): string
    {
        return Num::f($rgb[0]).' '.Num::f($rgb[1]).' '.Num::f($rgb[2]).' rg';
    }

    protected function cmykOperator(Cmyk $cmyk): string
    {
        [$c, $m, $y, $k] = $cmyk->components();

        return Num::f($c).' '.Num::f($m).' '.Num::f($y).' '.Num::f($k).' k';
    }

    protected function text(string $text, float $x, float $baseline, float $size, float $height, string $colorOperator): string
    {
        // The layout measures downwards and a PDF page counts upwards, so
        // the baseline is converted here rather than inside the flip: text
        // drawn inside the flip comes out mirrored.
        return sprintf(
            'BT /F1 %s Tf %s %s %s Td (%s) Tj ET',
            Num::f($size),
            $colorOperator,
            Num::f($x - $this->textWidth($text, $size) / 2),
            Num::f($height - $baseline),
            $text,
        );
    }

    protected function strokeFrame(Shape $rect, float $width, string $colorOperator): string
    {
        $stroke = str_replace([' rg', ' k'], [' RG', ' K'], $colorOperator);

        return $stroke.' '.Num::f($width).' w '.$this->pathOf([$rect]).' S';
    }

    /**
     * Assemble the file.
     *
     * The body is drawn inside a flip, because the layout works top-down
     * and a PDF page counts upwards from the bottom left. The caption is
     * drawn after the flip is restored: inside it the glyphs come out
     * mirrored, which is the classic trap in hand-written PDF.
     */
    protected function wrap(string $body, string $label, LayoutResult $layout, float $height): string
    {
        $width = (float) $layout->options->size;

        $content = 'q 1 0 0 -1 0 '.Num::f($height)." cm\n".$body."\nQ\n";

        if ($label !== '') {
            $content .= $label."\n";
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
                Num::f($width),
                Num::f($height),
            ),
            4 => sprintf("<< /Length %d >>\nstream\n%sendstream", strlen($content), $content),
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $payload) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$payload."\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;

        // Every entry is exactly twenty bytes, the free one included. An
        // off-by-one opens fine in Preview and reports the file as damaged
        // in Acrobat, so it is worth being pedantic about.
        $pdf .= 'xref'."\n".'0 '.$count."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_keys($objects) as $number) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        return $pdf.'trailer'."\n".'<< /Size '.$count.' /Root 1 0 R >>'."\n".'startxref'."\n".$xref."\n".'%%EOF'."\n";
    }

    /**
     * Estimate the width of a Helvetica string.
     *
     * Helvetica's average advance is close enough to half the point size to
     * centre a short caption, and shipping the full AFM metrics for one
     * line of text would be out of all proportion.
     */
    private function textWidth(string $text, float $size): float
    {
        return strlen($text) * $size * 0.5;
    }
}
