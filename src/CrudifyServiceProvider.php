<?php

namespace Crudify;

use Illuminate\Support\ServiceProvider;
use Crudify\Commands\CrudGenerateCommand;
use Crudify\Commands\CrudStubsCommand;

class CrudifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CrudGenerateCommand::class,
                CrudStubsCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/crudify'),
            ], 'crudify-stubs');
        }
    }
}
