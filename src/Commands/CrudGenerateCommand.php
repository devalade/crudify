<?php

namespace Crudify\Commands;

use Crudify\FieldParser;
use Crudify\Generators\ControllerGenerator;
use Crudify\Generators\FactoryGenerator;
use Crudify\Generators\FormRequestGenerator;
use Crudify\Generators\Generator;
use Crudify\Generators\LivewireComponentGenerator;
use Crudify\Generators\LivewireViewGenerator;
use Crudify\Generators\MigrationGenerator;
use Crudify\Generators\ModelGenerator;
use Crudify\Generators\PolicyGenerator;
use Crudify\Generators\RouteGenerator;
use Crudify\Generators\SeederGenerator;
use Crudify\RelationshipParser;
use Crudify\YamlParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class CrudGenerateCommand extends Command
{
    protected $signature = 'crudify:generate {model? : The model name (e.g., Post)}
                            {--fields= : Comma-separated field definitions (e.g., title:string,body:text)}
                            {--file= : Path to YAML definition file (overrides --fields)}
                            {--relationships= : Comma-separated relationships (e.g., user:belongsTo:User,comments:hasMany:Comment)}
                            {--only= : Generate only specified types (comma-separated)}
                            {--skip= : Skip specified types (comma-separated)}
                            {--soft-delete : Add soft deletes to model and migration}
                            {--searchable= : Comma-separated searchable fields}
                            {--force : Overwrite existing files}
                            {--dry-run : Preview files without writing them}';

    protected $description = 'Generate full CRUD with Livewire v4 components';

    /** @var array<int, Generator> */
    protected array $generators = [];

    public function handle(): int
    {
        $model = is_string($this->argument('model')) ? $this->argument('model') : '';
        $yamlFile = is_string($this->option('file')) ? $this->option('file') : '';
        $fieldsString = is_string($this->option('fields')) ? $this->option('fields') : '';
        $only = is_string($this->option('only')) ? $this->option('only') : null;
        $skip = is_string($this->option('skip')) ? $this->option('skip') : null;
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($yamlFile) {
            return $this->handleYaml($yamlFile, $only, $skip, $force, $dryRun);
        }

        if (empty($model)) {
            $this->error('Model name is required.');

            return self::FAILURE;
        }

        $model = trim($model);

        if (! $this->validateModelName($model)) {
            return self::FAILURE;
        }

        if (empty($fieldsString)) {
            $this->error('Either --fields or --file option is required.');
            $this->info('Examples:');
            $this->info('  --fields="title:string,body:text,is_published:boolean"');
            $this->info('  --file=crud.yaml');

            return self::FAILURE;
        }

        $fieldParser = new FieldParser;
        $fieldParser->parse($fieldsString);

        $relationshipsString = is_string($this->option('relationships')) ? $this->option('relationships') : '';
        $relationshipParser = new RelationshipParser;

        if ($relationshipsString !== '') {
            $relationshipParser->parse($relationshipsString);
        }

        $softDeletes = (bool) $this->option('soft-delete');

        $this->registerGenerators($fieldParser, $relationshipParser, $force, $dryRun, $softDeletes);

        return $this->runGenerators($model, $only, $skip, $dryRun);
    }

    protected function handleYaml(string $yamlFile, ?string $only, ?string $skip, bool $force, bool $dryRun): int
    {
        if (! file_exists($yamlFile)) {
            $this->error("YAML file not found: {$yamlFile}");

            return self::FAILURE;
        }

        $yamlParser = new YamlParser;
        $yamlParser->parse($yamlFile);

        $model = $yamlParser->getModel();

        if (! $model) {
            $this->error('YAML file must contain a "model" key.');

            return self::FAILURE;
        }

        if (! $this->validateModelName($model)) {
            return self::FAILURE;
        }

        $this->info("Generating CRUD for: {$model}");
        $this->info("Using YAML definition: {$yamlFile}");

        $fieldParser = new FieldParser;
        $fields = $yamlParser->getFields();

        $fieldsString = collect($fields)->map(function ($field) {
            $parts = [$field['name'].':'.$field['type']];

            if ($field['nullable']) {
                $parts[] = 'nullable';
            }
            if ($field['unique']) {
                $parts[] = 'unique';
            }
            if ($field['index']) {
                $parts[] = 'index';
            }
            if ($field['multiple']) {
                $parts[] = 'multiple';
            }
            if ($field['default'] !== null) {
                $parts[] = 'default:'.$field['default'];
            }
            if ($field['foreign_table']) {
                $parts[] = 'foreign:'.$field['foreign_table'];
            }

            return implode(':', $parts);
        })->implode(',');

        $fieldParser->parse($fieldsString);

        $relationshipParser = new RelationshipParser;
        $relationshipParser->setRelationships($yamlParser->getRelationships());

        $softDeletes = $yamlParser->hasSoftDeletes();

        $this->registerGenerators($fieldParser, $relationshipParser, $force, $dryRun, $softDeletes);

        return $this->runGenerators($model, $only, $skip, $dryRun);
    }

    protected function validateModelName(string $model): bool
    {
        $base = class_basename($model);

        if (! preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $base)) {
            $this->error("Invalid model name: {$base}. Must be a valid PHP class name.");

            return false;
        }

        $reserved = [
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
            'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
            'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
            'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
            'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
            'implements', 'include', 'include_once', 'instanceof', 'insteadof',
            'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print',
            'private', 'protected', 'public', 'readonly', 'require', 'require_once',
            'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
            'var', 'while', 'xor', 'yield', '__halt_compiler',
        ];

        if (in_array(strtolower($base), $reserved)) {
            $this->error("Model name '{$base}' is a reserved PHP keyword.");

            return false;
        }

        return true;
    }

    protected function registerGenerators(FieldParser $fieldParser, RelationshipParser $relationshipParser, bool $force, bool $dryRun, bool $softDeletes): void
    {
        $files = new Filesystem;
        $options = [
            'force' => $force,
            'dryRun' => $dryRun,
            'softDeletes' => $softDeletes,
        ];

        $this->generators = [
            new ModelGenerator($files, $fieldParser, $options, $relationshipParser),
            new MigrationGenerator($files, $fieldParser, $options, $relationshipParser),
            new ControllerGenerator($files, $fieldParser, $options, $relationshipParser),
            new FormRequestGenerator($files, $fieldParser, $options, $relationshipParser),
            new PolicyGenerator($files, $fieldParser, $options, $relationshipParser),
            new LivewireComponentGenerator($files, $fieldParser, $options, $relationshipParser),
            new LivewireViewGenerator($files, $fieldParser, $options, $relationshipParser),
            new RouteGenerator($files, $fieldParser, $options, $relationshipParser),
            new FactoryGenerator($files, $fieldParser, $options, $relationshipParser),
            new SeederGenerator($files, $fieldParser, $options, $relationshipParser),
        ];
    }

    protected function runGenerators(string $model, ?string $only, ?string $skip, bool $dryRun): int
    {
        $onlyTypes = $only ? explode(',', $only) : null;
        $skipTypes = $skip ? explode(',', $skip) : [];

        $generated = [];

        foreach ($this->generators as $generator) {
            $types = $generator->types();
            $type = $types[0];

            if ($onlyTypes && ! in_array($type, $onlyTypes)) {
                continue;
            }

            if (in_array($type, $skipTypes)) {
                continue;
            }

            try {
                $files = $generator->generate($model);
                $generated = array_merge($generated, $files);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->info('Dry run — files that would be generated:');
        } else {
            $this->info('Generated files:');
        }

        foreach ($generated as $file) {
            $this->line("  ✓ {$file}");
        }

        $resource = Str::kebab(Str::plural(class_basename($model)));

        $this->info("\nNext steps:");
        $this->line('  1. Run: php artisan migrate');
        $this->line('  2. Ensure you have a layout at resources/views/components/layouts/app.blade.php');
        $this->line("  3. Visit: /{$resource}");

        return self::SUCCESS;
    }
}
