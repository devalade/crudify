<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class LivewireComponentGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $modelBase = class_basename($model);
        $modelVar = $this->camelCase($modelBase);
        $models = $this->pluralize($modelVar);
        $kebabModels = $this->kebabCase($models);
        $viewPath = str_replace('/', '.', $kebabModels);
        $pluralBase = Str::plural($modelBase);

        $paths = [];
        $paths = array_merge($paths, $this->generateIndex($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));
        $paths = array_merge($paths, $this->generateCreate($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));
        $paths = array_merge($paths, $this->generateEdit($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));
        $paths = array_merge($paths, $this->generateShow($modelBase, $modelVar, $models, $kebabModels, $viewPath, $pluralBase));

        return $paths;
    }

    /** @return array<string> */
    protected function generateIndex(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Index';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Index.php");

        $fields = $this->fieldParser->getFields();
        $searchables = collect($fields)->filter(fn ($f) => in_array($f['type'], ['string', 'text', 'email']))->take(3);
        $sortableFields = collect(['id'])
            ->merge(
                collect($fields)
                    ->reject(fn ($f) => $f['name'] === 'id' || in_array($f['type'], ['image', 'file']))
                    ->take(5)
                    ->pluck('name')
            )
            ->unique()
            ->values()
            ->map(fn ($field) => "'{$field}'")
            ->implode(', ');

        $searchablesProps = $searchables->map(fn ($f) => "public string \${$f['name']} = '';")->implode("\n    ");

        $searchConditions = $searchables->map(fn ($f) => "\$q->orWhere('{$f['name']}', 'like', '%' . \$this->search . '%');")->implode("\n                    ");

        $with = $this->getWithClause();

        $stub = $this->getStub('livewire-index');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ title }}', $pluralBase, $stub);
        $stub = str_replace('{{ searchables }}', $searchablesProps ? "\n    {$searchablesProps}\n" : '', $stub);
        $stub = str_replace('{{ sortableFields }}', "[{$sortableFields}]", $stub);
        $stub = str_replace('{{ searchConditions }}', $searchConditions ?: '// Add search conditions', $stub);
        $stub = str_replace('{{ with }}', $with ?: '', $stub);
        $stub = str_replace('{{ models }}', $models, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateCreate(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Create';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Create.php");

        $fields = $this->fieldParser->getFields();
        $fileFields = $this->fieldParser->getFileFields();
        $hasFiles = ! empty($fileFields);

        $belongsToProperties = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => "#[Validate('required|integer')]\n    public int \$".Str::snake($r['name']).'_id;')
            ->implode("\n    ");

        $belongsToOptions = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => 'public $'.Str::camel($r['model']).'Options = [];')
            ->implode("\n    ");

        $belongsToManyProperties = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => "#[Validate('nullable|array')]\n    public array \$selected".Str::studly(Str::plural($r['name'])).'Ids = [];')
            ->implode("\n    ");

        $belongsToManyOptions = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => 'public $'.Str::camel(Str::plural($r['name'])).'Options = [];')
            ->implode("\n    ");

        $properties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) {
                $validate = $this->getValidationAttribute($f, false);
                if (in_array($f['type'], ['image', 'file'])) {
                    if ($f['multiple'] ?? false) {
                        return $validate."\n    public $".$f['name'].' = [];';
                    }

                    return $validate."\n    public $".$f['name'].';';
                }

                return $validate."\n    public {$this->getPropertyType($f)} \${$f['name']};";
            })
            ->implode("\n    ");

        $mountBody = collect($this->getRelationships())
            ->filter(fn ($r) => in_array($r['type'], ['belongsTo', 'belongsToMany']))
            ->map(function ($r) {
                if ($r['type'] === 'belongsTo') {
                    return '$this->'.Str::camel($r['model']).'Options = \\App\\Models\\'.$r['model'].'::limit(100)->get();';
                }

                return '$this->'.Str::camel(Str::plural($r['name'])).'Options = \\App\\Models\\'.$r['model'].'::limit(100)->get();';
            })
            ->implode("\n        ");

        $allProperties = $properties;
        if ($belongsToProperties) {
            $allProperties = $belongsToProperties."\n    ".$allProperties;
        }
        if ($belongsToManyProperties) {
            $allProperties = $belongsToManyProperties."\n    ".$allProperties;
        }
        if ($belongsToOptions) {
            $allProperties = $belongsToOptions."\n    ".$allProperties;
        }
        if ($belongsToManyOptions) {
            $allProperties = $belongsToManyOptions."\n    ".$allProperties;
        }

        $fileStorage = $this->generateFileStorage($fields, $modelBase, $modelVar);
        $syncRelationships = $this->generateSyncRelationships($modelVar, false);

        $stub = $this->getStub('livewire-create');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', 'Create '.$modelBase, $stub);
        $stub = str_replace('{{ properties }}', $allProperties, $stub);
        $stub = str_replace('{{ route }}', $kebabModels, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);
        $stub = str_replace('{{ mountBody }}', $mountBody ?: '// Initialize form', $stub);
        $stub = str_replace('{{ fileStorage }}', $fileStorage, $stub);
        $stub = str_replace('{{ syncRelationships }}', $syncRelationships, $stub);
        $stub = str_replace('{{ uses }}', $hasFiles ? "\nuse Livewire\\WithFileUploads;" : '', $stub);
        $stub = str_replace('{{ traits }}', $hasFiles ? "\n    use WithFileUploads;" : '', $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateEdit(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Edit';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Edit.php");

        $fields = $this->fieldParser->getFields();
        $fileFields = $this->fieldParser->getFileFields();
        $hasFiles = ! empty($fileFields);

        $belongsToProperties = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => "#[Validate('sometimes|integer')]\n    public int \$".Str::snake($r['name']).'_id;')
            ->implode("\n    ");

        $belongsToOptions = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => 'public $'.Str::camel($r['model']).'Options = [];')
            ->implode("\n    ");

        $belongsToManyProperties = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => "#[Validate('nullable|array')]\n    public array \$selected".Str::studly(Str::plural($r['name'])).'Ids = [];')
            ->implode("\n    ");

        $belongsToManyOptions = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => 'public $'.Str::camel(Str::plural($r['name'])).'Options = [];')
            ->implode("\n    ");

        $properties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) {
                $validate = $this->getValidationAttribute($f, true);
                if (in_array($f['type'], ['image', 'file'])) {
                    if ($f['multiple'] ?? false) {
                        return $validate."\n    public $".$f['name'].' = [];'."\n    public array $".$f['name'].'ToRemove = [];';
                    }

                    return $validate."\n    public $".$f['name'].';';
                }

                return $validate."\n    public {$this->getPropertyType($f)} \${$f['name']};";
            })
            ->implode("\n    ");

        $allProperties = $properties;
        if ($belongsToProperties) {
            $allProperties = $belongsToProperties."\n    ".$allProperties;
        }
        if ($belongsToManyProperties) {
            $allProperties = $belongsToManyProperties."\n    ".$allProperties;
        }
        if ($belongsToOptions) {
            $allProperties = $belongsToOptions."\n    ".$allProperties;
        }
        if ($belongsToManyOptions) {
            $allProperties = $belongsToManyOptions."\n    ".$allProperties;
        }

        $fillProperties = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => '$this->'.Str::snake($r['name'])."_id = \${$modelVar}->".Str::snake($r['name']).'_id;')
            ->implode("\n        ");

        $belongsToManyFill = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => '$this->selected'.Str::studly(Str::plural($r['name']))."Ids = \${$modelVar}->".Str::plural($r['name'])."->pluck('id')->toArray();")
            ->implode("\n        ");

        $fieldFillProperties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) use ($modelVar) {
                if (in_array($f['type'], ['image', 'file'])) {
                    return '// File fields are not pre-filled for security';
                }

                return "\$this->{$f['name']} = \${$modelVar}->{$f['name']};";
            })
            ->implode("\n        ");

        $fillParts = array_filter([$fillProperties, $belongsToManyFill, $fieldFillProperties]);
        $fillProperties = implode("\n        ", $fillParts);

        $mountBody = collect($this->getRelationships())
            ->filter(fn ($r) => in_array($r['type'], ['belongsTo', 'belongsToMany']))
            ->map(function ($r) {
                if ($r['type'] === 'belongsTo') {
                    return '$this->'.Str::camel($r['model']).'Options = \\App\\Models\\'.$r['model'].'::limit(100)->get();';
                }

                return '$this->'.Str::camel(Str::plural($r['name'])).'Options = \\App\\Models\\'.$r['model'].'::limit(100)->get();';
            })
            ->implode("\n        ");

        if ($mountBody) {
            $fillProperties = $mountBody."\n        ".$fillProperties;
        }

        $fileStorage = $this->generateFileStorage($fields, $modelBase, $modelVar, true);
        $syncRelationships = $this->generateSyncRelationships($modelVar, true);
        $extraMethods = $this->generateFileRemovalMethods($fields);

        $uses = [];
        if ($hasFiles) {
            $uses[] = 'use Livewire\\WithFileUploads;';
            if ($this->fieldParser->getMultipleFileFields()) {
                $uses[] = 'use Illuminate\\Support\\Facades\\Storage;';
            }
        }
        $usesStr = implode("\n", $uses);

        $stub = $this->getStub('livewire-edit');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', 'Edit '.$modelBase, $stub);
        $stub = str_replace('{{ properties }}', $allProperties, $stub);
        $stub = str_replace('{{ fillProperties }}', $fillProperties, $stub);
        $stub = str_replace('{{ route }}', $kebabModels, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);
        $stub = str_replace('{{ fileStorage }}', $fileStorage, $stub);
        $stub = str_replace('{{ syncRelationships }}', $syncRelationships, $stub);
        $stub = str_replace('{{ extraMethods }}', $extraMethods, $stub);
        $stub = str_replace('{{ uses }}', $usesStr ? "\n".$usesStr : '', $stub);
        $stub = str_replace('{{ traits }}', $hasFiles ? "\n    use WithFileUploads;" : '', $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateShow(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath, string $pluralBase): array
    {
        $namespace = 'App\\Livewire\\Pages\\'.$pluralBase;
        $class = 'Show';
        $path = base_path("app/Livewire/Pages/{$pluralBase}/Show.php");

        $stub = $this->getStub('livewire-show');
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\\Models', $stub);
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ route }}', $kebabModels, $stub);
        $stub = str_replace('{{ viewPath }}', $viewPath, $stub);

        $this->createFile($path, $stub);

        return [$path];
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
     * @param  array<int, array<string, mixed>>  $fields
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
     * @param  array<int, array<string, mixed>>  $fields
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

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getValidationAttribute(array $field, bool $isUpdate): string
    {
        $rules = [];

        if ($isUpdate) {
            $rules[] = $field['nullable'] ? 'nullable' : 'sometimes';
        } else {
            $rules[] = $field['nullable'] ? 'nullable' : 'required';
        }

        if ($field['type'] === 'email') {
            $rules[] = 'email';
        }

        if ($field['type'] === 'integer' || $field['type'] === 'bigint') {
            $rules[] = 'integer';
        }

        if ($field['type'] === 'boolean') {
            $rules[] = 'boolean';
        }

        if ($field['type'] === 'date' || $field['type'] === 'datetime') {
            $rules[] = 'date';
        }

        if ($field['type'] === 'image') {
            if ($field['multiple'] ?? false) {
                $rules[] = 'array';
            } else {
                $rules[] = 'image';
                $rules[] = 'mimes:jpeg,png,jpg,gif,webp,svg,avif';
                $rules[] = 'max:2048';
            }
        }

        if ($field['type'] === 'file') {
            if ($field['multiple'] ?? false) {
                $rules[] = 'array';
            } else {
                $rules[] = 'file';
                $rules[] = 'mimes:pdf,doc,docx,txt,zip,xls,xlsx,csv,ppt,pptx';
                $rules[] = 'max:2048';
            }
        }

        if ($field['type'] === 'text') {
            $rules[] = 'string';
        }

        $ruleString = implode('|', $rules);

        return "#[Validate('{$ruleString}')]";
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getPropertyType(array $field): string
    {
        return match ($field['type']) {
            'integer', 'bigint', 'float', 'double', 'decimal' => 'int|float',
            'boolean' => 'bool',
            default => 'string',
        };
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['livewire'];
    }
}
