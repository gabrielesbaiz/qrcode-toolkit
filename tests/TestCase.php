<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Tests;

use Gabrielesbaiz\QrcodeToolkit\QrcodeToolkitServiceProvider;
use Gabrielesbaiz\QrcodeToolkit\Renderers\PngRenderer;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // The SVG capability probe is memoised per process, so it is
        // cleared here to keep one test's driver out of the next.
        PngRenderer::forgetCapability();
    }

    /**
     * Get the package providers.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            QrcodeToolkitServiceProvider::class,
        ];
    }

    /**
     * Define the environment setup.
     */
    protected function defineEnvironment($app): void
    {
        tap($app->make(Repository::class), function (Repository $config): void {
            $config->set('cache.default', 'array');
        });
    }
}
