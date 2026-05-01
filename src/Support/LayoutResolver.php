<?php

namespace Crudify\Support;

use Illuminate\Filesystem\Filesystem;

class LayoutResolver
{
    public function __construct(protected Filesystem $files) {}

    public function path(?string $layout = null): string
    {
        if (is_string($layout) && $layout !== '') {
            return resource_path('views/'.ltrim($layout, '/'));
        }

        return resource_path('views/components/layouts/app.blade.php');
    }

    public function view(?string $layout = null): string
    {
        return $this->pathToView($this->path($layout));
    }

    public function pathToView(string $path): string
    {
        $viewsPath = rtrim(resource_path('views'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $relative = str_starts_with($path, $viewsPath)
            ? substr($path, strlen($viewsPath))
            : $path;

        $relative = preg_replace('/\.blade\.php$/', '', $relative) ?? $relative;

        return str_replace(DIRECTORY_SEPARATOR, '.', $relative);
    }
}
