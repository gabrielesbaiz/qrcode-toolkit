<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Geometry\Layout;
use Gabrielesbaiz\QrcodeToolkit\Geometry\LayoutResult;
use Gabrielesbaiz\QrcodeToolkit\Options;
use Gabrielesbaiz\QrcodeToolkit\Support\Encoder;
use Gabrielesbaiz\QrcodeToolkit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');

/**
 * The content every test encodes unless it cares about something else.
 */
function content(): string
{
    return 'https://laravel.com';
}

/**
 * Build a layout straight from an option array.
 *
 * @param  array<string, mixed>  $options
 */
function layoutFor(array $options = [], ?string $content = null): LayoutResult
{
    $resolved = Options::fromArray($options);

    return Layout::build(Encoder::encode($content ?? content(), $resolved), $resolved);
}

/**
 * Write a throwaway PNG and return its path.
 */
function temporaryPng(int $size = 120, string $extension = 'png'): string
{
    $path = tempnam(sys_get_temp_dir(), 'qrt').'.'.$extension;

    $image = imagecreatetruecolor($size, $size);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 120, 0));
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}
