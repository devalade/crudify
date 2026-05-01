<?php

namespace Crudify\Commands;

use Crudify\Support\LayoutResolver;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CrudSetupCommand extends Command
{
    protected $signature = 'crudify:setup
                            {--force : Overwrite generated setup files}
                            {--layout= : Relative Blade layout path under resources/views}';

    protected $description = 'Set up Tailwind entry files and Flux layout hooks for generated Crudify views';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle(): int
    {
        $cssPath = resource_path('css/app.css');
        $jsPath = resource_path('js/app.js');
        $layoutResolver = new LayoutResolver($this->files);
        $layoutOption = is_string($this->option('layout')) ? $this->option('layout') : null;
        $layoutPath = $layoutResolver->path($layoutOption);
        $layoutView = $layoutResolver->pathToView($layoutPath);
        $viteConfigPath = $this->resolveViteConfigPath();
        $overwriteLayout = $this->shouldOverwriteLayout($layoutPath);

        $this->ensureCssEntry($cssPath);
        $this->ensureJsEntry($jsPath);
        $this->ensureLayout($layoutPath, $overwriteLayout);
        $this->ensureViteConfig($viteConfigPath);

        $this->info('Crudify frontend setup complete.');
        $this->line("CSS entry: {$cssPath}");
        $this->line("JS entry: {$jsPath}");
        $this->line("Layout: {$layoutPath}");
        $this->line("Livewire layout view: {$layoutView}");
        if ($viteConfigPath !== null) {
            $this->line("Vite config: {$viteConfigPath}");
        }
        $this->line('Next step: composer require livewire/flux');

        return self::SUCCESS;
    }

    protected function ensureCssEntry(string $path): void
    {
        $stub = implode("\n", [
            "@import 'tailwindcss';",
            "@import '../../vendor/livewire/flux/dist/flux.css';",
            '@custom-variant dark (&:where(.dark, .dark *));',
            "@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';",
            "@source '../../storage/framework/views/*.php';",
            "@source '../**/*.blade.php';",
            "@source '../**/*.js';",
            '',
        ]);

        if ($this->shouldOverwrite($path)) {
            $this->putFile($path, $stub);

            return;
        }

        $content = $this->readFile($path);
        $lines = [
            "@import 'tailwindcss';",
            "@import '../../vendor/livewire/flux/dist/flux.css';",
            '@custom-variant dark (&:where(.dark, .dark *));',
            "@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';",
            "@source '../../storage/framework/views/*.php';",
            "@source '../**/*.blade.php';",
            "@source '../**/*.js';",
        ];

        foreach ($lines as $line) {
            if (! str_contains($content, $line)) {
                $content = rtrim($content)."\n".$line."\n";
            }
        }

        $this->putFile($path, $content);
    }

    protected function ensureJsEntry(string $path): void
    {
        if (! $this->shouldOverwrite($path) && $this->files->exists($path)) {
            return;
        }

        $this->putFile($path, "// Crudify Vite entry.\n");
    }

    protected function ensureLayout(string $path, bool $overwrite): void
    {
        if ($overwrite || ! $this->files->exists($path)) {
            $this->putFile($path, $this->layoutStub());

            return;
        }

        $content = $this->readFile($path);

        if (! str_contains($content, '@fluxAppearance')) {
            $content = $this->injectBeforeClosingTag($content, '</head>', "    @fluxAppearance\n");
        }

        if (! str_contains($content, 'resources/css/app.css') || ! str_contains($content, 'resources/js/app.js')) {
            $content = $this->injectBeforeClosingTag($content, '</head>', "    @vite(['resources/css/app.css', 'resources/js/app.js'])\n");
        }

        if (! str_contains($content, '@livewireScripts')) {
            $content = $this->injectBeforeClosingTag($content, '</body>', "    @livewireScripts\n");
        }

        if (! str_contains($content, '@fluxScripts')) {
            $content = $this->injectBeforeClosingTag($content, '</body>', "    @fluxScripts\n");
        }

        $this->putFile($path, $content);
    }

    protected function shouldOverwriteLayout(string $path): bool
    {
        if (! $this->files->exists($path)) {
            return true;
        }

        if ((bool) $this->option('force')) {
            return true;
        }

        return $this->confirm("Crudify found an existing layout at {$path}. Do you want to overwrite it?", false);
    }

    protected function resolveViteConfigPath(): ?string
    {
        $candidates = [
            base_path('vite.config.js'),
            base_path('vite.config.mjs'),
            base_path('vite.config.ts'),
            base_path('vite.config.mts'),
        ];

        foreach ($candidates as $candidate) {
            if ($this->files->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function shouldOverwrite(string $path): bool
    {
        return (bool) $this->option('force') || ! $this->files->exists($path);
    }

    protected function putFile(string $path, string $content): void
    {
        $directory = dirname($path);

        if (! $this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, $content);
    }

    protected function readFile(string $path): string
    {
        $content = $this->files->get($path);

        return is_string($content) ? $content : '';
    }

    protected function injectBeforeClosingTag(string $content, string $tag, string $snippet): string
    {
        if (str_contains($content, $tag)) {
            return str_replace($tag, $snippet.$tag, $content);
        }

        return rtrim($content)."\n".$snippet;
    }

    protected function ensureViteConfig(?string $path): void
    {
        if ($path === null) {
            $this->warn('No Vite config found. Add `@tailwindcss/vite` to your Vite plugins manually if your app uses Vite.');

            return;
        }

        $content = $this->readFile($path);
        $content = preg_replace('/^import\s+tailwindcss\s+from\s+[\'"]@tailwindcss\/vite[\'"];?\s*$/m', '', $content) ?? $content;
        $content = ltrim($content);

        if (preg_match_all('/^import .*$/m', $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
            $imports = $matches[0];
            $lastImport = $imports[count($imports) - 1];
            $offset = $lastImport[1] + strlen($lastImport[0]);
            $content = substr($content, 0, $offset)."\nimport tailwindcss from '@tailwindcss/vite'".substr($content, $offset);
        } else {
            $content = "import tailwindcss from '@tailwindcss/vite'\n".$content;
        }

        if (! str_contains($content, 'tailwindcss()')) {
            $content = preg_replace(
                '/plugins:\s*\[/',
                "plugins: [\n        tailwindcss(),",
                $content,
                1
            ) ?? $content;
        }

        $this->putFile($path, $content);
    }

    protected function layoutStub(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
    {{ $slot }}

    @livewireScripts
    @fluxScripts
</body>
</html>
BLADE;
    }
}
