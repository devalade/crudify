<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class LivewireViewGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $modelBase = class_basename($model);
        $modelVar = $this->camelCase($modelBase);
        $models = $this->pluralize($modelVar);
        $kebabModels = $this->kebabCase($models);
        $viewPath = str_replace('/', '.', $kebabModels);

        $paths = [];
        $paths = array_merge($paths, $this->generateIndexView($modelBase, $modelVar, $models, $kebabModels, $viewPath));
        $paths = array_merge($paths, $this->generateCreateView($modelBase, $modelVar, $models, $kebabModels, $viewPath));
        $paths = array_merge($paths, $this->generateEditView($modelBase, $modelVar, $models, $kebabModels, $viewPath));
        $paths = array_merge($paths, $this->generateShowView($modelBase, $modelVar, $models, $kebabModels, $viewPath));

        return $paths;
    }

    /** @return array<string> */
    protected function generateIndexView(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath): array
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/index.blade.php");

        $fields = $this->fieldParser->getFields();
        $displayFields = collect($fields)->reject(fn ($f) => $f['name'] === 'id' || in_array($f['type'], ['image', 'file']))->take(5);
        $imageFields = collect($fields)->filter(fn ($f) => in_array($f['type'], ['image', 'file']))->values();
        $hasImage = $imageFields->isNotEmpty();
        $firstImage = $hasImage ? $imageFields->first() : null;

        $belongsToRels = collect($this->getRelationships())->filter(fn ($r) => $r['type'] === 'belongsTo');
        $belongsToManyRels = collect($this->getRelationships())->filter(fn ($r) => $r['type'] === 'belongsToMany');

        $headers = $displayFields->map(fn ($f) => "<th wire:click=\"sortBy('{$f['name']}')\">\n                            <span style=\"cursor: pointer;\">".Str::title(str_replace('_', ' ', $f['name'])).' '."@if(\$sortField === '{$f['name']}') @if(\$sortDirection === 'asc') &#9650; @else &#9660; @endif @endif</span>\n                        </th>")->implode("\n                    ");

        foreach ($belongsToRels as $rel) {
            $headers .= "\n                    <th>".Str::title($rel['name']).'</th>';
        }

        foreach ($belongsToManyRels as $rel) {
            $headers .= "\n                    <th>".Str::title(Str::plural($rel['name'])).'</th>';
        }

        if ($hasImage) {
            $headers .= "\n                    <th>Image</th>";
        }

        $rowContent = $displayFields->map(function ($f) use ($modelVar) {
            $limit = $f['type'] === 'text' ? 50 : 30;

            return "<td>\n                            @if(\${$modelVar}->{$f['name']})\n                                {{ Str::limit(\${$modelVar}->{$f['name']}, {$limit}) }}\n                            @else\n                                <em class=\"text-muted\">—</em>\n                            @endif\n                        </td>";
        })->implode("\n                    ");

        foreach ($belongsToRels as $rel) {
            $name = $rel['name'];
            $rowContent .= "\n                    <td>\n                            @if(\${$modelVar}->{$name})\n                                {{ \${$modelVar}->{$name}->name ?? \${$modelVar}->{$name}->id }}\n                            @else\n                                <em class=\"text-muted\">—</em>\n                            @endif\n                        </td>";
        }

        foreach ($belongsToManyRels as $rel) {
            $name = Str::plural($rel['name']);
            $rowContent .= "\n                    <td style=\"width: 1px; white-space: nowrap;\">\n                            @if(\${$modelVar}->{$name}->isNotEmpty())\n                                <div style=\"display: flex; flex-wrap: wrap; gap: 0.25rem;\">\n                                    @foreach(\${$modelVar}->{$name} as \$item)\n                                        <span class=\"outline\" style=\"padding: 0.15rem 0.4rem; font-size: 0.75rem;\">{{ \$item->name ?? \$item->id }}</span>\n                                    @endforeach\n                                </div>\n                            @else\n                                <em class=\"text-muted\">—</em>\n                            @endif\n                        </td>";
        }

        if ($hasImage && $firstImage) {
            $name = $firstImage['name'];
            $rowContent .= "\n                    <td style=\"width: 1px; white-space: nowrap;\">\n                            @if(\${$modelVar}->{$name})\n                                @if(Str::endsWith(\${$modelVar}->{$name}, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))\n                                    <img src=\"{{ asset('storage/' . \${$modelVar}->{$name}) }}\" style=\"width: 40px; height: 40px; object-fit: cover; border-radius: 4px;\" />\n                                @else\n                                    <a href=\"{{ asset('storage/' . \${$modelVar}->{$name}) }}\" target=\"_blank\" class=\"outline\" style=\"padding: 0.25rem 0.5rem;\">File</a>\n                                @endif\n                            @else\n                                <em class=\"text-muted\">—</em>\n                            @endif\n                        </td>";
        }

        $colspan = $displayFields->count() + $belongsToRels->count() + $belongsToManyRels->count() + ($hasImage ? 1 : 0) + 2;

        $stub = $this->getStub('livewire-index-view');
        $stub = str_replace('{{ title }}', Str::plural($modelBase), $stub);
        $stub = str_replace('{{ titleSingular }}', $modelBase, $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.create') }}", $stub);
        $stub = str_replace('{{ routeName }}', $kebabModels, $stub);
        $stub = str_replace('{{ headers }}', $headers, $stub);
        $stub = str_replace('{{ rowContent }}', $rowContent, $stub);
        $stub = str_replace('{{ colspan }}', (string) $colspan, $stub);
        $stub = str_replace('{{ models }}', $models, $stub);
        $stub = str_replace('{{ modelVar }}', $modelVar, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateCreateView(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath): array
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/create.blade.php");

        $fields = $this->fieldParser->getFields();
        $formFields = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(fn ($f) => $this->generateFormField($f, $modelVar))
            ->implode("\n            ");

        $belongsToFields = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => $this->generateRelationshipField($r))
            ->implode("\n            ");

        $belongsToManyFields = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => $this->generateBelongsToManyField($r))
            ->implode("\n            ");

        $allFields = collect([$belongsToFields, $belongsToManyFields, $formFields])
            ->filter()
            ->implode("\n            ");

        $stub = $this->getStub('livewire-create-view');
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', Str::plural($modelBase), $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.index') }}", $stub);
        $stub = str_replace('{{ formFields }}', $allFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateEditView(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath): array
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/edit.blade.php");

        $fields = $this->fieldParser->getFields();
        $formFields = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(fn ($f) => $this->generateFormField($f, $modelVar, true))
            ->implode("\n            ");

        $belongsToFields = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => $this->generateRelationshipField($r))
            ->implode("\n            ");

        $belongsToManyFields = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => $this->generateBelongsToManyField($r))
            ->implode("\n            ");

        $allFields = collect([$belongsToFields, $belongsToManyFields, $formFields])
            ->filter()
            ->implode("\n            ");

        $stub = $this->getStub('livewire-edit-view');
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', Str::plural($modelBase), $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.index') }}", $stub);
        $stub = str_replace('{{ formFields }}', $allFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /** @return array<string> */
    protected function generateShowView(string $modelBase, string $modelVar, string $models, string $kebabModels, string $viewPath): array
    {
        $path = base_path("resources/views/livewire/pages/{$kebabModels}/show.blade.php");

        $fields = $this->fieldParser->getFields();
        $showFields = collect($fields)
            ->reject(fn ($f) => $f['name'] === 'id')
            ->map(fn ($f) => $this->generateShowField($f, $modelVar))
            ->implode("\n            ");

        $belongsToShow = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsTo')
            ->map(fn ($r) => $this->generateBelongsToShowField($r, $modelVar))
            ->implode("\n            ");

        $belongsToManyShow = collect($this->getRelationships())
            ->filter(fn ($r) => $r['type'] === 'belongsToMany')
            ->map(fn ($r) => $this->generateBelongsToManyShowField($r, $modelVar))
            ->implode("\n            ");

        $allShowFields = collect([$belongsToShow, $belongsToManyShow, $showFields])
            ->filter()
            ->implode("\n            ");

        $stub = $this->getStub('livewire-show-view');
        $stub = str_replace('{{ title }}', $modelBase, $stub);
        $stub = str_replace('{{ titleSingular }}', $modelBase, $stub);
        $stub = str_replace('{{ pluralTitle }}', Str::plural($modelBase), $stub);
        $stub = str_replace('{{ route }}', "{{ route('{$kebabModels}.index') }}", $stub);
        $stub = str_replace('{{ editRoute }}', "{{ route('{$kebabModels}.edit', \${$modelVar}) }}", $stub);
        $stub = str_replace('{{ showFields }}', $allShowFields, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function generateFormField(array $field, string $modelVar, bool $isEdit = false): string
    {
        $label = Str::title(str_replace('_', ' ', $field['name']));
        $name = $field['name'];

        if ($field['type'] === 'image' || $field['type'] === 'file') {
            return $this->generateFileField($field, $label, $modelVar, $isEdit);
        }

        if (in_array($field['type'], ['text'])) {
            return <<<BLADE
<label>
                {$label}
                <textarea wire:model="{$name}" rows="4" placeholder="Enter {$label}..."></textarea>
                <small class="text-red-500">@error('{$name}') {{ \$message }} @enderror</small>
            </label>
BLADE;
        }

        if ($field['type'] === 'boolean') {
            return <<<BLADE
<label>
                <input type="checkbox" wire:model="{$name}" />
                {$label}
            </label>
BLADE;
        }

        if (in_array($field['type'], ['date', 'datetime'])) {
            $inputType = $field['type'] === 'datetime' ? 'datetime-local' : 'date';

            return <<<BLADE
<label>
                {$label}
                <input type="{$inputType}" wire:model="{$name}" />
                <small class="text-red-500">@error('{$name}') {{ \$message }} @enderror</small>
            </label>
BLADE;
        }

        if (in_array($field['type'], ['integer', 'bigint', 'float', 'decimal'])) {
            return <<<BLADE
<label>
                {$label}
                <input type="number" wire:model="{$name}" placeholder="Enter {$label}..." />
                <small class="text-red-500">@error('{$name}') {{ \$message }} @enderror</small>
            </label>
BLADE;
        }

        return <<<BLADE
<label>
            {$label}
            <input type="text" wire:model="{$name}" placeholder="Enter {$label}..." />
            <small class="text-red-500">@error('{$name}') {{ \$message }} @enderror</small>
        </label>
BLADE;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function generateFileField(array $field, string $label, string $modelVar, bool $isEdit = false): string
    {
        $name = $field['name'];
        $isMultiple = $field['multiple'] ?? false;
        $multipleAttr = $isMultiple ? ' multiple' : '';
        $accept = $field['type'] === 'image' ? 'accept="image/*"' : '';

        $preview = '';
        if ($isEdit) {
            if ($isMultiple) {
                $methodName = 'remove'.Str::studly($name).'File';
                $preview = <<<BLADE

            @if(!empty(\${$modelVar}->{$name}))
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                    @foreach(is_array(\${$modelVar}->{$name}) ? \${$modelVar}->{$name} : json_decode(\${$modelVar}->{$name}, true) ?? [] as \$path)
                        @unless(in_array(\$path, \${$name}ToRemove))
                            <div style="position: relative;">
                                @if(Str::endsWith(\$path, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                                    <img src="{{ asset('storage/' . \$path) }}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;" />
                                @else
                                    <a href="{{ asset('storage/' . \$path) }}" target="_blank" class="outline" style="padding: 0.5rem;">{{ basename(\$path) }}</a>
                                @endif
                                <button type="button" wire:click="{$methodName}('{{ \$path }}')" style="position: absolute; top: -6px; right: -6px; background: #dc2626; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
                            </div>
                        @endunless
                    @endforeach
                </div>
            @endif
BLADE;
            } else {
                $preview = <<<BLADE

            @if(\${$modelVar}->{$name})
                <div style="margin-top: 0.5rem;">
                    @if(Str::endsWith(\${$modelVar}->{$name}, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                        <img src="{{ asset('storage/' . \${$modelVar}->{$name}) }}" style="max-width: 200px; max-height: 150px; border-radius: 4px;" />
                    @else
                        <a href="{{ asset('storage/' . \${$modelVar}->{$name}) }}" target="_blank" class="outline">{{ basename(\${$modelVar}->{$name}) }}</a>
                    @endif
                </div>
            @endif
BLADE;
            }
        }

        return <<<BLADE
<label>
            {$label}
            <input type="file" wire:model="{$name}"{$multipleAttr} {$accept} />
            <small class="text-red-500">@error('{$name}') {{ \$message }} @enderror</small>
            {$preview}
        </label>
BLADE;
    }

    /**
     * @param  array<string, mixed>  $relationship
     */
    protected function generateRelationshipField(array $relationship): string
    {
        $label = Str::title(str_replace('_', ' ', $relationship['name']));
        $foreignKey = Str::snake($relationship['name']).'_id';
        $relatedModel = $relationship['model'];
        $relatedVar = $this->camelCase($relatedModel);

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

    /**
     * @param  array<string, mixed>  $field
     */
    protected function generateShowField(array $field, string $modelVar): string
    {
        $label = Str::title(str_replace('_', ' ', $field['name']));
        $name = $field['name'];

        if ($field['type'] === 'image' || $field['type'] === 'file') {
            return $this->generateShowFileField($field, $label, $modelVar);
        }

        return <<<BLADE
<div>
                <h6>{$label}</h6>
                <p>
                    @if(\${$modelVar}->{$name})
                        {{ \${$modelVar}->{$name} }}
                    @else
                        <em class="text-muted">—</em>
                    @endif
                </p>
            </div>
BLADE;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function generateShowFileField(array $field, string $label, string $modelVar): string
    {
        $name = $field['name'];
        $isMultiple = $field['multiple'] ?? false;

        if ($isMultiple) {
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

    /** @return array<string> */
    public function types(): array
    {
        return ['livewire-view'];
    }
}
