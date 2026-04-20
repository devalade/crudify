<?php

namespace Crudify\Generators;

class ModelGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $namespace = 'App\\Models';
        $class = class_basename($model);
        $path = $this->getPath($namespace, $class);

        $fillable = $this->fieldParser->getFillable();
        $casts = $this->fieldParser->getCasts();

        $fillableStr = collect($fillable)
            ->map(fn ($field) => "'{$field}'")
            ->implode(",\n        ");

        $castsStr = collect($casts)
            ->map(fn ($cast, $field) => "'{$field}' => '{$cast}'")
            ->implode(",\n        ");

        $traits = [];
        $uses = [];

        if ($this->softDeletes) {
            $uses[] = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
            $traits[] = 'SoftDeletes';
        }

        $usesStr = implode("\n", $uses);
        $traitsStr = empty($traits) ? '' : 'use '.implode(', ', $traits).';';

        $stub = $this->getStub('model');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ fillable }}', $fillableStr, $stub);
        $stub = str_replace('{{ casts }}', $castsStr, $stub);
        $stub = str_replace('{{ uses }}', $usesStr, $stub);
        $stub = str_replace('{{ traits }}', $traitsStr, $stub);
        $stub = str_replace('{{ relationships }}', $this->generateRelationships(), $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    protected function generateRelationships(): string
    {
        $relationships = $this->getRelationships();

        if (empty($relationships)) {
            return '';
        }

        $methods = [];

        foreach ($relationships as $rel) {
            $type = is_string($rel['type'] ?? null) ? $rel['type'] : 'belongsTo';
            $name = is_string($rel['name'] ?? null) ? $rel['name'] : 'relation';
            $model = is_string($rel['model'] ?? null) ? $rel['model'] : 'Model';

            $returnType = match ($type) {
                'belongsTo' => '\Illuminate\Database\Eloquent\Relations\BelongsTo',
                'hasMany' => '\Illuminate\Database\Eloquent\Relations\HasMany',
                'hasOne' => '\Illuminate\Database\Eloquent\Relations\HasOne',
                'belongsToMany' => '\Illuminate\Database\Eloquent\Relations\BelongsToMany',
                default => null,
            };

            if ($returnType === null) {
                continue;
            }

            $modelClass = str_contains($model, '\\') ? '\\'.$model : '\\App\\Models\\'.$model;

            $methods[] = <<<PHP
    public function {$name}(): {$returnType}
    {
        return \$this->{$type}({$modelClass}::class);
    }
PHP;
        }

        return "\n\n".implode("\n\n", $methods);
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['model'];
    }
}
