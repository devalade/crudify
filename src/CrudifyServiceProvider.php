<?php

namespace Crudify;

use Crudify\Commands\CrudGenerateCommand;
use Crudify\Commands\CrudStubsCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

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
                __DIR__.'/../stubs' => base_path('stubs/crudify'),
            ], 'crudify-stubs');
        }

        $this->discoverVoltRoutes();
    }

    protected function discoverVoltRoutes(): void
    {
        $pagesPath = resource_path('views/pages');

        if (! is_dir($pagesPath)) {
            return;
        }

        $files = Finder::create()
            ->in($pagesPath)
            ->name('*.blade.php')
            ->files();

        $added = false;

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $filename = basename($relativePath, '.blade.php');

            $directory = dirname($relativePath);
            $directory = $directory === '.' ? '' : '/'.$directory;

            $segments = array_values(array_filter(explode('/', trim($directory, '/'))));
            $resource = $segments[0] ?? null;

            if (! $resource) {
                continue;
            }

            $fullRoutePath = '/'.$resource;

            if ($filename !== 'index') {
                if (in_array($filename, ['edit', 'show'])) {
                    $fullRoutePath .= '/{'.Str::singular($resource).'}';
                }
                $fullRoutePath .= '/'.$filename;
            }

            $sfcComponent = 'pages::'.$resource.'.'.$filename;
            $phpComponent = 'App\\Livewire\\Pages\\'.Str::studly($resource).'\\'.Str::studly($filename);

            $isSfc = file_exists($file->getPathname()) && $this->isVoltSfc($file->getPathname());
            $isPhpClass = class_exists($phpComponent);

            if (! $isSfc && ! $isPhpClass) {
                continue;
            }

            $routeName = $filename === 'index'
                ? "{$resource}.index"
                : "{$resource}.{$filename}";

            if ($isSfc) {
                if (! Route::hasMacro('livewire')) {
                    continue;
                }
                // @phpstan-ignore-next-line livewire() is provided by livewire/volt package when installed
                Route::livewire($fullRoutePath, $sfcComponent)->name($routeName);
            } else {
                Route::get($fullRoutePath, $phpComponent)->name($routeName);
            }
            $added = true;
        }

        if ($added) {
            $this->callAfterResolving('router', function ($router) {
                $router->getRoutes()->refreshNameLookups();
            });
        }
    }

    protected function isVoltSfc(string $path): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);

        return str_contains($content, 'new')
            && str_contains($content, 'class extends Component')
            && str_contains($content, '#[Layout');
    }
}
