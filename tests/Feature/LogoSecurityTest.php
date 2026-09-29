<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Exceptions\QrException;
use Gabrielesbaiz\QrcodeToolkit\Support\LoadedLogo;
use Gabrielesbaiz\QrcodeToolkit\Support\LogoLoader;

it('accepts a real local image', function (): void {
    $logo = (new LogoLoader)->load(temporaryPng());

    expect($logo?->mime)->toBe('image/png')
        ->and($logo?->width)->toBe(120)
        ->and($logo?->bytes)->not->toBeEmpty();
});

it('accepts a base64 data URI', function (): void {
    $uri = 'data:image/png;base64,'.base64_encode((string) file_get_contents(temporaryPng()));

    expect((new LogoLoader)->load($uri)?->mime)->toBe('image/png');
});

it('refuses every remote scheme and stream wrapper', function (string $path, string $scheme): void {
    expect(fn (): ?LoadedLogo => (new LogoLoader)->load($path))
        ->toThrow(QrException::class, sprintf('The scheme [%s] is not allowed', $scheme));
})->with([
    'https' => ['https://example.com/logo.png', 'https'],
    'http' => ['http://example.com/logo.png', 'http'],
    'ftp' => ['ftp://example.com/logo.png', 'ftp'],
    'file' => ['file:///etc/passwd', 'file'],
    'phar' => ['phar:///tmp/evil.phar/logo.png', 'phar'],
    'php filter' => ['php://filter/read=convert.base64-encode/resource=/etc/passwd', 'php'],
    'data compress' => ['compress.zlib:///etc/passwd', 'compress.zlib'],
]);

it('refuses a path containing a null byte', function (): void {
    expect(fn (): ?LoadedLogo => (new LogoLoader)->load("logo.png\0.txt"))
        ->toThrow(QrException::class, 'null byte');
});

it('refuses an empty path', function (): void {
    expect(fn (): ?LoadedLogo => (new LogoLoader)->load('   '))->toThrow(QrException::class, 'empty');
});

it('refuses a file that is not there', function (): void {
    expect(fn (): ?LoadedLogo => (new LogoLoader)->load('/tmp/definitely-not-here-9999.png'))
        ->toThrow(QrException::class, 'does not exist');
});

it('refuses an extension that is not an image', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'qrt').'.bmp';
    file_put_contents($path, 'whatever');

    expect(fn (): ?LoadedLogo => (new LogoLoader)->load($path))->toThrow(QrException::class, 'must be one of');
});

it('refuses a file whose bytes are not the type its name claims', function (): void {
    $png = temporaryPng();
    $disguised = tempnam(sys_get_temp_dir(), 'qrt').'.jpg';
    copy($png, $disguised);

    expect(fn (): ?LoadedLogo => (new LogoLoader)->load($disguised))
        ->toThrow(QrException::class, 'declared as [image/jpeg] but its bytes are [image/png]');
});

it('refuses something that is not an image at all', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'qrt').'.png';
    file_put_contents($path, '<html><body>hello</body></html>');

    expect(fn (): ?LoadedLogo => (new LogoLoader)->load($path))
        ->toThrow(QrException::class, 'not an allowed image type');
});

it('refuses a file over the size limit', function (): void {
    $loader = new LogoLoader(['logo_max_bytes' => 64]);

    expect(fn (): ?LoadedLogo => $loader->load(temporaryPng()))->toThrow(QrException::class, 'the maximum is');
});

it('refuses an oversized data URI before decoding it', function (): void {
    $loader = new LogoLoader(['logo_max_bytes' => 64]);
    $uri = 'data:image/png;base64,'.base64_encode((string) file_get_contents(temporaryPng()));

    expect(fn (): ?LoadedLogo => $loader->load($uri))->toThrow(QrException::class, 'the maximum is');
});

it('refuses an image with more pixels than allowed', function (): void {
    $loader = new LogoLoader(['logo_max_dimension' => 50]);

    expect(fn (): ?LoadedLogo => $loader->load(temporaryPng()))->toThrow(QrException::class, 'pixels; the maximum is');
});

it('refuses a malformed data URI', function (string $uri): void {
    expect(fn (): ?LoadedLogo => (new LogoLoader)->load($uri))->toThrow(QrException::class);
})->with([
    'not base64' => ['data:image/png;base64,!!!not-base64!!!'],
    'no media type' => ['data:;base64,AAAA'],
    'percent encoded' => ['data:image/png,%89PNG'],
]);

it('refuses an SVG logo by default', function (): void {
    $uri = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>');

    expect(fn (): ?LoadedLogo => (new LogoLoader)->load($uri))->toThrow(QrException::class, 'not an allowed image type');
});

it('refuses a dangerous SVG even when SVG logos are enabled', function (string $svg, string $reason): void {
    $path = tempnam(sys_get_temp_dir(), 'qrt').'.svg';
    file_put_contents($path, $svg);

    $loader = new LogoLoader([
        'allow_svg_logo' => true,
        'logo_mimes' => ['image/png', 'image/svg+xml'],
    ]);

    expect(fn (): ?LoadedLogo => $loader->load($path))->toThrow(QrException::class, $reason);
})->with([
    'doctype' => ['<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg"></svg>', '<!DOCTYPE'],
    'entity' => ['<!ENTITY x "y"><svg xmlns="http://www.w3.org/2000/svg"></svg>', '<!ENTITY'],
    'script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'script'],
    'handler' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>', 'onload'],
]);

it('refuses a path outside the allowed directories', function (): void {
    $loader = new LogoLoader(['logo_paths' => ['/etc']]);

    expect(fn (): ?LoadedLogo => $loader->load(temporaryPng()))
        ->toThrow(QrException::class, 'outside the allowed logo directories');
});

it('accepts a path inside the allowed directories', function (): void {
    $path = temporaryPng();
    $loader = new LogoLoader(['logo_paths' => [dirname($path)]]);

    expect($loader->load($path)?->mime)->toBe('image/png');
});

it('changes the fingerprint when the logo file changes', function (): void {
    $path = temporaryPng();
    $loader = new LogoLoader;

    $before = $loader->fingerprint($path);

    touch($path, time() + 60);
    clearstatcache(true, $path);

    expect($loader->fingerprint($path))->not->toBe($before);
});

it('hands the renderer bytes rather than a path', function (): void {
    $logo = (new LogoLoader)->load(temporaryPng());

    expect($logo?->toDataUri())->toStartWith('data:image/png;base64,');
});
