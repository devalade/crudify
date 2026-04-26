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
        $searchProperties = $searchables->map(fn ($f) => "public string \${$f['name']} = '';")->implode("\n    ");
        $searchConditions = $searchables->map(fn ($f) => "\$q->orWhere('{$f['name']}', 'like', '%' . \$this->search . '%');")->implode("\n                    ");

        $displayFields = collect($fields)->reject(fn ($f) => $f['name'] === 'id' || in_array($f['type'], ['image', 'file']))->take(5);
        $headers = $displayFields->map(fn ($f) => "<th wire:click=\"sortBy('{$f['name']})\">".Str::title(str_replace('_', ' ', $f['name']))." @if(\$sortField === '{$f['name']}) @if(\$sortDirection === 'asc') &#9650; @else &#9660; @endif @endif</th>")->implode("\n                ");
        $rowContent = $displayFields->map(fn ($f) => "<td>{{ \${$modelVar}->{$f['name']} }}</td>")->implode("\n                ");
        $colspan = $displayFields->count() + 2;
        $with = $this->getWithClause();

        $stub = $this->getStub('volt-index');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ models }}', $models, $stub);
        $stub = str_replace('{{ title }}', $pluralBase, $stub);
        $stub = str_replace('{{ searchables }}', $searchProperties, $stub);
        $stub = str_replace('{{ searchConditions }}', $searchConditions ?: '// Add search', $stub);
        $stub = str_replace('{{ with }}', $with ?: '', $stub);
        $stub = str_replace('{{ headers }}', $headers, $stub);
        $stub = str_replace('{{ rowContent }}', $rowContent, $stub);
        $stub = str_replace('{{ colspan }}', (string) $colspan, $stub);
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels.'/create', $stub);
        $stub = str_replace('{{ viewPath }}', $kebabModels, $stub);
        $stub = str_replace('{{ titleSingular }}', $modelBase, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateCreate(string $modelBase, string $modelVar, string $models, string $kebabModels, string $pluralBase): array
    {
        $path = base_path("resources/views/pages/{$kebabModels}/create.blade.php");
        $fields = $this->fieldParser->getFields();
        $fileFields = $this->fieldParser->getFileFields();
        $hasFiles = ! empty($fileFields);

        $belongsToProps = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => "#[Validate('required|integer')]\n    public int \${$r['name']}_id = 0;")
            ->implode("\n    ");
        $belongsToOpts = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => 'public $'.Str::camel($r['model']).'Options = [];')
            ->implode("\n    ");

        $belongsToManyProps = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => "#[Validate('nullable|array')]\n    public array \$selected".Str::studly(Str::plural($r['name'])).'Ids = [];')
            ->implode("\n    ");
        $belongsToManyOpts = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => 'public $'.Str::camel(Str::plural($r['name'])).'Options = [];')
            ->implode("\n    ");

        $properties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) {
                $validate = $this->getValidationAttribute($f['type'], $f['nullable'] ?? false, false);
                if (in_array($f['type'], ['image', 'file'])) {
                    if ($f['multiple'] ?? false) {
                        return $validate."\n    public $".$f['name'].' = [];';
                    }

                    return $validate."\n    public $".$f['name'].';';
                }

                return $validate."\n    public {$this->getPropertyType($f['type'])} \${$f['name']} = '';";
            })
            ->implode("\n    ");

        $allProperties = trim(implode("\n    ", array_filter([$belongsToProps, $belongsToOpts, $belongsToManyProps, $belongsToManyOpts, $properties])));

        $mountBody = collect($this->getRelationships())
            ->filter(fn ($r) => in_array($r['type'], ['belongsTo', 'belongsToMany']))
            ->map(function ($r) {
                if ($r['type'] === 'belongsTo') {
                    return '$this->'.Str::camel($r['model']).'Options = \App\Models\\'.$r['model'].'::limit(100)->get();';
                }

                return '$this->'.Str::camel(Str::plural($r['name'])).'Options = \App\Models\\'.$r['model'].'::limit(100)->get();';
            })
            ->implode("\n    ");

        $formFields = $this->generateFormFields($fields);
        $fileStorage = $this->generateFileStorage($fields, $modelBase, $modelVar);
        $syncRelationships = $this->generateSyncRelationships($modelVar, false);

        $uses = [];
        $traits = [];
        if ($hasFiles) {
            $uses[] = 'use Livewire\WithFileUploads;';
            $traits[] = 'use WithFileUploads;';
        }
        $usesStr = implode("\n", $uses);
        $traitsStr = implode("\n    ", $traits);

        $stub = $this->getStub('volt-create');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ titleSingular }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', $pluralBase, $stub);
        $stub = str_replace('{{ properties }}', $allProperties, $stub);
        $stub = str_replace('{{ mountBody }}', $mountBody, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels, $stub);
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);
        $stub = str_replace('{{ formFields }}', $formFields, $stub);
        $stub = str_replace('{{ viewPath }}', $kebabModels, $stub);
        $stub = str_replace('{{ uses }}', $usesStr, $stub);
        $stub = str_replace('{{ traits }}', $traitsStr, $stub);
        $stub = str_replace('{{ fileStorage }}', $fileStorage, $stub);
        $stub = str_replace('{{ syncRelationships }}', $syncRelationships, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateEdit(string $modelBase, string $modelVar, string $models, string $kebabModels, string $pluralBase): array
    {
        $path = base_path("resources/views/pages/{$kebabModels}/edit.blade.php");
        $fields = $this->fieldParser->getFields();
        $fileFields = $this->fieldParser->getFileFields();
        $hasFiles = ! empty($fileFields);

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
                if (in_array($f['type'], ['image', 'file'])) {
                    if ($f['multiple'] ?? false) {
                        return $validate."\n    public $".$f['name'].' = [];'."\n    public array $".$f['name'].'ToRemove = [];';
                    }

                    return $validate."\n    public $".$f['name'].';';
                }

                return $validate."\n    public {$this->getPropertyType($f['type'])} \${$f['name']};";
            })
            ->implode("\n    ");

        $allProperties = trim(implode("\n    ", array_filter([$belongsToProps, $belongsToOpts, $properties])));

        $fillProperties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) use ($modelVar) {
                if (in_array($f['type'], ['image', 'file'])) {
                    return '// File fields are not pre-filled for security';
                }

                return '$this->'.$f['name'].' = $'.$modelVar.'->'.$f['name'].';';
            })
            ->implode("\n    ");

        $formFields = $this->generateFormFields($fields);
        $fileStorage = $this->generateFileStorage($fields, $modelBase, $modelVar, true);
        $syncRelationships = $this->generateSyncRelationships($modelVar, true);
        $extraMethods = $this->generateFileRemovalMethods($fields);

        $uses = [];
        $traits = [];
        if ($hasFiles) {
            $uses[] = 'use Livewire\WithFileUploads;';
            $traits[] = 'use WithFileUploads;';
            if ($this->fieldParser->getMultipleFileFields()) {
                $uses[] = 'use Illuminate\Support\Facades\Storage;';
            }
        }
        $usesStr = implode("\n", $uses);
        $traitsStr = implode("\n    ", $traits);

        $stub = $this->getStub('volt-edit');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', $pluralBase, $stub);
        $stub = str_replace('{{ properties }}', $allProperties, $stub);
        $stub = str_replace('{{ fillProperties }}', $fillProperties, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels, $stub);
        $stub = str_replace('{{ formFields }}', $formFields, $stub);
        $stub = str_replace('{{ uses }}', $usesStr, $stub);
        $stub = str_replace('{{ traits }}', $traitsStr, $stub);
        $stub = str_replace('{{ fileStorage }}', $fileStorage, $stub);
        $stub = str_replace('{{ syncRelationships }}', $syncRelationships, $stub);
        $stub = str_replace('{{ extraMethods }}', $extraMethods, $stub);

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
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', $pluralBase, $stub);
        $stub = str_replace('{{ showFields }}', $details, $stub);
        $stub = str_replace('{{ editRoute }}', "route('{$kebabModels}.edit', \${$modelVar})", $stub);
        $stub = str_replace('{{ titleSingular }}', $modelBase, $stub);
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

                if (in_array($f['type'], ['image', 'file'])) {
                    $multiple = $f['multiple'] ?? false;
                    $accept = $f['type'] === 'image' ? 'accept="image/*"' : '';
                    $multipleAttr = $multiple ? ' multiple' : '';

                    return "<label>\n                    {$label}\n                    <input type=\"file\" wire:model=\"{$f['name']}\"{$multipleAttr} {$accept} />\n                    <small class=\"text-red-500\">@error('{$f['name']}') {{ \$message }} @enderror</small>\n                </label>";
                }

                $input = match ($f['type']) {
                    'boolean' => '<input type="checkbox" wire:model="'.$f['name'].'" />',
                    'text' => '<textarea wire:model="'.$f['name'].'" rows="4"></textarea>',
                    default => '<input type="text" wire:model="'.$f['name'].'" />',
                };

                return "<label>\n                    {$label}\n                    {$input}\n                    <small class=\"text-red-500\">@error('{$f['name']}') {{ \$message }} @enderror</small>\n                </label>";
            })
            ->implode("\n\n");
    }

    protected function generateSyncRelationships(string $modelVar, bool $isEdit): string
    {
        $belongsToMany = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany');

        if ($belongsToMany->isEmpty()) {
            return '';
        }

        $lines = [];
        $modelRef = $isEdit ? "\$this->{$modelVar}" : "\${$modelVar}";

        foreach ($belongsToMany as $rel) {
            $name = Str::plural($rel['name']);
            $propertyName = 'selected'.Str::studly($name).'Ids';
            $lines[] = "{$modelRef}->{$name}()->sync(\$this->{$propertyName});";
        }

        return implode("\n        ", $lines);
    }

    /**
     * @param  array<array<string, mixed>>  $fields
     */
    protected function generateFileRemovalMethods(array $fields): string
    {
        $multipleFileFields = array_filter($fields, fn ($f) => in_array($f['type'], ['image', 'file']) && ($f['multiple'] ?? false));

        if (empty($multipleFileFields)) {
            return '';
        }

        $methods = [];
        foreach ($multipleFileFields as $field) {
            $name = $field['name'];
            $methodName = 'remove'.Str::studly($name).'File';
            $methods[] = <<<PHP
    public function {$methodName}(string \$path): void
    {
        \$this->{$name}ToRemove[] = \$path;
    }
PHP;
        }

        return "\n".implode("\n\n", $methods);
    }

    /**
     * @param  array<array<string, mixed>>  $fields
     */
    protected function generateFileStorage(array $fields, string $modelBase, string $modelVar, bool $isEdit = false): string
    {
        $fileFields = array_filter($fields, fn ($f) => in_array($f['type'], ['image', 'file']));

        if (empty($fileFields)) {
            return '';
        }

        $lines = [];
        $table = Str::plural(Str::snake($modelBase));

        foreach ($fileFields as $field) {
            $name = $field['name'];
            $isMultiple = $field['multiple'] ?? false;

            if ($isMultiple) {
                if ($isEdit) {
                    $lines[] = "\$existing{$name} = is_array(\$this->{$modelVar}->{$name}) ? \$this->{$modelVar}->{$name} : json_decode(\$this->{$modelVar}->{$name}, true) ?? [];";
                    $lines[] = "\$existing{$name} = array_diff(\$existing{$name}, \$this->{$name}ToRemove);";
                    $lines[] = "foreach (\$this->{$name}ToRemove as \$path) {";
                    $lines[] = "    Storage::disk('public')->delete(\$path);";
                    $lines[] = '}';
                    $lines[] = "if (!empty(\$this->{$name})) {";
                    $lines[] = "    foreach (\$this->{$name} as \$file) {";
                    $lines[] = "        \$existing{$name}[] = \$file->store('{$table}', 'public');";
                    $lines[] = '    }';
                    $lines[] = '}';
                    $lines[] = "\$validated['{$name}'] = \$existing{$name};";
                } else {
                    $lines[] = "if (!empty(\$this->{$name})) {";
                    $lines[] = '    $paths = [];';
                    $lines[] = "    foreach (\$this->{$name} as \$file) {";
                    $lines[] = "        \$paths[] = \$file->store('{$table}', 'public');";
                    $lines[] = '    }';
                    $lines[] = "    \$validated['{$name}'] = \$paths;";
                    $lines[] = '}';
                }
            } else {
                if ($isEdit) {
                    $lines[] = "if (\$this->{$name}) {";
                    $lines[] = "    if (\$this->{$modelVar}->{$name}) {";
                    $lines[] = "        Storage::disk('public')->delete(\$this->{$modelVar}->{$name});";
                    $lines[] = '    }';
                    $lines[] = "    \$validated['{$name}'] = \$this->{$name}->store('{$table}', 'public');";
                    $lines[] = '}';
                } else {
                    $lines[] = "if (\$this->{$name}) {";
                    $lines[] = "    \$validated['{$name}'] = \$this->{$name}->store('{$table}', 'public');";
                    $lines[] = '}';
                }
            }
        }

        return implode("\n        ", $lines);
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
        if ($type === 'image') {
            $rules[] = 'image';
            $rules[] = 'mimes:jpeg,png,jpg,gif,webp,svg,avif';
            $rules[] = 'max:2048';
        }
        if ($type === 'file') {
            $rules[] = 'file';
            $rules[] = 'mimes:pdf,doc,docx,txt,zip,xls,xlsx,csv,ppt,pptx';
            $rules[] = 'max:2048';
        }

        return "#[Validate('".implode('|', $rules)."')]";
    }
}
