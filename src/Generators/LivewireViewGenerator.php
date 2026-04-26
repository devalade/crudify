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

        $headers = $displayFields->map(fn ($f) => "<flux:table.column wire:click=\"sortBy('{$f['name']}')\" class=\"cursor-pointer\">\n                            ".Str::title(str_replace('_', ' ', $f['name'])).' '."@if(\$sortField === '{$f['name']}') @if(\$sortDirection === 'asc') ↑ @else ↓ @endif @endif\n                        </flux:table.column>")->implode("\n            ");

        foreach ($belongsToRels as $rel) {
            $headers .= "\n            <flux:table.column>".Str::title($rel['name']).'</flux:table.column>';
        }

        foreach ($belongsToManyRels as $rel) {
            $headers .= "\n            <flux:table.column>".Str::title(Str::plural($rel['name'])).'</flux:table.column>';
        }

        if ($hasImage) {
            $headers .= "\n            <flux:table.column>Image</flux:table.column>";
        }

        $rowContent = $displayFields->map(function ($f) use ($modelVar) {
            $limit = $f['type'] === 'text' ? 50 : 30;

            return "<flux:table.cell>\n                        @if(\${$modelVar}->{$f['name']})\n                            {{ Str::limit(\${$modelVar}->{$f['name']}, {$limit}) }}\n                        @else\n                            <span class=\"text-zinc-400\">—</span>\n                        @endif\n                    </flux:table.cell>";
        })->implode("\n                    ");

        foreach ($belongsToRels as $rel) {
            $name = $rel['name'];
            $rowContent .= "\n                    <flux:table.cell>\n                        @if(\${$modelVar}->{$name})\n                            {{ \${$modelVar}->{$name}->name ?? \${$modelVar}->{$name}->id }}\n                        @else\n                            <span class=\"text-zinc-400\">—</span>\n                        @endif\n                    </flux:table.cell>";
        }

        foreach ($belongsToManyRels as $rel) {
            $name = Str::plural($rel['name']);
            $rowContent .= "\n                    <flux:table.cell>\n                        @if(\${$modelVar}->{$name}->isNotEmpty())\n                            <div class=\"flex flex-wrap gap-2\">\n                                @foreach(\${$modelVar}->{$name} as \$item)\n                                    <flux:badge size=\"sm\">{{ \$item->name ?? \$item->id }}</flux:badge>\n                                @endforeach\n                            </div>\n                        @else\n                            <span class=\"text-zinc-400\">—</span>\n                        @endif\n                    </flux:table.cell>";
        }

        if ($hasImage && $firstImage) {
            $name = $firstImage['name'];
            $rowContent .= "\n                    <flux:table.cell>\n                        @if(\${$modelVar}->{$name})\n                            @if(Str::endsWith(\${$modelVar}->{$name}, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))\n                                <img src=\"{{ asset('storage/' . \${$modelVar}->{$name}) }}\" class=\"h-10 w-10 rounded-lg object-cover\" />\n                            @else\n                                <flux:button size=\"sm\" variant=\"ghost\" href=\"{{ asset('storage/' . \${$modelVar}->{$name}) }}\" target=\"_blank\">File</flux:button>\n                            @endif\n                        @else\n                            <span class=\"text-zinc-400\">—</span>\n                        @endif\n                    </flux:table.cell>";
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
<div class="space-y-2">
                <flux:textarea wire:model="{$name}" label="{$label}" rows="4" placeholder="Enter {$label}..." />
                @error('{$name}') <flux:text class="text-red-500">{{ \$message }}</flux:text> @enderror
            </div>
BLADE;
        }

        if ($field['type'] === 'boolean') {
            return <<<BLADE
<div class="space-y-2">
                <flux:checkbox wire:model="{$name}" label="{$label}" />
            </div>
BLADE;
        }

        if (in_array($field['type'], ['date', 'datetime'])) {
            $inputType = $field['type'] === 'datetime' ? 'datetime-local' : 'date';

            return <<<BLADE
<div class="space-y-2">
                <flux:input type="{$inputType}" wire:model="{$name}" label="{$label}" />
                @error('{$name}') <flux:text class="text-red-500">{{ \$message }}</flux:text> @enderror
            </div>
BLADE;
        }

        if (in_array($field['type'], ['integer', 'bigint', 'float', 'decimal'])) {
            return <<<BLADE
<div class="space-y-2">
                <flux:input type="number" wire:model="{$name}" label="{$label}" placeholder="Enter {$label}..." />
                @error('{$name}') <flux:text class="text-red-500">{{ \$message }}</flux:text> @enderror
            </div>
BLADE;
        }

        return <<<BLADE
<div class="space-y-2">
            <flux:input type="text" wire:model="{$name}" label="{$label}" placeholder="Enter {$label}..." />
            @error('{$name}') <flux:text class="text-red-500">{{ \$message }}</flux:text> @enderror
        </div>
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
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach(is_array(\${$modelVar}->{$name}) ? \${$modelVar}->{$name} : json_decode(\${$modelVar}->{$name}, true) ?? [] as \$path)
                        @unless(in_array(\$path, \${$name}ToRemove))
                            <div class="relative">
                                @if(Str::endsWith(\$path, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                                    <img src="{{ asset('storage/' . \$path) }}" class="h-20 w-20 rounded-xl object-cover" />
                                @else
                                    <flux:button variant="ghost" href="{{ asset('storage/' . \$path) }}" target="_blank">{{ basename(\$path) }}</flux:button>
                                @endif
                                <flux:button type="button" size="sm" variant="danger" wire:click="{$methodName}('{{ \$path }}')" class="absolute -right-2 -top-2 !rounded-full !px-2">×</flux:button>
                            </div>
                        @endunless
                    @endforeach
                </div>
            @endif
BLADE;
            } else {
                $preview = <<<BLADE

            @if(\${$modelVar}->{$name})
                <div class="mt-3">
                    @if(Str::endsWith(\${$modelVar}->{$name}, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                        <img src="{{ asset('storage/' . \${$modelVar}->{$name}) }}" class="max-h-40 rounded-xl object-cover" />
                    @else
                        <flux:button variant="ghost" href="{{ asset('storage/' . \${$modelVar}->{$name}) }}" target="_blank">{{ basename(\${$modelVar}->{$name}) }}</flux:button>
                    @endif
                </div>
            @endif
BLADE;
            }
        }

        return <<<BLADE
<div class="space-y-2">
            <flux:input type="file" wire:model="{$name}" label="{$label}"{$multipleAttr} {$accept} />
            @error('{$name}') <flux:text class="text-red-500">{{ \$message }}</flux:text> @enderror
            {$preview}
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
        $relatedModel = $relationship['model'];
        $relatedVar = $this->camelCase($relatedModel);

        return <<<BLADE
<div class="space-y-2">
            <flux:select wire:model="{$foreignKey}" label="{$label}">
                <option value="">Select {$label}</option>
                @foreach(\${$relatedVar}Options as \$option)
                    <option value="{{ \$option->id }}">{{ \$option->name ?? \$option->id }}</option>
                @endforeach
            </flux:select>
            @error('{$foreignKey}') <flux:text class="text-red-500">{{ \$message }}</flux:text> @enderror
        </div>
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
<div class="space-y-3">
            <flux:field>
                <flux:label>{$label}</flux:label>
            </flux:field>
            <div class="flex flex-wrap gap-3">
                @foreach(\${$optionsVar} as \$option)
                    <flux:checkbox wire:model="{$propertyName}" value="{{ \$option->id }}" label="{{ \$option->name ?? \$option->id }}" />
                @endforeach
            </div>
            @error('{$propertyName}') <flux:text class="text-red-500">{{ \$message }}</flux:text> @enderror
        </div>
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
<div class="space-y-2">
                <flux:subheading>{$label}</flux:subheading>
                <flux:text>
                    @if(\${$modelVar}->{$name})
                        {{ \${$modelVar}->{$name} }}
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </flux:text>
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
<div class="space-y-3">
                <flux:subheading>{$label}</flux:subheading>
                <div class="flex flex-wrap gap-3">
                    @if(!empty(\${$modelVar}->{$name}))
                        @foreach(is_array(\${$modelVar}->{$name}) ? \${$modelVar}->{$name} : json_decode(\${$modelVar}->{$name}, true) ?? [] as \$path)
                            @if(Str::endsWith(\$path, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                                <img src="{{ asset('storage/' . \$path) }}" class="h-24 w-24 rounded-xl object-cover" />
                            @else
                                <flux:button variant="ghost" href="{{ asset('storage/' . \$path) }}" target="_blank">{{ basename(\$path) }}</flux:button>
                            @endif
                        @endforeach
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </div>
            </div>
BLADE;
        }

        return <<<BLADE
<div class="space-y-2">
                <flux:subheading>{$label}</flux:subheading>
                <div>
                    @if(\${$modelVar}->{$name})
                        @if(Str::endsWith(\${$modelVar}->{$name}, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                            <img src="{{ asset('storage/' . \${$modelVar}->{$name}) }}" class="max-h-56 rounded-xl object-cover" />
                        @else
                            <flux:button variant="ghost" href="{{ asset('storage/' . \${$modelVar}->{$name}) }}" target="_blank">{{ basename(\${$modelVar}->{$name}) }}</flux:button>
                        @endif
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </div>
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
<div class="space-y-2">
                <flux:subheading>{$label}</flux:subheading>
                <flux:text>
                    @if(\${$modelVar}->{$name})
                        {{ \${$modelVar}->{$name}->name ?? \${$modelVar}->{$name}->id }}
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </flux:text>
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
<div class="space-y-2">
                <flux:subheading>{$label}</flux:subheading>
                <div>
                    @if(\${$modelVar}->{$name}->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach(\${$modelVar}->{$name} as \$item)
                                <flux:badge>{{ \$item->name ?? \$item->id }}</flux:badge>
                            @endforeach
                        </div>
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </div>
            </div>
BLADE;
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['livewire-view'];
    }
}
