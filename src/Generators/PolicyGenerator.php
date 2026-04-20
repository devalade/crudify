<?php

namespace Crudify\Generators;

class PolicyGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $namespace = 'App\\Policies';
        $class = class_basename($model).'Policy';
        $path = $this->getPath($namespace, $class);

        $modelBase = class_basename($model);
        $modelVar = $this->camelCase($modelBase);
        $modelNamespace = 'App\\Models';

        $stub = $this->getStub('policy');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', $modelNamespace, $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);

        $restoreMethod = <<<PHP
/**
     * Determine if the given policy authorizes the user to restore.
     */
    public function restore({$modelBase} \${$modelVar}): bool
    {
        return true;
    }
PHP;

        $forceDeleteMethod = <<<PHP
/**
     * Determine if the given policy authorizes the user to force delete.
     */
    public function forceDelete({$modelBase} \${$modelVar}): bool
    {
        return true;
    }
PHP;

        $stub = str_replace('{{ restoreMethod }}', $restoreMethod, $stub);
        $stub = str_replace('{{ forceDeleteMethod }}', $forceDeleteMethod, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['policy'];
    }
}
