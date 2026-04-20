<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class LivewireViewGenerator extends BaseGenerator
{
    public function generate(string $model): array
    {
        $modelBase = class_basename($model);
        $modelVar = $this->camelCase($modelBase);
        $models = $this->pluralize($modelVar);
        $kebabModels = $this->kebabCase($models);
        $viewPath = str_replace('/', '.', $kebabModels);

        $paths = array_merge($paths ?? [], $this->generateIndexView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath));
        $paths = array_merge($paths, $this->generateCreateView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath));
        $paths = array_merge($paths, $this->generateEditView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath));
        $paths = array_merge($paths, $this->generateShowView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath));

        return $paths;
    }

    protected function generateIndexView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath)
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/index.blade.php");

        $fields = $this->fieldParser->getFields();
        $displayFields = collect($fields)->reject(fn($f) => $f['name'] === 'id')->take(5);

        $headers = $displayFields->map(fn($f) => "<th class=\"px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer\" wire:click=\"sortBy('{$f['name']}')\">\n                        " . Str::title(str_replace('_', ' ', $f['name'])) . "\n                        @if(\$sortField === '{$f['name']}')\n                            @if(\$sortDirection === 'asc') &uarr; @else &darr; @endif\n                        @endif\n                    </th>")->implode("\n                    ");

        $rowContent = $displayFields->map(fn($f) => "<td class=\"px-6 py-4 whitespace-nowrap\">\n                            @if(\${$modelVar}->{$f['name']})\n                                {{ \${$modelVar}->{$f['name']} }}\n                            @else\n                                <span class=\"text-gray-400\">-</span>\n                            @endif\n                        </td>")->implode("\n                        ");

        $colspan = $displayFields->count() + 1;

        $stub = $this->getStub('livewire-index-view');
        $stub = str_replace('{{ title }}', Str::plural($modelBase), $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.create') }}", $stub);
        $stub = str_replace('{{ headers }}', $headers, $stub);
        $stub = str_replace('{{ rowContent }}', $rowContent, $stub);
        $stub = str_replace('{{ colspan }}', $colspan, $stub);
        $stub = str_replace('{{ models }}', $models, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    protected function generateCreateView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath)
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/create.blade.php");

        $fields = $this->fieldParser->getFields();
        $formFields = collect($fields)
            ->reject(fn($f) => $f['name'] === 'id')
            ->map(fn($f) => $this->generateFormField($f, $modelVar))
            ->implode("\n            ");

        $stub = $this->getStub('livewire-create-view');
        $stub = str_replace('{{ title }}', 'Create ' . $modelBase, $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.index') }}", $stub);
        $stub = str_replace('{{ formFields }}', $formFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    protected function generateEditView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath)
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/edit.blade.php");

        $fields = $this->fieldParser->getFields();
        $formFields = collect($fields)
            ->reject(fn($f) => $f['name'] === 'id')
            ->map(fn($f) => $this->generateFormField($f, $modelVar))
            ->implode("\n            ");

        $stub = $this->getStub('livewire-edit-view');
        $stub = str_replace('{{ title }}', 'Edit ' . $modelBase, $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.index') }}", $stub);
        $stub = str_replace('{{ formFields }}', $formFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    protected function generateShowView($model, $modelBase, $modelVar, $models, $kebabModels, $viewPath)
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/show.blade.php");

        $fields = $this->fieldParser->getFields();
        $showFields = collect($fields)
            ->reject(fn($f) => $f['name'] === 'id')
            ->map(fn($f) => $this->generateShowField($f, $modelVar))
            ->implode("\n            ");

        $stub = $this->getStub('livewire-show-view');
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.index') }}", $stub);
        $stub = str_replace('{{ editRoute }}', "{{ route('{$kebabModels}.edit', \${$modelVar}) }}", $stub);
        $stub = str_replace('{{ showFields }}', $showFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    protected function generateFormField($field, $modelVar)
    {
        $label = Str::title(str_replace('_', ' ', $field['name']));
        $name = $field['name'];

        if (in_array($field['type'], ['text'])) {
            return <<<BLADE
<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{$label}</label>
                <textarea
                    wire:model="{$name}"
                    rows="4"
                    class="w-full border rounded px-3 py-2 @error('{$name}') border-red-500 @enderror"
                ></textarea>
                @error('{$name}')
                    <span class="text-red-500 text-sm">{{ \$message }}</span>
                @enderror
            </div>
BLADE;
        }

        if ($field['type'] === 'boolean') {
            return <<<BLADE
<div>
                <label class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        wire:model="{$name}"
                        class="rounded border-gray-300"
                    />
                    <span class="text-sm font-medium text-gray-700">{$label}</span>
                </label>
                @error('{$name}')
                    <span class="text-red-500 text-sm">{{ \$message }}</span>
                @enderror
            </div>
BLADE;
        }

        if (in_array($field['type'], ['date', 'datetime'])) {
            $inputType = $field['type'] === 'datetime' ? 'datetime-local' : 'date';
            return <<<BLADE
<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{$label}</label>
                <input
                    type="{$inputType}"
                    wire:model="{$name}"
                    class="w-full border rounded px-3 py-2 @error('{$name}') border-red-500 @enderror"
                />
                @error('{$name}')
                    <span class="text-red-500 text-sm">{{ \$message }}</span>
                @enderror
            </div>
BLADE;
        }

        if (in_array($field['type'], ['integer', 'bigint', 'float', 'decimal'])) {
            return <<<BLADE
<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{$label}</label>
                <input
                    type="number"
                    wire:model="{$name}"
                    class="w-full border rounded px-3 py-2 @error('{$name}') border-red-500 @enderror"
                />
                @error('{$name}')
                    <span class="text-red-500 text-sm">{{ \$message }}</span>
                @enderror
            </div>
BLADE;
        }

        return <<<BLADE
<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{$label}</label>
                <input
                    type="text"
                    wire:model="{$name}"
                    class="w-full border rounded px-3 py-2 @error('{$name}') border-red-500 @enderror"
                />
                @error('{$name}')
                    <span class="text-red-500 text-sm">{{ \$message }}</span>
                @enderror
            </div>
BLADE;
    }

    protected function generateShowField($field, $modelVar)
    {
        $label = Str::title(str_replace('_', ' ', $field['name']));
        $name = $field['name'];

        return <<<BLADE
<div class="mb-4">
                <dt class="text-sm font-medium text-gray-500">{$label}</dt>
                <dd class="mt-1 text-sm text-gray-900">@if(\${$modelVar}->{$name}) {{ \${$modelVar}->{$name} }} @else <span class="text-gray-400">-</span> @endif</dd>
            </div>
BLADE;
    }

    public function types(): array
    {
        return ['livewire-view'];
    }
}
