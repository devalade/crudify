<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class FactoryGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $namespace = 'Database\Factories';
        $modelBase = class_basename($model);
        $class = $modelBase.'Factory';
        $path = database_path("factories/{$class}.php");

        $fields = $this->fieldParser->getFields();
        $fieldDefs = [];
        $uses = [];

        foreach ($this->getRelationships() as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $foreignKey = $this->relationshipForeignKey($rel);

                if ($this->hasField($foreignKey)) {
                    continue;
                }

                $relatedModel = $rel['model'];
                $fieldDefs[] = "'{$foreignKey}' => \\App\\Models\\{$relatedModel}::factory(),";
            }
        }

        foreach ($fields as $field) {
            if ($field['name'] === 'id') {
                continue;
            }

            $faker = $this->getFakerMethod($field);
            $fieldDefs[] = "'{$field['name']}' => {$faker},";

            if ($field['type'] === 'foreign') {
                $foreignTable = is_string($field['foreign_table'] ?? null) ? $field['foreign_table'] : null;

                if ($foreignTable !== null) {
                    $modelClass = Str::studly(Str::singular($foreignTable));

                    if ($modelClass !== $modelBase) {
                        $uses[] = "use App\\Models\\{$modelClass};";
                    }
                }
            }
        }

        $fieldsStr = implode("\n            ", $fieldDefs);
        $usesStr = implode("\n", array_unique($uses));

        $configure = $this->generateConfigure($modelBase);

        $stub = $this->getStub('factory');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ fields }}', $fieldsStr, $stub);
        $stub = str_replace('{{ uses }}', $usesStr, $stub);
        $stub = str_replace('{{ configure }}', $configure, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getFakerMethod(array $field): string
    {
        $type = is_string($field['type'] ?? null) ? $field['type'] : 'string';

        if (in_array($type, ['image', 'file', 'video'], true)) {
            $extension = $type === 'video' ? 'mp4' : 'jpg';

            if ($field['multiple'] ?? false) {
                return "[fake()->word().'.{$extension}']";
            }

            return "fake()->word().'.{$extension}'";
        }

        return match ($type) {
            'string' => 'fake()->word()',
            'text' => 'fake()->paragraph()',
            'integer', 'bigint' => 'fake()->randomNumber()',
            'float', 'double', 'decimal' => 'fake()->randomFloat(2, 0, 1000)',
            'boolean' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'datetime', 'timestamp' => 'fake()->dateTime()',
            'time' => 'fake()->time()',
            'email' => 'fake()->safeEmail()',
            'uuid' => 'fake()->uuid()',
            'json' => "'[]'",
            'foreign' => $this->getForeignFaker($field),
            default => 'fake()->word()',
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getForeignFaker(array $field): string
    {
        $foreignTable = is_string($field['foreign_table'] ?? null) ? $field['foreign_table'] : null;

        if ($foreignTable !== null) {
            $modelClass = Str::studly(Str::singular($foreignTable));

            return "{$modelClass}::factory()";
        }

        return 'fake()->randomDigitNotNull()';
    }

    protected function generateConfigure(string $modelBase): string
    {
        $belongsToMany = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany');

        if ($belongsToMany->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($belongsToMany as $rel) {
            $name = Str::plural($rel['name']);
            $relatedModel = $rel['model'];
            $lines[] = "        \$model->{$name}()->attach(\\App\\Models\\{$relatedModel}::inRandomOrder()->take(rand(1, 3))->pluck('id'));";
        }

        $attachCode = implode("\n", $lines);

        return <<<PHP
    public function configure(): static
    {
        return \$this->afterCreating(function ({$modelBase} \$model) {
{$attachCode}
        });
    }
PHP;
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['factory'];
    }
}
