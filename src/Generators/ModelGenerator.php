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
        $paths = [];

        $fillable = $this->fieldParser->getFillable();
        $casts = $this->fieldParser->getCasts();

        foreach ($this->getRelationships() as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $foreignKey = $this->relationshipForeignKey($rel);

                if (! in_array($foreignKey, $fillable, true)) {
                    $fillable[] = $foreignKey;
                }
            }
        }

        $fillableStr = collect($fillable)
            ->map(fn ($field) => "'{$field}'")
            ->implode(",\n        ");

        $castsStr = collect($casts)
            ->map(fn ($cast, $field) => "'{$field}' => '{$cast}'")
            ->implode(",\n        ");

        $traits = ['HasFactory'];
        $uses = ['use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;'];

        if ($this->softDeletes) {
            $uses[] = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
            $traits[] = 'SoftDeletes';
        }

        $usesStr = implode("\n", $uses);
        $traitsStr = 'use '.implode(', ', $traits).';';

        $stub = $this->getStub('model');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ fillable }}', $fillableStr, $stub);
        $stub = str_replace('{{ casts }}', $castsStr, $stub);
        $stub = str_replace('{{ uses }}', $usesStr, $stub);
        $stub = str_replace('{{ traits }}', $traitsStr, $stub);
        $stub = str_replace('{{ relationships }}', $this->generateRelationships(), $stub);

        $this->createFile($path, $stub);

        $paths[] = $path;

        return array_merge($paths, $this->generateMissingRelatedModels($class));
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
            $arguments = "{$modelClass}::class";

            if ($type === 'belongsTo' && is_string($rel['foreign_key'] ?? null) && $rel['foreign_key'] !== '') {
                $arguments .= ", '{$rel['foreign_key']}'";
            }

            $methods[] = <<<PHP
    public function {$name}(): {$returnType}
    {
        return \$this->{$type}({$arguments});
    }
PHP;
        }

        return "\n\n".implode("\n\n", $methods);
    }

    /**
     * @return array<string>
     */
    protected function generateMissingRelatedModels(string $model): array
    {
        $paths = [];

        foreach ($this->getRelationships() as $rel) {
            if (($rel['type'] ?? null) !== 'belongsToMany' || ! is_string($rel['model'] ?? null)) {
                continue;
            }

            $relatedClass = class_basename($rel['model']);

            if ($relatedClass === $model) {
                continue;
            }

            $path = $this->getPath('App\\Models', $relatedClass);

            if (file_exists($path)) {
                $paths[] = $path;

                continue;
            }

            $stub = $this->getStub('model');
            $stub = str_replace('{{ namespace }}', 'App\\Models', $stub);
            $stub = str_replace('{{ class }}', $relatedClass, $stub);
            $stub = str_replace('{{ fillable }}', '', $stub);
            $stub = str_replace('{{ casts }}', '', $stub);
            $stub = str_replace('{{ uses }}', 'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;', $stub);
            $stub = str_replace('{{ traits }}', 'use HasFactory;', $stub);
            $stub = str_replace('{{ relationships }}', '', $stub);

            $this->createFile($path, $stub);
            $paths[] = $path;
        }

        return $paths;
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['model'];
    }
}
