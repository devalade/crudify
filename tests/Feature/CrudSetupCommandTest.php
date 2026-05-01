<?php

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/crudify-setup-tests-'.uniqid();
    mkdir($this->tmpDir, 0777, true);

    mkdir($this->tmpDir.'/resources/views', 0755, true);

    $this->swapAppPaths($this->tmpDir);
});

afterEach(function () {
    if (is_dir($this->tmpDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->tmpDir);
    }
});

it('creates tailwind entries and default layout when missing', function () {
    $this->artisan('crudify:setup')
        ->assertSuccessful();

    expect(file_exists(resource_path('css/app.css')))->toBeTrue();
    expect(file_exists(resource_path('js/app.js')))->toBeTrue();
    expect(file_exists(resource_path('views/components/layouts/app.blade.php')))->toBeTrue();

    $css = file_get_contents(resource_path('css/app.css'));
    $layout = file_get_contents(resource_path('views/components/layouts/app.blade.php'));

    expect($css)->toContain("@import 'tailwindcss';");
    expect($css)->toContain("@import '../../vendor/livewire/flux/dist/flux.css';");
    expect($css)->toContain('@custom-variant dark (&:where(.dark, .dark *));');
    expect($layout)->toContain('@fluxAppearance');
    expect($layout)->toContain("@vite(['resources/css/app.css', 'resources/js/app.js'])");
    expect($layout)->toContain('@livewireScripts');
    expect($layout)->toContain('@fluxScripts');
});

it('creates the component layout even when a Laravel app layout exists', function () {
    mkdir(resource_path('views/layouts'), 0755, true);
    file_put_contents(resource_path('views/layouts/app.blade.php'), <<<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <title>Test</title>
</head>
<body>
    {{ $slot }}
</body>
</html>
BLADE);

    $this->artisan('crudify:setup')
        ->assertSuccessful();

    expect(file_exists(resource_path('views/components/layouts/app.blade.php')))->toBeTrue();

    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($layout)->not->toContain('@fluxAppearance');
    expect($layout)->not->toContain('@livewireScripts');
});

it('asks before overwriting an existing component layout', function () {
    mkdir(resource_path('views/components/layouts'), 0755, true);
    $layoutPath = resource_path('views/components/layouts/app.blade.php');
    file_put_contents($layoutPath, <<<'BLADE'
<x-layouts.app>
    Existing application shell
</x-layouts.app>
BLADE);

    $this->artisan('crudify:setup')
        ->expectsConfirmation("Crudify found an existing layout at {$layoutPath}. Do you want to overwrite it?", 'yes')
        ->assertSuccessful();

    $layout = file_get_contents($layoutPath);

    expect($layout)->toContain('<!DOCTYPE html>');
    expect($layout)->toContain('@fluxAppearance');
    expect($layout)->not->toContain('Existing application shell');
});

it('keeps an existing component layout when overwrite is declined', function () {
    mkdir(resource_path('views/components/layouts'), 0755, true);
    $layoutPath = resource_path('views/components/layouts/app.blade.php');
    file_put_contents($layoutPath, <<<'BLADE'
<x-layouts.app>
    Existing application shell
</x-layouts.app>
BLADE);

    $this->artisan('crudify:setup')
        ->expectsConfirmation("Crudify found an existing layout at {$layoutPath}. Do you want to overwrite it?", 'no')
        ->assertSuccessful();

    $layout = file_get_contents($layoutPath);

    expect($layout)->toContain('Existing application shell');
    expect($layout)->toContain('@fluxAppearance');
});

it('respects custom layout option', function () {
    $this->artisan('crudify:setup --layout=admin/app.blade.php')
        ->assertSuccessful();

    expect(file_exists(resource_path('views/admin/app.blade.php')))->toBeTrue();
});

it('patches vite config without duplicating tailwind import', function () {
    file_put_contents(base_path('vite.config.js'), <<<'JS'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel(['resources/css/app.css', 'resources/js/app.js']),
        tailwindcss(),
    ],
});
JS);

    $this->artisan('crudify:setup')
        ->assertSuccessful();

    $viteConfig = file_get_contents(base_path('vite.config.js'));

    expect(substr_count($viteConfig, '@tailwindcss/vite'))->toBe(1);
    expect(substr_count($viteConfig, 'tailwindcss()'))->toBe(1);
});
