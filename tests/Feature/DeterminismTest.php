<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\Format;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\Options;
use Gabrielesbaiz\QrcodeToolkit\Support\Num;

it('renders the same bytes twice for every format', function (Format $format): void {
    $build = fn () => Qr::make(content())
        ->modules('rounded')
        ->eyes('circle')
        ->gradient('#4338ca', '#be185d', 'linear', 45)
        ->label('Ticket 12')
        ->frame('#0f172a', 1.0, 2.0)
        ->render($format);

    expect($build())->toBe($build());
})->with(Format::cases());

it('renders the same bytes after a round trip through the options array', function (Format $format): void {
    $options = Options::fromArray([
        'modules' => 'dots',
        'eyes' => 'leaf',
        'gradient' => ['from' => '#4338ca', 'to' => '#be185d', 'angle' => 30],
    ]);

    $one = Qr::make(content())->with($options->toArray())->render($format);
    $two = Qr::make(content())->with($options->with([])->toArray())->render($format);

    expect($one)->toBe($two);
})->with(Format::cases());

it('writes no timestamp into the vector formats', function (): void {
    expect(Qr::make(content())->toPdf())->not->toContain('/CreationDate')
        ->and(Qr::make(content())->toEps())->not->toContain('%%CreationDate');
});

it('normalises negative zero away', function (): void {
    expect(Num::f(-0.0001))->toBe('0')
        ->and(Num::f(-0.0))->toBe('0')
        ->and(Num::f(0.0))->toBe('0');
});

it('trims trailing zeroes without losing precision', function (): void {
    expect(Num::f(1.5000))->toBe('1.5')
        ->and(Num::f(2.0))->toBe('2')
        ->and(Num::f(1.2345))->toBe('1.235')
        ->and(Num::f(-3.25))->toBe('-3.25');
});

it('gives the same fingerprint regardless of how the options were set', function (): void {
    $fluent = Qr::make(content())->size(400)->modules('dots')->options()->fingerprint();
    $array = Qr::make(content())->with(['size' => 400, 'modules' => 'dots'])->options()->fingerprint();

    expect($fluent)->toBe($array);
});
