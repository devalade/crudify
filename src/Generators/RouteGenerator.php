<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class RouteGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $modelBase = class_basename($model);
        $resource = $this->kebabCase(Str::plural($modelBase));
        $modelVar = $this->camelCase($modelBase);
        $livewireNamespace = Str::plural($modelBase);

        $stub = $this->getStub('routes');
        $stub = str_replace('{{ resource }}', $resource, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ livewireNamespace }}', $livewireNamespace, $stub);

        $routePath = base_path('routes/web.php');
        $existingContent = '';

        if ($this->files->exists($routePath)) {
            $existingContent = $this->files->get($routePath);
        }

        $marker = "// CRUDify Routes: {$resource}";

        if (str_contains($existingContent, $marker)) {
            return [$routePath];
        }

        $output = "\n{$marker}\n".trim($stub)."\n// End CRUDify Routes: {$resource}\n";

        $this->files->append($routePath, $output);

        return [$routePath];
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['route'];
    }
}
