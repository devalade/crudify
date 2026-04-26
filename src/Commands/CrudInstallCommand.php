<?php

namespace Crudify\Commands;

use Crudify\Support\ExternalCommandRunner;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CrudInstallCommand extends Command
{
    protected $signature = 'crudify:install
                            {--volt : Install livewire/volt for single-file components}
                            {--skip-npm : Skip npm installation for Tailwind CSS}
                            {--force : Overwrite generated setup files}
                            {--layout= : Relative Blade layout path under resources/views}';

    protected $description = 'Install Flux, optional Volt, Tailwind dependencies, and Crudify frontend setup';

    public function __construct(
        protected Filesystem $files,
        protected ExternalCommandRunner $runner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->files->exists(base_path('composer.json'))) {
            $this->error('composer.json not found. Run this command from your Laravel app root.');

            return self::FAILURE;
        }

        if (! $this->installComposerPackages()) {
            return self::FAILURE;
        }

        if (! $this->option('skip-npm') && ! $this->installNpmPackages()) {
            return self::FAILURE;
        }

        $setupExitCode = $this->call('crudify:setup', array_filter([
            '--force' => (bool) $this->option('force'),
            '--layout' => is_string($this->option('layout')) ? $this->option('layout') : null,
        ], fn ($value) => $value !== null && $value !== false));

        if ($setupExitCode !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Linking storage directory...');
        $this->call('storage:link');

        $this->newLine();
        $this->info('Crudify install complete.');
        $this->line('Installed/verified: Livewire dependency from Crudify, Flux, Tailwind CSS, Vite Tailwind plugin.');

        if ((bool) $this->option('volt')) {
            $this->line('Installed/verified: Volt.');
        }

        $this->line('Next step: npm run dev');

        return self::SUCCESS;
    }

    protected function installComposerPackages(): bool
    {
        $packages = array_values(array_filter([
            'livewire/flux',
            $this->option('volt') ? 'livewire/volt' : null,
        ], function (?string $package): bool {
            return $package !== null && ! $this->composerPackageInstalled($package);
        }));

        if ($packages === []) {
            $this->info('Composer packages already installed.');

            return true;
        }

        return $this->runExternalCommand(array_merge(['composer', 'require'], $packages), 'Composer install failed.');
    }

    protected function installNpmPackages(): bool
    {
        if (! $this->files->exists(base_path('package.json'))) {
            $this->warn('package.json not found. Skipping npm installation. Install `tailwindcss` and `@tailwindcss/vite` manually if needed.');

            return true;
        }

        $packages = array_values(array_filter([
            'tailwindcss',
            '@tailwindcss/vite',
        ], fn (string $package): bool => ! $this->npmPackageInstalled($package)));

        if ($packages === []) {
            $this->info('NPM packages already installed.');

            return true;
        }

        return $this->runExternalCommand(array_merge(['npm', 'install', '-D'], $packages), 'NPM install failed.');
    }

    protected function composerPackageInstalled(string $package): bool
    {
        $composerJson = json_decode($this->files->get(base_path('composer.json')), true);
        $requires = array_merge($composerJson['require'] ?? [], $composerJson['require-dev'] ?? []);

        return array_key_exists($package, $requires);
    }

    protected function npmPackageInstalled(string $package): bool
    {
        $packageJson = json_decode($this->files->get(base_path('package.json')), true);
        $requires = array_merge($packageJson['dependencies'] ?? [], $packageJson['devDependencies'] ?? []);

        return array_key_exists($package, $requires);
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function runExternalCommand(array $command, string $failureMessage): bool
    {
        $this->line('Running: '.implode(' ', $command));

        $exitCode = $this->runner->run($command, base_path(), function (string $buffer): void {
            $this->output->write($buffer);
        });

        if ($exitCode !== 0) {
            $this->error($failureMessage);

            return false;
        }

        return true;
    }
}
