<?php

namespace Crudify\Generators;

class SeederGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $namespace = 'Database\Seeders';
        $modelBase = class_basename($model);
        $class = $modelBase.'Seeder';
        $path = database_path("seeders/{$class}.php");

        $stub = $this->getStub('seeder');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['seeder'];
    }
}
