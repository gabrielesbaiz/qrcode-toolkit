<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

it('renders a code to a file', function (): void {
    $dir = sys_get_temp_dir().'/qrcmd-'.uniqid();
    mkdir($dir);
    $path = $dir.'/code.svg';

    $this->artisan('qr:make', ['content' => 'https://laravel.com', '--out' => $path])
        ->assertSuccessful();

    expect(file_get_contents($path))->toContain('<svg');

    @unlink($path);
    @rmdir($dir);
});

it('prints SVG to standard output when no file is given', function (): void {
    $this->artisan('qr:make', ['content' => 'https://laravel.com'])
        ->expectsOutputToContain('<svg')
        ->assertSuccessful();
});

it('refuses to print binary formats to the terminal', function (): void {
    $this->artisan('qr:make', ['content' => 'https://laravel.com', '--format' => 'png'])
        ->assertFailed();
});

it('takes styling flags', function (): void {
    $dir = sys_get_temp_dir().'/qrcmd-'.uniqid();
    mkdir($dir);
    $path = $dir.'/styled.svg';

    $this->artisan('qr:make', [
        'content' => 'https://laravel.com',
        '--out' => $path,
        '--modules' => 'dots',
        '--eyes' => 'rounded',
        '--gradient-from' => '#4338ca',
        '--gradient-to' => '#be185d',
        '--size' => 400,
    ])->assertSuccessful();

    expect(file_get_contents($path))->toContain('<linearGradient');

    @unlink($path);
    @rmdir($dir);
});

it('reports a readable code as passing', function (): void {
    $this->artisan('qr:check', ['content' => 'https://laravel.com'])
        ->expectsOutputToContain('good')
        ->assertSuccessful();
});

it('fails the build on a risky code', function (): void {
    $this->artisan('qr:check', [
        'content' => 'https://laravel.com',
        '--color' => '#9aa4b2',
        '--no-contrast-check' => true,
    ])->assertFailed();
});

it('can be told to fail on merely marginal', function (): void {
    $this->artisan('qr:check', [
        'content' => 'https://laravel.com',
        '--modules' => 'dots',
        '--module-scale' => 0.5,
        '--fail-on' => 'marginal',
    ])->assertFailed();

    $this->artisan('qr:check', [
        'content' => 'https://laravel.com',
        '--modules' => 'dots',
        '--module-scale' => 0.5,
    ])->assertSuccessful();
});

it('reports as JSON when asked', function (): void {
    $this->artisan('qr:check', ['content' => 'https://laravel.com', '--json' => true])
        ->expectsOutputToContain('"verdict"')
        ->assertSuccessful();
});

it('rejects a nonsense fail-on level', function (): void {
    $this->artisan('qr:check', ['content' => 'https://laravel.com', '--fail-on' => 'eventually'])
        ->assertFailed();
});

it('reports the environment', function (): void {
    $this->artisan('qr:doctor')
        ->expectsOutputToContain('QrcodeToolkit environment')
        ->assertSuccessful();
});

it('renders one code per CSV row', function (): void {
    $dir = sys_get_temp_dir().'/qrbatch-'.uniqid();
    mkdir($dir);
    $csv = $dir.'/rows.csv';

    file_put_contents($csv, "content,name\nhttps://laravel.com/docs,docs\nhttps://laravel.com/api,api\n");

    $this->artisan('qr:batch', [
        'csv' => $csv,
        '--out' => $dir,
        '--name-column' => 'name',
        '--format' => 'svg',
    ])->assertSuccessful();

    expect(file_exists($dir.'/docs.svg'))->toBeTrue()
        ->and(file_exists($dir.'/api.svg'))->toBeTrue();

    array_map(unlink(...), (array) glob($dir.'/*'));
    @rmdir($dir);
});

it('complains about a CSV column that is not there', function (): void {
    $dir = sys_get_temp_dir().'/qrbatch-'.uniqid();
    mkdir($dir);
    $csv = $dir.'/rows.csv';
    file_put_contents($csv, "url\nhttps://laravel.com\n");

    $this->artisan('qr:batch', ['csv' => $csv, '--out' => $dir])->assertFailed();

    @unlink($csv);
    @rmdir($dir);
});

it('complains about a CSV that is not there', function (): void {
    $this->artisan('qr:batch', ['csv' => '/tmp/definitely-not-here-9999.csv'])->assertFailed();
});

it('carries on past a bad row unless told otherwise', function (): void {
    $dir = sys_get_temp_dir().'/qrbatch-'.uniqid();
    mkdir($dir);
    $csv = $dir.'/rows.csv';

    // The middle row is empty, which is skipped rather than fatal.
    file_put_contents($csv, "content\nhttps://laravel.com/one\n\nhttps://laravel.com/two\n");

    $this->artisan('qr:batch', ['csv' => $csv, '--out' => $dir, '--format' => 'svg'])
        ->assertSuccessful();

    expect(glob($dir.'/*.svg'))->toHaveCount(2);

    array_map(unlink(...), (array) glob($dir.'/*'));
    @rmdir($dir);
});

it('writes a batch to a disk', function (): void {
    Storage::fake('codes');

    $dir = sys_get_temp_dir().'/qrbatch-'.uniqid();
    mkdir($dir);
    $csv = $dir.'/rows.csv';
    file_put_contents($csv, "content,name\nhttps://laravel.com,home\n");

    $this->artisan('qr:batch', [
        'csv' => $csv,
        '--out' => 'batch',
        '--name-column' => 'name',
        '--format' => 'png',
        '--disk' => 'codes',
    ])->assertSuccessful();

    Storage::disk('codes')->assertExists('batch/home.png');

    @unlink($csv);
    @rmdir($dir);
});

it('lists the configured profiles', function (): void {
    $this->artisan('qr:profiles', ['--no-preview' => true])
        ->expectsOutputToContain('brand')
        ->expectsOutputToContain('ticket')
        ->assertSuccessful();
});

it('draws a profile in the terminal', function (): void {
    $this->artisan('qr:profiles', ['profile' => 'ticket'])->assertSuccessful();
});

it('complains about a profile that is not configured', function (): void {
    $this->artisan('qr:profiles', ['profile' => 'nope'])->assertFailed();
});

it('reports honestly when the cache cannot be purged selectively', function (): void {
    // The array store carries no tags, and flushing the application
    // cache on the user's behalf would be the wrong call.
    $this->artisan('qr:clear')->assertSuccessful();
});
