<?php

namespace Crudify\Generators;

use Crudify\FieldParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseGenerator implements Generator
{
    protected Filesystem $files;

    protected FieldParser $fieldParser;

    protected bool $force = false;

    protected bool $dryRun = false;

    protected bool $softDeletes = false;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(Filesystem $files, FieldParser $fieldParser, array $options = [])
    {
        $this->files = $files;
        $this->fieldParser = $fieldParser;
        $this->force = $options['force'] ?? false;
        $this->dryRun = $options['dryRun'] ?? false;
        $this->softDeletes = $options['softDeletes'] ?? false;
    }

    protected function getStub(string $name): string
    {
        $customPath = null;

        try {
            $customPath = base_path("stubs/crudify/{$name}.stub");
        } catch (\Throwable) {
            // base_path() may not be available in testing contexts
        }

        if ($customPath && file_exists($customPath)) {
            $content = file_get_contents($customPath);
            if ($content === false) {
                throw new \RuntimeException("Unable to read stub: {$customPath}");
            }

            return $content;
        }

        $packagePath = __DIR__."/../../stubs/{$name}.stub";

        if (! file_exists($packagePath)) {
            throw new \RuntimeException("Stub not found: {$name} (looked at {$packagePath})");
        }

        $content = file_get_contents($packagePath);
        if ($content === false) {
            throw new \RuntimeException("Unable to read stub: {$packagePath}");
        }

        return $content;
    }

    protected function getPath(string $namespace, string $class): string
    {
        return base_path(str_replace('\\', '/', $namespace).'/'.$class.'.php');
    }

    protected function createFile(string $path, string $content): void
    {
        if ($this->dryRun) {
            return;
        }

        if (file_exists($path) && ! $this->force) {
            throw new \RuntimeException("File already exists: {$path}. Use --force to overwrite.");
        }

        $directory = dirname($path);

        if (! $this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, $content);
    }

    protected function pluralize(string $string): string
    {
        return Str::plural($string);
    }

    protected function camelCase(string $string): string
    {
        return Str::camel($string);
    }

    protected function kebabCase(string $string): string
    {
        return Str::kebab($string);
    }
}
