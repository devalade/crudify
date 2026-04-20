<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class ControllerGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $namespace = 'App\\Http\\Controllers';
        $modelBase = class_basename($model);
        $class = Str::plural($modelBase).'Controller';
        $path = $this->getPath($namespace, $class);

        $modelVar = $this->camelCase($modelBase);
        $models = $this->pluralize($modelVar);
        $resource = $this->kebabCase(Str::plural($modelBase));
        $storeRequest = 'Store'.$modelBase.'Request';
        $updateRequest = 'Update'.$modelBase.'Request';

        $stub = $this->getStub('controller');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ storeRequest }}', $storeRequest, $stub);
        $stub = str_replace('{{ updateRequest }}', $updateRequest, $stub);

        $stub = str_replace('{{ indexBody }}', $this->indexBody($modelBase, $models), $stub);
        $stub = str_replace('{{ createBody }}', $this->createBody($models), $stub);
        $stub = str_replace('{{ storeBody }}', $this->storeBody($modelBase, $resource), $stub);
        $stub = str_replace('{{ showBody }}', $this->showBody($modelVar, $models), $stub);
        $stub = str_replace('{{ editBody }}', $this->editBody($modelVar, $models), $stub);
        $stub = str_replace('{{ updateBody }}', $this->updateBody($modelVar, $resource), $stub);
        $stub = str_replace('{{ destroyBody }}', $this->destroyBody($modelVar, $resource), $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    protected function indexBody(string $modelBase, string $models): string
    {
        return <<<PHP
return view('{$models}.index', [
            '{$models}' => {$modelBase}::latest()->paginate(10),
        ]);
PHP;
    }

    protected function createBody(string $models): string
    {
        return "return view('{$models}.create');";
    }

    protected function storeBody(string $modelBase, string $resource): string
    {
        return <<<PHP
\$validated = \$request->validated();
        {$modelBase}::create(\$validated);
        return redirect()->route('{$resource}.index');
PHP;
    }

    protected function showBody(string $modelVar, string $models): string
    {
        return "return view('{$models}.show', ['{$modelVar}' => \${$modelVar}]);";
    }

    protected function editBody(string $modelVar, string $models): string
    {
        return "return view('{$models}.edit', ['{$modelVar}' => \${$modelVar}]);";
    }

    protected function updateBody(string $modelVar, string $resource): string
    {
        return <<<PHP
\$validated = \$request->validated();
        \${$modelVar}->update(\$validated);
        return redirect()->route('{$resource}.index');
PHP;
    }

    protected function destroyBody(string $modelVar, string $resource): string
    {
        return <<<PHP
\${$modelVar}->delete();
        return redirect()->route('{$resource}.index');
PHP;
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['controller'];
    }
}
