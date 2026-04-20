<?php

namespace Crudify\Tests;

use Crudify\CrudifyServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, string>
     */
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
        $app->useAppPath($basePath.'/app');
        $app->useDatabasePath($basePath.'/database');
    }
}
