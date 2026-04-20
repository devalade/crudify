<?php

namespace Crudify\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Crudify\CrudifyServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CrudifyServiceProvider::class,
        ];
    }

    protected function swapAppPaths(string $basePath): void
    {
        $app = $this->app;

        $app->setBasePath($basePath);
        $app->useAppPath($basePath . '/app');
        $app->useDatabasePath($basePath . '/database');
    }
}
