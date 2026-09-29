<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Enums\ModuleStyle;
use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Facades\Qr;
use Gabrielesbaiz\QrcodeToolkit\QrBuilder;
use Gabrielesbaiz\QrcodeToolkit\Renderers\PngRenderer;
use Illuminate\Support\Facades\Storage;

/**
 * The README promises these work. This file is why that promise holds.
 */
it('runs the quick start examples', function (): void {
    expect(Qr::make('https://sbaiz.com')->toSvg())->toContain('<svg');

    expect(
        Qr::make('https://sbaiz.com')
            ->modules('dots')
            ->eyes('rounded')
            ->gradient('#4338ca', '#be185d', 'linear', 45)
            ->size(400)
            ->toSvg()
    )->toContain('<linearGradient');
});

it('runs every styling example', function (): void {
    $url = 'https://sbaiz.com';

    foreach (['square', 'rounded', 'dots', 'diamond', 'vertical', 'horizontal'] as $style) {
        expect(Qr::make($url)->modules($style)->toSvg())->toContain('<svg');
    }

    expect(Qr::make($url)->modules(ModuleStyle::Dots)->options()->modules)->toBe(ModuleStyle::Dots);
    expect(Qr::make($url)->modules('dots', 0.8)->options()->moduleScale)->toBe(0.8);
    expect(Qr::make($url)->with(['module_scale' => 0.8])->options()->moduleScale)->toBe(0.8);

    foreach (['square', 'rounded', 'circle', 'leaf'] as $eye) {
        expect(Qr::make($url)->eyes($eye)->toSvg())->toContain('<svg');
    }

    expect(Qr::make($url)->eyes('rounded', '#1e1b4b', '#4338ca')->options()->eyeColor)->toBe('#1e1b4b');
    expect(Qr::make($url)->color('#0f172a')->background('#f8fafc')->options()->foreground)->toBe('#0f172a');
    expect(Qr::make($url)->gradient('#4338ca', '#be185d')->options()->gradient)->not->toBeNull();
    expect(Qr::make($url)->radialGradient('#0369a1', '#15803d')->options()->gradient?->type->value)->toBe('radial');
    expect(Qr::make($url)->transparent()->options()->background)->toBeNull();
    expect(Qr::make($url)->background(null)->options()->background)->toBeNull();
    expect(Qr::make($url)->frame('#0f172a', width: 1.0, radius: 2.0)->options()->frame?->radius)->toBe(2.0);
});

it('rejects a named colour exactly as the README says', function (): void {
    expect(fn () => Qr::make('https://sbaiz.com')->color('rebeccapurple'))
        ->toThrow(QrException::class, 'must be a hex colour such as #0af or #00aaff');
});

it('runs the logo and caption examples', function (): void {
    $logo = temporaryPng();
    $url = 'https://sbaiz.com';

    expect(Qr::make($url)->logo($logo)->toSvg())->toContain('<image');
    expect(Qr::make($url)->logo($logo, size: 0.25, padding: 1.0, background: '#ffffff')->toSvg())->toContain('<image');
    expect(Qr::make($url)->label('Ticket A-12')->toSvg())->toContain('Ticket A-12');
    expect(Qr::make($url)->label('Ticket A-12', size: 14, color: '#475569')->toSvg())->toContain('#475569');
});

it('reports the fields the README prints', function (): void {
    $report = Qr::make('https://sbaiz.com')
        ->modules('dots', 0.9)
        ->logo(temporaryPng())
        ->scannability();

    expect($report->verdict())->toBeIn(['good', 'marginal', 'risky'])
        ->and($report->ecc->value)->toBe('H')
        ->and($report->eccRaised)->toBeTrue()
        ->and($report->version)->toBeInt()
        ->and($report->matrixSize)->toBeInt()
        ->and($report->modulesErased)->toBeGreaterThan(0)
        ->and($report->budgetUsed())->toBeFloat()
        ->and($report->budgetRemaining())->toBeFloat()
        ->and($report->contrastRatio)->toBeFloat()
        ->and($report->minimumPrintWidthMm())->toBeFloat()
        ->and($report->moduleSizeMm(50.0))->toBeFloat()
        ->and($report->reasons())->toBeArray();

    expect($report->toArray())->toHaveKeys([
        'verdict', 'ecc', 'ecc_raised', 'version', 'matrix_size', 'modules_total',
        'modules_erased', 'budget_used', 'budget_remaining', 'contrast_ratio',
        'module_scale', 'transparent_background', 'minimum_print_width_mm', 'reasons',
    ]);
});

it('runs every format example', function (): void {
    $url = 'https://sbaiz.com';

    expect(Qr::make($url)->toSvg())->toContain('<svg')
        ->and(Qr::make($url)->toPdf())->toStartWith('%PDF')
        ->and(Qr::make($url)->toEps())->toStartWith('%!PS')
        ->and(Qr::make($url)->render('png'))->toStartWith("\x89PNG");

    expect(PngRenderer::imagickCanRasteriseSvg())->toBeBool();
});

it('runs the CMYK example', function (): void {
    $pdf = Qr::make('https://sbaiz.com')
        ->cmyk('foreground', [0, 0, 0, 100])
        ->cmyk('background', [0, 0, 0, 0])
        ->toPdf();

    expect($pdf)->toContain(' k');
});

it('runs every payload example', function (): void {
    expect(Qr::url('https://sbaiz.com/hello')->content())->toBe('https://sbaiz.com/hello');
    expect(Qr::url('sbaiz.com')->content())->toBe('https://sbaiz.com');

    expect(Qr::wifi('Office', 'sup3rsecret')->content())->toBe('WIFI:T:WPA;S:Office;P:sup3rsecret;;');
    expect(Qr::wifi('Guest', encryption: 'nopass')->content())->toContain('T:nopass');
    expect(Qr::wifi('Office', 'sup3rsecret', hidden: true)->content())->toContain('H:true');
    expect(Qr::wifi('Corp', 'password', 'WPA2-EAP', enterprise: [
        'eap' => 'PEAP', 'identity' => 'gabriele', 'phase2' => 'MSCHAPV2',
    ])->content())->toContain('E:PEAP');

    expect(Qr::vcard([
        'first' => 'Gabriele',
        'last' => 'Sbaiz',
        'org' => 'NoviasNet',
        'title' => 'Developer',
        'mobile' => '+393331234567',
        'email' => ['gabriele@example.com', 'g@example.com'],
        'street' => 'Via Roma 1',
        'city' => 'Udine',
        'postal_code' => '33100',
        'country' => 'Italy',
        'url' => 'https://sbaiz.com',
    ])->content())->toContain('BEGIN:VCARD');

    expect(Qr::vcard(['first' => 'Gabriele', 'last' => 'Sbaiz', 'mobile' => '+393331234567'], mecard: true)->content())
        ->toStartWith('MECARD:');

    expect(Qr::email('gabriele@example.com', 'Order #12', "Hello\nthere")->content())->toStartWith('mailto:');
    expect(Qr::email('gabriele@example.com', 'Order #12', matmsg: true)->content())->toStartWith('MATMSG:');
    expect(Qr::phone('+39 333 1234567')->content())->toBe('tel:+393331234567');
    expect(Qr::sms('+393331234567', 'On my way')->content())->toBe('SMSTO:+393331234567:On my way');
    expect(Qr::geo(46.0693, 13.2358)->content())->toBe('geo:46.0693,13.2358');
    expect(Qr::geo(46.0693, 13.2358, altitude: 120.0)->content())->toBe('geo:46.0693,13.2358,120');
    expect(Qr::geo(46.0693, 13.2358, query: 'Udine Castle')->content())->toContain('?q=Udine%20Castle');

    expect(Qr::epc(
        name: 'NoviasNet S.r.l.',
        iban: 'IT60X0542811101000000123456',
        amount: 12.30,
        remittance: 'Invoice 2026/0042',
        bic: 'BPPBITRRXXX',
        purpose: 'GDDS',
    )->content())->toStartWith('BCD');
});

it('reports a bad IBAN with the message the README quotes', function (): void {
    expect(fn () => Qr::epc('Acme', 'GB82WEST12345698765433'))
        ->toThrow(QrException::class, 'failed the mod 97 checksum');
});

it('refuses a logo on an EPC payload', function (): void {
    $qr = Qr::epc('Acme', 'IT60X0542811101000000123456');

    expect(fn () => $qr->logo(temporaryPng()))->toThrow(QrException::class, 'EPC specification');
});

it('runs every output example', function (): void {
    $builder = Qr::make('https://sbaiz.com');

    expect($builder->toSvg())->toBeString()
        ->and($builder->toHtml())->toBeString()
        ->and((string) $builder)->toBeString()
        ->and($builder->toDataUri())->toStartWith('data:image/svg+xml;base64,')
        ->and($builder->toDataUri('svg', base64: false))->toStartWith('data:image/svg+xml;charset=UTF-8,')
        ->and((string) $builder->toImg(['alt' => 'Scan me']))->toContain('<img ')
        ->and($builder->response()->getStatusCode())->toBe(200)
        ->and($builder->response('png')->headers->get('Content-Type'))->toBe('image/png')
        ->and($builder->download('ticket', 'png')->headers->get('Content-Disposition'))->toContain('ticket.png');

    $path = sys_get_temp_dir().'/qr-readme-'.uniqid().'.svg';
    expect($builder->save($path))->toBe($path);
    @unlink($path);

    Storage::fake('public');
    $builder->save('codes/one.png', 'public');
    Storage::disk('public')->assertExists('codes/one.png');
});

it('runs the profile examples', function (): void {
    expect(Qr::profile('brand', 'https://sbaiz.com')->toSvg())->toContain('<svg')
        ->and(Qr::profiles())->toContain('brand', 'ticket', 'minimal');
});

it('runs the cache examples', function (): void {
    $url = 'https://sbaiz.com';

    expect(Qr::make($url)->cache()->toPng())->toStartWith("\x89PNG")
        ->and(Qr::make($url)->cache(ttl: 3600, store: 'array')->toPng())->toStartWith("\x89PNG")
        ->and(Qr::make($url)->cache(key: 'qr:order:1')->toPng())->toStartWith("\x89PNG")
        ->and(Qr::make($url)->noCache()->toPng())->toStartWith("\x89PNG");
});

it('runs the macro examples', function (): void {
    QrBuilder::macro('brandish', fn (): QrBuilder => $this
        ->modules('dots')
        ->eyes('rounded')
        ->gradient('#4338ca', '#be185d'));

    expect(Qr::make('https://sbaiz.com')->brandish()->size(400)->toSvg())->toContain('<svg');

    QrBuilder::macro('oops', function (): void {});

    expect(fn () => Qr::make('https://sbaiz.com')->oops())
        ->toThrow(QrException::class, 'must return a value');
});

it('runs the batch recipe', function (): void {
    $base = Qr::profile('brand')
        ->logo(temporaryPng())
        ->cache(ttl: 0);

    $directory = sys_get_temp_dir().'/qr-batch-'.uniqid();
    mkdir($directory);

    foreach ([1, 2, 3] as $id) {
        $base->withContent("https://sbaiz.com/orders/{$id}")->save("{$directory}/{$id}.png");
    }

    expect(glob($directory.'/*.png'))->toHaveCount(3);

    array_map(unlink(...), (array) glob($directory.'/*.png'));
    rmdir($directory);
});

it('runs the ticket recipe', function (): void {
    $builder = Qr::profile('ticket', 'https://sbaiz.com/t/A-12')
        ->label('A-12')
        ->size(240);

    expect($builder->scannability()->verdict())->toBeIn(['good', 'marginal'])
        ->and($builder->toPdf())->toStartWith('%PDF');
});

it('matches the README on profile merging', function (): void {
    config()->set('qrcode-toolkit.profiles.partial', ['gradient' => ['type' => 'radial']]);
    config()->set('qrcode-toolkit.defaults.gradient', ['from' => '#4338ca', 'to' => '#be185d', 'type' => 'linear', 'angle' => 0]);

    $options = Qr::profile('partial', 'https://sbaiz.com')->options();

    expect($options->gradient?->type->value)->toBe('radial')
        ->and($options->gradient?->from)->toBe('#4338ca');
});

it('reports the shape counts the README quotes', function (): void {
    expect(count(layoutFor(['modules' => 'square'])->body))->toBe(231)
        ->and(count(layoutFor(['modules' => 'vertical'])->body))->toBe(123);
});
