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
        $headers = $displayFields->map(fn ($f) => "<th wire:click=\"sortBy('{$f['name']}')\">".Str::title(str_replace('_', ' ', $f['name']))." @if(\$sortField === '{$f['name']}') @if(\$sortDirection === 'asc') &#9650; @else &#9660; @endif @endif</th>")->implode("\n                ");
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
                $validate = $this->getValidationAttribute($f, false);
                if (in_array($f['type'], ['image', 'file'])) {
                    if ($f['multiple'] ?? false) {
                        return $validate."\n    public $".$f['name'].' = [];';
                    }

                    return $validate."\n    public $".$f['name'].';';
                }

                return $validate."\n    public {$this->getPropertyType($f['type'])} \${$f['name']} = {$this->getDefaultValue($f['type'])};";
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
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
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
                $validate = $this->getValidationAttribute($f, true);
                if (in_array($f['type'], ['image', 'file'])) {
                    if ($f['multiple'] ?? false) {
                        return $validate."\n    public $".$f['name'].' = [];'."\n    public array $".$f['name'].'ToRemove = [];';
                    }

                    return $validate."\n    public $".$f['name'].';';
                }

                return $validate."\n    public {$this->getPropertyType($f['type'])} \${$f['name']};";
            })
            ->implode("\n    ");

        $allProperties = trim(implode("\n    ", array_filter([$belongsToProps, $belongsToOpts, $belongsToManyProps, $belongsToManyOpts, $properties])));

        $fillBelongsToProperties = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => '$this->'.Str::snake($r['name'])."_id = \${$modelVar}->".Str::snake($r['name']).'_id;')
            ->implode("\n    ");
        $fillBelongsToManyProperties = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => '$this->selected'.Str::studly(Str::plural($r['name']))."Ids = \${$modelVar}->".Str::plural($r['name'])."->pluck('id')->toArray();")
            ->implode("\n    ");
        $mountBody = collect($this->getRelationships())
            ->filter(fn ($r) => in_array($r['type'], ['belongsTo', 'belongsToMany']))
            ->map(function ($r) {
                if ($r['type'] === 'belongsTo') {
                    return '$this->'.Str::camel($r['model']).'Options = \App\Models\\'.$r['model'].'::limit(100)->get();';
                }

                return '$this->'.Str::camel(Str::plural($r['name'])).'Options = \App\Models\\'.$r['model'].'::limit(100)->get();';
            })
            ->implode("\n    ");

        $fillProperties = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(function ($f) use ($modelVar) {
                if (in_array($f['type'], ['image', 'file'])) {
                    return '// File fields are not pre-filled for security';
                }

                return '$this->'.$f['name'].' = $'.$modelVar.'->'.$f['name'].';';
            })
            ->implode("\n    ");
        $fillProperties = trim(implode("\n    ", array_filter([$mountBody, $fillBelongsToProperties, $fillBelongsToManyProperties, $fillProperties])));

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
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);
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
        $showFields = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id' || $f['type'] === 'foreign')
            ->map(fn ($f) => $this->generateShowField($f, $modelVar));

        $belongsToShow = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => $this->generateBelongsToShowField($r, $modelVar));

        $belongsToManyShow = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => $this->generateBelongsToManyShowField($r, $modelVar));

        $details = collect([$belongsToShow, $belongsToManyShow, $showFields])
            ->flatten()
            ->implode("\n\n    ");

        $stub = $this->getStub('volt-show');
        $stub = str_replace('{{ model }}', $modelBase, $stub);
        $stub = str_replace('{{ modelNamespace }}', 'App\Models', $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', $pluralBase, $stub);
        $stub = str_replace('{{ showFields }}', $details, $stub);
        $stub = str_replace('{!! editRoute !!}', "<a href=\"{{ route('{$kebabModels}.edit', \${$modelVar}) }}\" role=\"button\">Edit</a>", $stub);
        $stub = str_replace('{{ titleSingular }}', $modelBase, $stub);
        $stub = str_replace('{{ route }}', '/'.$kebabModels, $stub);
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);

        $this->createFile($path, $stub);

        return [$path];
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

    protected function getDefaultValue(string $type): string
    {
        return match ($type) {
            'boolean' => 'false',
            'integer', 'bigint' => '0',
            default => "''",
        };
    }

    /** @param  array<array<string, mixed>> $fields */
    protected function generateFormFields(array $fields): string
    {
        $fieldMarkup = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id' || $f['type'] === 'foreign')
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
            ->all();

        $relationshipMarkup = collect($this->getRelationships())
            ->filter(fn ($relationship) => $relationship['type'] === 'belongsTo')
            ->map(fn ($relationship) => $this->generateRelationshipField($relationship))
            ->all();

        $belongsToManyMarkup = collect($this->getRelationships())
            ->filter(fn ($relationship) => $relationship['type'] === 'belongsToMany')
            ->map(fn ($relationship) => $this->generateBelongsToManyField($relationship))
            ->all();

        return implode("\n\n", array_filter([...$fieldMarkup, ...$relationshipMarkup, ...$belongsToManyMarkup]));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function generateShowField(array $field, string $modelVar): string
    {
        $label = Str::title(str_replace('_', ' ', $field['name']));
        $name = $field['name'];

        if (in_array($field['type'], ['image', 'file'])) {
            return $this->generateShowFileField($field, $label, $modelVar);
        }

        return '<div><h6>'.$label.'</h6><p>@if($'.$modelVar.'->'.$name.') {{ $'.$modelVar.'->'.$name.' }} @else <em class="text-muted">—</em> @endif</p></div>';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function generateShowFileField(array $field, string $label, string $modelVar): string
    {
        $name = $field['name'];

        if ($field['multiple'] ?? false) {
            return <<<BLADE
<div>
        <h6>{$label}</h6>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            @if(!empty(\${$modelVar}->{$name}))
                @foreach(is_array(\${$modelVar}->{$name}) ? \${$modelVar}->{$name} : json_decode(\${$modelVar}->{$name}, true) ?? [] as \$path)
                    @if(Str::endsWith(\$path, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                        <img src="{{ asset('storage/' . \$path) }}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;" />
                    @else
                        <a href="{{ asset('storage/' . \$path) }}" target="_blank" class="outline">{{ basename(\$path) }}</a>
                    @endif
                @endforeach
            @else
                <em class="text-muted">—</em>
            @endif
        </div>
    </div>
BLADE;
        }

        return <<<BLADE
<div>
        <h6>{$label}</h6>
        <p>
            @if(\${$modelVar}->{$name})
                @if(Str::endsWith(\${$modelVar}->{$name}, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                    <img src="{{ asset('storage/' . \${$modelVar}->{$name}) }}" style="max-width: 300px; max-height: 200px; border-radius: 4px;" />
                @else
                    <a href="{{ asset('storage/' . \${$modelVar}->{$name}) }}" target="_blank" class="outline">{{ basename(\${$modelVar}->{$name}) }}</a>
                @endif
            @else
                <em class="text-muted">—</em>
            @endif
        </p>
    </div>
BLADE;
    }

    /**
     * @param  array<string, mixed>  $relationship
     */
    protected function generateBelongsToShowField(array $relationship, string $modelVar): string
    {
        $label = Str::title($relationship['name']);
        $name = $relationship['name'];

        return <<<BLADE
<div>
        <h6>{$label}</h6>
        <p>
            @if(\${$modelVar}->{$name})
                {{ \${$modelVar}->{$name}->name ?? \${$modelVar}->{$name}->id }}
            @else
                <em class="text-muted">—</em>
            @endif
        </p>
    </div>
BLADE;
    }

    /**
     * @param  array<string, mixed>  $relationship
     */
    protected function generateBelongsToManyShowField(array $relationship, string $modelVar): string
    {
        $label = Str::title(Str::plural($relationship['name']));
        $name = Str::plural($relationship['name']);

        return <<<BLADE
<div>
        <h6>{$label}</h6>
        <p>
            @if(\${$modelVar}->{$name}->isNotEmpty())
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @foreach(\${$modelVar}->{$name} as \$item)
                        <span class="outline" style="padding: 0.25rem 0.5rem;">{{ \$item->name ?? \$item->id }}</span>
                    @endforeach
                </div>
            @else
                <em class="text-muted">—</em>
            @endif
        </p>
    </div>
BLADE;
    }

    /**
     * @param  array<string, mixed>  $relationship
     */
    protected function generateRelationshipField(array $relationship): string
    {
        $label = Str::title(str_replace('_', ' ', $relationship['name']));
        $foreignKey = Str::snake($relationship['name']).'_id';
        $relatedVar = $this->camelCase($relationship['model']);

        return <<<BLADE
<label>
                    {$label}
                    <select wire:model="{$foreignKey}">
                        <option value="">Select {$label}</option>
                        @foreach(\${$relatedVar}Options as \$option)
                            <option value="{{ \$option->id }}">{{ \$option->name ?? \$option->id }}</option>
                        @endforeach
                    </select>
                    <small class="text-red-500">@error('{$foreignKey}') {{ \$message }} @enderror</small>
                </label>
BLADE;
    }

    /**
     * @param  array<string, mixed>  $relationship
     */
    protected function generateBelongsToManyField(array $relationship): string
    {
        $label = Str::title(Str::plural($relationship['name']));
        $propertyName = 'selected'.Str::studly(Str::plural($relationship['name'])).'Ids';
        $optionsVar = Str::camel(Str::plural($relationship['name'])).'Options';

        return <<<BLADE
<fieldset>
                    <legend>{$label}</legend>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        @foreach(\${$optionsVar} as \$option)
                            <label style="display: inline-flex; align-items: center; gap: 0.4rem; width: auto;">
                                <input type="checkbox" wire:model="{$propertyName}" value="{{ \$option->id }}" />
                                {{ \$option->name ?? \$option->id }}
                            </label>
                        @endforeach
                    </div>
                    <small class="text-red-500">@error('{$propertyName}') {{ \$message }} @enderror</small>
                </fieldset>
BLADE;
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

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getValidationAttribute(array $field, bool $isUpdate): string
    {
        $rules = [];
        $rules[] = $isUpdate ? (($field['nullable'] ?? true) ? 'nullable' : 'sometimes') : (($field['nullable'] ?? false) ? 'nullable' : 'required');

        if ($field['type'] === 'email') {
            $rules[] = 'email';
        }
        if (in_array($field['type'], ['integer', 'bigint'])) {
            $rules[] = 'integer';
        }
        if ($field['type'] === 'boolean') {
            $rules[] = 'boolean';
        }
        if (in_array($field['type'], ['date', 'datetime'])) {
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

        return "#[Validate('".implode('|', $rules)."')]";
    }
}
