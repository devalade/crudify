<?php

use Crudify\Support\ExternalCommandRunner;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/crudify-install-tests-'.uniqid();
    mkdir($this->tmpDir, 0777, true);

    mkdir($this->tmpDir.'/resources/views', 0755, true);

    file_put_contents($this->tmpDir.'/composer.json', json_encode([
        'require' => [
            'php' => '^8.2',
            'devalade/crudify' => '^1.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($this->tmpDir.'/package.json', json_encode([
        'devDependencies' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($this->tmpDir.'/vite.config.js', <<<'JS'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel(['resources/css/app.css', 'resources/js/app.js']),
    ],
})
JS);

    $this->swapAppPaths($this->tmpDir);

    $runner = new class extends ExternalCommandRunner
    {
        /** @var array<int, array<int, string>> */
        public array $commands = [];

        public function run(array $command, string $workingDirectory, callable $writeOutput): int
        {
            $this->commands[] = $command;
            $writeOutput("ok\n", 'out');

            return 0;
        }
    };

    $this->app->instance(ExternalCommandRunner::class, $runner);
    $this->runner = $runner;
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

it('installs flux and tailwind dependencies then runs setup', function () {
    $this->artisan('crudify:install')
        ->assertSuccessful();

    expect($this->runner->commands)->toBe([
        ['composer', 'require', 'livewire/flux'],
        ['npm', 'install', '-D', 'tailwindcss', '@tailwindcss/vite'],
    ]);

    expect(file_exists(resource_path('css/app.css')))->toBeTrue();
    expect(file_exists(resource_path('views/components/layouts/app.blade.php')))->toBeTrue();
});

it('installs volt when requested', function () {
    $this->artisan('crudify:install --volt')
        ->assertSuccessful();

    expect($this->runner->commands[0])->toBe(['composer', 'require', 'livewire/flux', 'livewire/volt']);
});

it('skips already installed packages', function () {
    file_put_contents($this->tmpDir.'/composer.json', json_encode([
        'require' => [
            'php' => '^8.2',
            'livewire/flux' => '^2.0',
            'livewire/volt' => '^1.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($this->tmpDir.'/package.json', json_encode([
        'devDependencies' => [
            'tailwindcss' => '^4.0',
            '@tailwindcss/vite' => '^4.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->artisan('crudify:install --volt')
        ->assertSuccessful();

    expect($this->runner->commands)->toBe([]);
});
