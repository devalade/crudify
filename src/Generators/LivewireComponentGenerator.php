<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class LivewireComponentGenerator extends BaseGenerator
{
    /** @return array<string> */
    /** @return array<string> */
    public function generate(string $model): array
    {
        $modelBase = class_basename($model);
        $modelVar = $this->camelCase($modelBase);
        $models = $this->pluralize($modelVar);
        $kebabModels = $this->kebabCase($models);
        $viewPath = str_replace('/', '.', $kebabModels);
        $pluralBase = Str::plural($modelBase);

        $paths = [];
        $paths = array_merge($paths, $this->generateIndex($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));
        $paths = array_merge($paths, $this->generateCreate($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));
        $paths = array_merge($paths, $this->generateEdit($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));
        $paths = array_merge($paths, $this->generateShow($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));

        return $paths;
    }

    /** @return array<string> */
    protected function generateIndex(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Index';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Index.php");

        $fields = $this->fieldParser->getFields();
        $searchables = collect($fields)->filter(fn ($f) => in_array($f['type'], ['string', 'text', 'email']))->take(3);

        $searchablesProps = $searchables->map(fn ($f) => "public string \${$f['name']} = '';")->implode("\n    ");

        $searchConditions = $searchables->map(fn ($f) => "\$q->orWhere('{$f['name']}', 'like', '%' . \$this->search . '%');")->implode("\n                    ");

        $with = $this->getWithClause();

        $stub = $this->getStub('livewire-index');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ title }}', $pluralBase, $stub);
        $stub = str_replace('{{ searchables }}', $searchablesProps ? "\n    {$searchablesProps}\n" : '', $stub);
        $stub = str_replace('{{ searchConditions }}', $searchConditions ?: '// Add search conditions', $stub);
        $stub = str_replace('{{ with }}', $with, $stub);
        $stub = str_replace('{{ models }}', $models, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateCreate(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Create';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Create.php");

        $fields = $this->fieldParser->getFields();
        $properties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(fn ($f) => "public {$this->getPropertyType($f)} \${$f['name']};")
            ->implode("\n    ");

        $stub = $this->getStub('livewire-create');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ title }}', 'Create '.$modelBase, $stub);
        $stub = str_replace('{{ properties }}', $properties, $stub);
        $stub = str_replace('{{ route }}', $kebabModels, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateEdit(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Edit';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Edit.php");

        $fields = $this->fieldParser->getFields();
        $properties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(fn ($f) => "public {$this->getPropertyType($f)} \${$f['name']};")
            ->implode("\n    ");

        $fillProperties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(fn ($f) => "\$this->{$f['name']} = \${$modelVar}->{$f['name']};")
            ->implode("\n        ");

        $stub = $this->getStub('livewire-edit');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', 'Edit '.$modelBase, $stub);
        $stub = str_replace('{{ properties }}', $properties, $stub);
        $stub = str_replace('{{ fillProperties }}', $fillProperties, $stub);
        $stub = str_replace('{{ route }}', $kebabModels, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateShow(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Show';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Show.php");

        $stub = $this->getStub('livewire-show');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ route }}', $kebabModels, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getPropertyType(array $field): string
    {
        return match ($field['type']) {
            'integer', 'bigint', 'float', 'double', 'decimal' => 'int|float',
            'boolean' => 'bool',
            default => 'string',
        };
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['livewire'];
    }
}
