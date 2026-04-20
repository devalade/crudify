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
                $foreignKey = Str::snake($rel['name']).'_id';
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

        $stub = $this->getStub('factory');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ fields }}', $fieldsStr, $stub);
        $stub = str_replace('{{ uses }}', $usesStr, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getFakerMethod(array $field): string
    {
        $type = is_string($field['type'] ?? null) ? $field['type'] : 'string';

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

    /** @return array<string> */
    public function types(): array
    {
        return ['factory'];
    }
}
