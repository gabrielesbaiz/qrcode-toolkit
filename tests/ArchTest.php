<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Drawable;
use Gabrielesbaiz\QrcodeToolkit\Support\MatrixContract;
use Illuminate\Console\Command;

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('everything is strictly typed')
    ->expect('Gabrielesbaiz\QrcodeToolkit')
    ->toUseStrictTypes();

arch('enums live in the Enums namespace')
    ->expect('Gabrielesbaiz\QrcodeToolkit\Enums')
    ->toBeEnums();

arch('exceptions extend the package exception')
    ->expect('Gabrielesbaiz\QrcodeToolkit\Exceptions')
    ->toExtend(QrException::class)
    ->ignoring(QrException::class);

arch('commands extend the Laravel command')
    ->expect('Gabrielesbaiz\QrcodeToolkit\Commands')
    ->toExtend(Command::class)
    ->ignoring('Gabrielesbaiz\QrcodeToolkit\Commands\Concerns');

arch('geometry is final')
    ->expect('Gabrielesbaiz\QrcodeToolkit\Geometry')
    ->toBeFinal()
    ->ignoring(Drawable::class);

arch('support is final')
    ->expect('Gabrielesbaiz\QrcodeToolkit\Support')
    ->toBeFinal()
    ->ignoring(MatrixContract::class);
