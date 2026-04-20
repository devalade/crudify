<?php

namespace Crudify;

use Symfony\Component\Yaml\Yaml;

class YamlParser
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function parse(string $yamlPath): self
    {
        if (! file_exists($yamlPath)) {
            throw new \Exception("YAML file not found: {$yamlPath}");
        }

        $this->data = Yaml::parseFile($yamlPath);

        return $this;
    }

    public function parseString(string $yamlString): self
    {
        $this->data = Yaml::parse($yamlString);

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->data['model'] ?? null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getFields(): array
    {
        /** @var array<string, mixed> $fields */
        $fields = $this->data['fields'] ?? [];
        $parsed = [];

        foreach ($fields as $name => $config) {
            if (is_string($config)) {
                $config = ['type' => $config];
            }

            /** @var array<string, mixed> $config */
            $parsed[] = [
                'name' => $name,
                'type' => $config['type'] ?? 'string',
                'nullable' => $config['nullable'] ?? false,
                'unique' => $config['unique'] ?? false,
                'default' => $config['default'] ?? null,
                'index' => $config['index'] ?? false,
                'foreign_table' => $config['foreign'] ?? null,
            ];
        }

        return $parsed;
    }

    /** @return array<int, string> */
    public function getSearchable(): array
    {
        return $this->data['searchable'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getRelationships(): array
    {
        /** @var array<string, mixed> $relationships */
        $relationships = $this->data['relationships'] ?? [];
        $parsed = [];

        foreach ($relationships as $name => $config) {
            if (! is_array($config)) {
                continue;
            }

            /** @var array<string, mixed> $config */
            $parsed[] = [
                'name' => $name,
                'type' => $config['type'] ?? 'belongsTo',
                'model' => $config['model'] ?? 'Model',
            ];
        }

        return $parsed;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->data['options'] ?? [];
    }

    public function hasSoftDeletes(): bool
    {
        return $this->data['options']['soft_deletes'] ?? false;
    }
}
