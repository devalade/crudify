<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class VoltLivewireGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $modelBase = class_basename($model);
        $modelVar = $this->camelCase($modelBase);
        $models = $this->pluralize($modelVar);
        $kebabModels = $this->kebabCase($models);
        $pluralBase = Str::plural($modelBase);

        $paths = [];
        $paths = array_merge($paths, $this->generateIndex($modelBase, $modelVar, $models, $kebabModels, $pluralBase));
        $paths = array_merge($paths, $this->generateCreate($modelBase, $modelVar, $models, $kebabModels, $pluralBase));
        $paths = array_merge($paths, $this->generateEdit($modelBase, $modelVar, $models, $kebabModels, $pluralBase));
        $paths = array_merge($paths, $this->generateShow($modelBase, $modelVar, $models, $kebabModels, $pluralBase));

        return $paths;
    }

    /** @return array<string> */
    protected function generateIndex(string $modelBase, string $modelVar, string $models, string $kebabModels, string $pluralBase): array
    {
        $path = base_path("resources/views/pages/{$kebabModels}/index.blade.php");
        $fields = $this->fieldParser->getFields();

        $searchables = collect($fields)->filter(fn ($f) => in_array($f['type'], ['string', 'text', 'email']))->take(3);
        $searchConditions = $searchables->map(fn ($f) => "\$q->orWhere('{$f['name']}', 'like', '%' . \$this->search . '%');")->implode("\n                    ");

        $displayFields = collect($fields)->reject(fn ($f) => $f['name'] === 'id' || in_array($f['type'], ['image', 'file']))->take(5);
        $headers = $displayFields->map(fn ($f) => "<th wire:click=\"sortBy('{$f['name']})\">".Str::title(str_replace('_', ' ', $f['name']))." @if(\$sortField === '{$f['name']}) @if(\$sortDirection === 'asc') &#9650; @else &#9660; @endif @endif</th>")->implode("\n                ");
        $rowContent = $displayFields->map(fn ($f) => "<td>{{ \${$modelVar}->{$f['name']} }}</td>")->implode("\n                ");
        $colspan = $displayFields->count() + 2;
        $with = $this->getWithClause();

        $stub = $this->getStub('volt-index');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ models }}', $models, $stub);
        $stub = str_replace('{{ title }}', $pluralBase, $stub);
        $stub = str_replace('{{ searchConditions }}', $searchConditions ?: '// Add search', $stub);
        $stub = str_replace('{{ with }}', $with, $stub);
        $stub = str_replace('{{ headers }}', $headers, $stub);
        $stub = str_replace('{{ rowContent }}', $rowContent, $stub);
        $stub = str_replace('{{ colspan }}', (string) $colspan, $stub);
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels.'/create', $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateCreate(string $modelBase, string $modelVar, string $models, string $kebabModels, string $pluralBase): array
    {
        $path = base_path("resources/views/pages/{$kebabModels}/create.blade.php");
        $fields = $this->fieldParser->getFields();

        $belongsToProps = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => "#[Validate('required|integer')]\n    public int \${$r['name']}_id = 0;")
            ->implode("\n    ");
        $belongsToOpts = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => 'public $'.Str::camel($r['model']).'Options = [];')
            ->implode("\n    ");

        $properties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) {
                $validate = $this->getValidationAttribute($f['type'], $f['nullable'] ?? false, false);

                return $validate."\n    public {$this->getPropertyType($f['type'])} \${$f['name']} = '';";
            })
            ->implode("\n    ");

        $allProperties = trim(implode("\n    ", array_filter([$belongsToProps, $belongsToOpts, $properties])));

        $mountBody = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => '$this->'.Str::camel($r['model']).'Options = \\App\\Models\\'.$r['model'].'::limit(100)->get();')
            ->implode("\n    ");

        $formFields = $this->generateFormFields($fields);

        $stub = $this->getStub('volt-create');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', $pluralBase, $stub);
        $stub = str_replace('{{ properties }}', $allProperties, $stub);
        $stub = str_replace('{{ mountBody }}', $mountBody, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels, $stub);
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);
        $stub = str_replace('{{ formFields }}', $formFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateEdit(string $modelBase, string $modelVar, string $models, string $kebabModels, string $pluralBase): array
    {
        $path = base_path("resources/views/pages/{$kebabModels}/edit.blade.php");
        $fields = $this->fieldParser->getFields();

        $belongsToProps = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => "#[Validate('required|integer')]\n    public int \${$r['name']}_id = 0;")
            ->implode("\n    ");
        $belongsToOpts = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => 'public $'.Str::camel($r['model']).'Options = [];')
            ->implode("\n    ");

        $properties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) {
                $validate = $this->getValidationAttribute($f['type'], $f['nullable'] ?? true, true);

                return $validate."\n    public {$this->getPropertyType($f['type'])} \${$f['name']};";
            })
            ->implode("\n    ");

        $allProperties = trim(implode("\n    ", array_filter([$belongsToProps, $belongsToOpts, $properties])));

        $fillProperties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(fn ($f) => '$this->'.$f['name'].' = $'.$modelVar.'->'.$f['name'].';')
            ->implode("\n    ");

        $formFields = $this->generateFormFields($fields);

        $stub = $this->getStub('volt-edit');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', $pluralBase, $stub);
        $stub = str_replace('{{ properties }}', $allProperties, $stub);
        $stub = str_replace('{{ fillProperties }}', $fillProperties, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels, $stub);
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);
        $stub = str_replace('{{ formFields }}', $formFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateShow(string $modelBase, string $modelVar, string $models, string $kebabModels, string $pluralBase): array
    {
        $path = base_path("resources/views/pages/{$kebabModels}/show.blade.php");
        $fields = $this->fieldParser->getFields();
        $displayFields = collect($fields)->reject(fn ($f) => $f['name'] === 'id');

        $details = $displayFields->map(fn ($f) => '<tr><td>'.Str::title(str_replace('_', ' ', $f['name'])).'</td><td>{{ $'.$modelVar.'->'.$f['name'].' }}</td></tr>')->implode("\n                ");

        $stub = $this->getStub('volt-show');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ details }}', $details, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateRoutes(string $modelBase, string $pluralBase, string $kebabModels): array
    {
        $routeCode = "
// Volt SFC Routes: {$modelBase}
Route::livewire('/{$kebabModels}', 'pages::{$kebabModels}.index')->name('{$kebabModels}.index');
Route::livewire('/{$kebabModels}/create', 'pages::{$kebabModels}.create')->name('{$kebabModels}.create');
Route::livewire('/{$kebabModels}/{{ $kebabModels }}', 'pages::{$kebabModels}.show')->name('{$kebabModels}.show');
Route::livewire('/{$kebabModels}/{{ $kebabModels }}/edit', 'pages::{$kebabModels}.edit')->name('{$kebabModels}.edit');";

        $path = base_path('routes/web.php');
        $existingContent = file_exists($path) ? (string) file_get_contents($path) : '';

        if (! str_contains($existingContent, "pages::{$kebabModels}.")) {
            file_put_contents($path, $routeCode."\n", FILE_APPEND);
        }

        return ['routes/web.php'];
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['volt-livewire'];
    }

    protected function getValidationAttribute(string $type, bool $nullable, bool $isUpdate): string
    {
        $rules = [];
        $rules[] = $isUpdate ? ($nullable ? 'nullable' : 'sometimes') : ($nullable ? 'nullable' : 'required');
        if ($type === 'email') {
            $rules[] = 'email';
        }
        if (in_array($type, ['integer', 'bigint'])) {
            $rules[] = 'integer';
        }
        if ($type === 'boolean') {
            $rules[] = 'boolean';
        }
        if (in_array($type, ['date', 'datetime'])) {
            $rules[] = 'date';
        }

        return "#[Validate('".implode('|', $rules)."')]";
    }

    protected function getPropertyType(string $type): string
    {
        return match ($type) {
            'boolean' => 'bool',
            'integer', 'bigint' => 'int',
            default => 'string',
        };
    }

    /** @param  array<array<string, mixed>> $fields */
    protected function generateFormFields(array $fields): string
    {
        return collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) {
                $label = Str::title(str_replace('_', ' ', $f['name']));
                $input = match ($f['type']) {
                    'boolean' => '<input type="checkbox" wire:model="'.$f['name'].'" />',
                    'text' => '<textarea wire:model="'.$f['name'].'" rows="4"></textarea>',
                    default => '<input type="text" wire:model="'.$f['name'].'" />',
                };

                return "<label>\n                    {$label}\n                    {$input}\n                    <small class=\"text-red-500\">@error('{$f['name']}') {{ \$message }} @enderror</small>\n                </label>";
            })
            ->implode("\n\n");
    }
}
