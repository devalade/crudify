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

it('patches existing layout without duplicating directives', function () {
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
    $this->artisan('crudify:setup')
        ->assertSuccessful();

    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect(substr_count($layout, '@fluxAppearance'))->toBe(1);
    expect(substr_count($layout, "@vite(['resources/css/app.css', 'resources/js/app.js'])"))->toBe(1);
    expect(substr_count($layout, '@livewireScripts'))->toBe(1);
    expect(substr_count($layout, '@fluxScripts'))->toBe(1);
});

it('respects custom layout option', function () {
    $this->artisan('crudify:setup --layout=admin/app.blade.php')
        ->assertSuccessful();

    expect(file_exists(resource_path('views/admin/app.blade.php')))->toBeTrue();
});
