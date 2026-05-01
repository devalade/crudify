<?php

namespace Crudify\Generators;

use Crudify\FieldParser;
use Crudify\RelationshipParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseGenerator implements Generator
{
    protected Filesystem $files;

    protected FieldParser $fieldParser;

    protected ?RelationshipParser $relationshipParser = null;

    protected bool $force = false;

    protected bool $dryRun = false;

    protected bool $softDeletes = false;

    protected string $layoutView = 'components.layouts.app';

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(Filesystem $files, FieldParser $fieldParser, array $options = [], ?RelationshipParser $relationshipParser = null)
    {
        $this->files = $files;
        $this->fieldParser = $fieldParser;
        $this->relationshipParser = $relationshipParser;
        $this->force = $options['force'] ?? false;
        $this->dryRun = $options['dryRun'] ?? false;
        $this->softDeletes = $options['softDeletes'] ?? false;
        $this->layoutView = is_string($options['layoutView'] ?? null) && $options['layoutView'] !== ''
            ? $options['layoutView']
            : 'components.layouts.app';
    }

    protected function getStub(string $name): string
    {
        $customPath = null;

        try {
            $customPath = base_path("stubs/crudify/{$name}.stub");
        } catch (\Throwable) {
            // base_path() may not be available in testing contexts
        }

        if ($customPath && file_exists($customPath)) {
            $content = file_get_contents($customPath);
            if ($content === false) {
                throw new \RuntimeException("Unable to read stub: {$customPath}");
            }

            return $content;
        }

        $packagePath = __DIR__."/../../stubs/{$name}.stub";

        if (! file_exists($packagePath)) {
            throw new \RuntimeException("Stub not found: {$name} (looked at {$packagePath})");
        }

        $content = file_get_contents($packagePath);
        if ($content === false) {
            throw new \RuntimeException("Unable to read stub: {$packagePath}");
        }

        return $content;
    }

    protected function getPath(string $namespace, string $class): string
    {
        $relativePath = str_replace('\\', '/', $namespace);
        $relativePath = preg_replace('#^App/#', 'app/', $relativePath);

        return base_path($relativePath.'/'.$class.'.php');
    }

    protected function createFile(string $path, string $content): void
    {
        $content = str_replace('{{ layoutView }}', $this->layoutView, $content);

        if ($this->dryRun) {
            return;
        }

        if (file_exists($path) && ! $this->force) {
            throw new \RuntimeException("File already exists: {$path}. Use --force to overwrite.");
        }

        $directory = dirname($path);

        if (! $this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, $content);
    }

    protected function pluralize(string $string): string
    {
        return Str::plural($string);
    }

    protected function camelCase(string $string): string
    {
        return Str::camel($string);
    }

    protected function kebabCase(string $string): string
    {
        return Str::kebab($string);
    }

    /** @return array<int, array<string, mixed>> */
    protected function getRelationships(): array
    {
        return $this->relationshipParser?->getRelationships() ?? [];
    }

    protected function hasField(string $name): bool
    {
        foreach ($this->fieldParser->getFields() as $field) {
            if (($field['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $relationship */
    protected function relationshipForeignKey(array $relationship): string
    {
        if (is_string($relationship['foreign_key'] ?? null) && $relationship['foreign_key'] !== '') {
            return $relationship['foreign_key'];
        }

        $name = is_string($relationship['name'] ?? null) ? $relationship['name'] : 'relation';

        return Str::snake($name).'_id';
    }

    /** @param  array<string, mixed>  $relationship */
    protected function relationshipTable(array $relationship): string
    {
        $model = is_string($relationship['model'] ?? null) ? class_basename($relationship['model']) : 'Model';

        return Str::plural(Str::snake($model));
    }

    /** @return array<int, string> */
    protected function relationshipForeignKeys(): array
    {
        return collect($this->getRelationships())
            ->filter(fn ($relationship) => ($relationship['type'] ?? null) === 'belongsTo')
            ->map(fn ($relationship) => $this->relationshipForeignKey($relationship))
            ->all();
    }

    protected function getWithClause(): string
    {
        $relationships = $this->getRelationships();

        if (empty($relationships)) {
            return '';
        }

        $names = [];

        foreach ($relationships as $rel) {
            if (is_string($rel['name'] ?? null)) {
                $names[] = $rel['name'];
            }
        }

        if (empty($names)) {
            return '';
        }

        return "->with(['".implode("', '", $names)."'])";
    }

    /** @param  array<string, mixed>  $relationship */
    protected function getRelationshipLabel(array $relationship, bool $plural = false): string
    {
        $label = is_string($relationship['label'] ?? null) && $relationship['label'] !== ''
            ? $relationship['label']
            : (is_string($relationship['name'] ?? null) ? $relationship['name'] : 'Relation');

        if ($plural && ! is_string($relationship['label'] ?? null)) {
            $label = Str::plural($label);
        }

        return Str::title(str_replace('_', ' ', $label));
    }

    /** @param  array<string, mixed>  $relationship */
    protected function getRelationshipDisplayField(array $relationship): string
    {
        $display = $relationship['display'] ?? 'name';

        return is_string($display) && $display !== '' ? $display : 'name';
    }

    /** @param  array<string, mixed>  $field */
    protected function isImageField(array $field): bool
    {
        return ($field['type'] ?? null) === 'image';
    }

    /** @param  array<string, mixed>  $field */
    protected function isVideoField(array $field): bool
    {
        return ($field['type'] ?? null) === 'video';
    }

    /** @param  array<string, mixed>  $field */
    protected function isMediaField(array $field): bool
    {
        return in_array($field['type'] ?? null, ['image', 'file', 'video'], true);
    }
}
