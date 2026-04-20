<?php

namespace Crudify\Generators;

class ModelGenerator extends BaseGenerator
{
    public function generate(string $model): array
    {
        $namespace = 'App\\Models';
        $class = class_basename($model);
        $path = $this->getPath($namespace, $class);

        $fillable = $this->fieldParser->getFillable();
        $casts = $this->fieldParser->getCasts();

        $fillableStr = collect($fillable)
            ->map(fn($field) => "'{$field}'")
            ->implode(",\n        ");

        $castsStr = collect($casts)
            ->map(fn($cast, $field) => "'{$field}' => '{$cast}'")
            ->implode(",\n        ");

        $traits = [];
        $uses = [];

        if ($this->softDeletes) {
            $uses[] = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
            $traits[] = 'SoftDeletes';
        }

        $usesStr = implode("\n", $uses);
        $traitsStr = empty($traits) ? '' : 'use ' . implode(', ', $traits) . ';';

        $stub = $this->getStub('model');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ fillable }}', $fillableStr, $stub);
        $stub = str_replace('{{ casts }}', $castsStr, $stub);
        $stub = str_replace('{{ uses }}', $usesStr, $stub);
        $stub = str_replace('{{ traits }}', $traitsStr, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    public function types(): array
    {
        return ['model'];
    }
}
