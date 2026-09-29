<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Commands;

use Gabrielesbaiz\QrcodeToolkit\Renderers\PngRenderer;
use Gabrielesbaiz\QrcodeToolkit\Support\CacheSpec;
use Gabrielesbaiz\QrcodeToolkit\Support\RendererRegistry;
use Illuminate\Console\Command;
use Imagick;
use Throwable;

/**
 * Explain what this machine can and cannot render, and why.
 *
 * The PNG driver is chosen by probing rather than by asking, and the
 * reason is not obvious from the outside: ImageMagick ships an internal
 * SVG renderer registered under the same name as librsvg that paints
 * gradients flat black. This command is the answer to "why is my
 * gradient black", without anyone having to read the source.
 */
class DoctorQrCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'qr:doctor';

    /**
     * The console command description.
     */
    protected $description = 'Report what this machine can render, and which PNG driver will be used';

    /**
     * Execute the console command.
     */
    public function handle(RendererRegistry $registry, CacheSpec $cache): int
    {
        $this->components->info('QrcodeToolkit environment');

        $this->section('PHP');
        $this->detail('Version', PHP_VERSION);
        $this->detail('ext-gd', $this->yesNo(extension_loaded('gd')));
        $this->detail('ext-imagick', $this->yesNo(extension_loaded('imagick')));
        $this->detail('ext-fileinfo', $this->yesNo(extension_loaded('fileinfo')));

        $this->section('PNG');
        $configured = $this->text('qrcode-toolkit.png_driver', 'auto');
        $this->detail('Configured driver', $configured);

        $capable = PngRenderer::imagickCanRasteriseSvg();

        if (extension_loaded('imagick')) {
            $this->detail('ImageMagick', $this->imagickVersion());
            $this->detail('Rasterises SVG correctly', $capable
                ? '<fg=green>yes</>'
                : '<fg=yellow>no — the internal MSVG renderer paints gradients flat black</>');
        }

        try {
            $this->detail('Driver in use', '<options=bold>'.$registry->pngDriver().'</>');
        } catch (Throwable $exception) {
            $this->detail('Driver in use', '<fg=red>none — '.$exception->getMessage().'</>');
        }

        if ($registryDriverIsGd = $this->safeDriver($registry) === 'gd') {
            $this->detail('GD FreeType', $this->yesNo(function_exists('imagettftext')));
        }

        $this->section('Fonts');
        $path = config('qrcode-toolkit.font.path');
        $this->detail('TrueType path', is_string($path) && $path !== ''
            ? $path.' '.(is_readable($path) ? '<fg=green>(readable)</>' : '<fg=red>(not readable)</>')
            : '<fg=gray>not set</>');
        $this->detail('SVG family', $this->text('qrcode-toolkit.font.family', 'sans-serif'));

        if ($registryDriverIsGd && ! function_exists('imagettftext')) {
            $this->components->warn('PNG captions will fall back to a built-in bitmap font at an approximate size.');
        }

        $this->section('Cache');
        $enabled = (bool) config('qrcode-toolkit.cache.enabled', false);
        $this->detail('Enabled', $this->yesNo($enabled));
        $store = config('qrcode-toolkit.cache.store');
        $this->detail('Store', is_string($store) && $store !== '' ? $store : $this->text('cache.default', 'array'));
        $this->detail('Purgeable by qr:clear', $this->yesNo($cache->taggable()));

        $this->section('Security');
        $paths = config('qrcode-toolkit.security.logo_paths', []);
        $readable = [];

        if (is_array($paths)) {
            foreach ($paths as $path) {
                if (is_scalar($path)) {
                    $readable[] = (string) $path;
                }
            }
        }

        $this->detail('Logo directories', $readable !== []
            ? implode(', ', $readable)
            : '<fg=yellow>any readable path — set logo_paths if a path can come from a request</>');
        $this->detail('SVG logos', $this->yesNo((bool) config('qrcode-toolkit.security.allow_svg_logo', false)));
        $this->detail('Minimum contrast', $this->text('qrcode-toolkit.security.min_contrast', '3.0').':1');

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Read a configuration value as a string.
     */
    protected function text(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Get the resolved driver without letting a failure stop the report.
     */
    protected function safeDriver(RendererRegistry $registry): ?string
    {
        try {
            return $registry->pngDriver();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read the ImageMagick version string.
     */
    protected function imagickVersion(): string
    {
        try {
            $version = Imagick::getVersion();

            return is_array($version) && isset($version['versionString'])
                ? (string) $version['versionString']
                : 'unknown';
        } catch (Throwable) {
            return 'unknown';
        }
    }

    /**
     * Print a section heading.
     */
    protected function section(string $title): void
    {
        $this->newLine();
        $this->line('  <options=bold>'.$title.'</>');
    }

    /**
     * Print one fact.
     */
    protected function detail(string $label, string $value): void
    {
        $this->components->twoColumnDetail($label, $value);
    }

    /**
     * Render a boolean the way a reader wants to see it.
     */
    protected function yesNo(bool $value): string
    {
        return $value ? '<fg=green>yes</>' : '<fg=red>no</>';
    }
}
