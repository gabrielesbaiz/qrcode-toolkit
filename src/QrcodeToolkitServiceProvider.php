<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit;

use Gabrielesbaiz\QrcodeToolkit\Commands\BatchQrCommand;
use Gabrielesbaiz\QrcodeToolkit\Commands\CheckQrCommand;
use Gabrielesbaiz\QrcodeToolkit\Commands\ClearQrCommand;
use Gabrielesbaiz\QrcodeToolkit\Commands\DoctorQrCommand;
use Gabrielesbaiz\QrcodeToolkit\Commands\MakeQrCommand;
use Gabrielesbaiz\QrcodeToolkit\Commands\ProfilesQrCommand;
use Gabrielesbaiz\QrcodeToolkit\Support\CacheSpec;
use Gabrielesbaiz\QrcodeToolkit\Support\LogoLoader;
use Gabrielesbaiz\QrcodeToolkit\Support\RenderContext;
use Gabrielesbaiz\QrcodeToolkit\Support\RendererRegistry;
use Gabrielesbaiz\QrcodeToolkit\View\Components\Qr as QrComponent;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QrcodeToolkitServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('qrcode-toolkit')
            ->hasConfigFile()
            ->hasViews('qr')
            ->hasCommands([
                MakeQrCommand::class,
                CheckQrCommand::class,
                BatchQrCommand::class,
                DoctorQrCommand::class,
                ProfilesQrCommand::class,
                ClearQrCommand::class,
            ]);
    }

    /**
     * Register any package services.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(LogoLoader::class, function (Application $app): LogoLoader {
            /** @var array<string, mixed> $security */
            $security = $app->make(ConfigRepository::class)->array('qrcode-toolkit.security', []);

            return new LogoLoader($security);
        });

        // Renderers are resolved lazily inside the registry, so an
        // application that only ever emits SVG never touches GD.
        $this->app->singleton(RendererRegistry::class, function (Application $app): RendererRegistry {
            $config = $app->make(ConfigRepository::class);

            $fontPath = $config->get('qrcode-toolkit.font.path');

            return new RendererRegistry(
                pngDriver: $config->string('qrcode-toolkit.png_driver', 'auto'),
                fontPath: is_string($fontPath) ? $fontPath : null,
                fontFamily: $config->string('qrcode-toolkit.font.family', 'sans-serif'),
            );
        });

        $this->app->singleton(RenderContext::class, fn (Application $app): RenderContext => new RenderContext(
            $app->make(RendererRegistry::class),
            $app->make(LogoLoader::class),
        ));

        $this->app->singleton(CacheSpec::class, function (Application $app): CacheSpec {
            $config = $app->make(ConfigRepository::class);

            $store = $config->get('qrcode-toolkit.cache.store');

            return new CacheSpec(
                $app->make(CacheFactory::class),
                $config->boolean('qrcode-toolkit.cache.enabled'),
                is_string($store) && $store !== '' ? $store : null,
                $config->integer('qrcode-toolkit.cache.ttl', 2592000),
                $config->string('qrcode-toolkit.cache.prefix', 'qrcode-toolkit:'),
            );
        });

        $this->app->singleton(QrManager::class, fn (Application $app): QrManager => new QrManager(
            $app->make(RenderContext::class),
            $app->make(ConfigRepository::class),
            $app->make(CacheSpec::class),
        ));

        $this->app->alias(QrManager::class, 'qrcode-toolkit');
        $this->app->alias(QrManager::class, 'qr');
    }

    /**
     * Bootstrap any package services.
     */
    public function packageBooted(): void
    {
        $this->registerComponents();
    }

    /**
     * Register the package Blade components.
     *
     * The short tag is the documented one. The namespaced form is
     * registered as well so an application that already owns a component
     * called "qr" can disambiguate instead of colliding with this one.
     */
    protected function registerComponents(): void
    {
        Blade::component('qr', QrComponent::class);
        Blade::component('qr::qr', QrComponent::class);
    }
}
