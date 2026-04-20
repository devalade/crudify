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

        if (file_exists($routePath)) {
            $content = file_get_contents($routePath);
            $existingContent = $content !== false ? $content : '';
        }

        $marker = "// CRUDify Routes: {$resource}";

        if (str_contains($existingContent, $marker)) {
            return [$routePath];
        }

        $output = "\n{$marker}\n".trim($stub)."\n// End CRUDify Routes: {$resource}\n";

        file_put_contents($routePath, $output, FILE_APPEND);

        return [$routePath];
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['route'];
    }
}
