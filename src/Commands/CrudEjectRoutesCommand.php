<?php

namespace Crudify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class CrudEjectRoutesCommand extends Command
{
    protected $signature = 'crudify:eject-routes';

    protected $description = 'Eject auto-discovered Crudify routes into routes/web.php for manual management';

    public function handle(): int
    {
        $pagesPath = resource_path('views/pages');

        if (! is_dir($pagesPath)) {
            $this->warn('No pages directory found. Nothing to eject.');
            return self::SUCCESS;
        }

        $files = Finder::create()
            ->in($pagesPath)
            ->name('*.blade.php')
            ->files();

        $routesCode = [];
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
                $routesCode[] = "Route::livewire('{$fullRoutePath}', '{$sfcComponent}')->name('{$routeName}');";
            } else {
                $routesCode[] = "Route::get('{$fullRoutePath}', \\{$phpComponent}::class)->name('{$routeName}');";
            }
            
            $added = true;
        }

        if (! $added) {
            $this->warn('No routes to eject.');
            return self::SUCCESS;
        }

        $webPath = base_path('routes/web.php');
        $content = File::exists($webPath) ? File::get($webPath) : "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n";

        $markerStart = '// CRUDify Ejected Routes';
        $markerEnd = '// End CRUDify Ejected Routes';

        if (str_contains($content, $markerStart)) {
            $this->warn('Routes have already been ejected to routes/web.php. Please remove the existing block to eject again.');
            return self::FAILURE;
        }

        $codeBlock = "\n" . $markerStart . "\n" . implode("\n", $routesCode) . "\n" . $markerEnd . "\n";
        
        File::append($webPath, $codeBlock);

        $this->info('Successfully ejected auto-discovered routes to routes/web.php');
        $this->line('Auto-discovery has been completely disabled. You are now in full control of your routes.');

        return self::SUCCESS;
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
