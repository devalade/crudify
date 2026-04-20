<?php

namespace Crudify;

use Symfony\Component\Yaml\Yaml;

class YamlParser
{
    protected array $data = [];

    public function parse(string $yamlPath): self
    {
        if (!file_exists($yamlPath)) {
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

    public function getFields(): array
    {
        $fields = $this->data['fields'] ?? [];
        $parsed = [];

        foreach ($fields as $name => $config) {
            if (is_string($config)) {
                $config = ['type' => $config];
            }

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

    public function getSearchable(): array
    {
        return $this->data['searchable'] ?? [];
    }

    public function getOptions(): array
    {
        return $this->data['options'] ?? [];
    }

    public function hasSoftDeletes(): bool
    {
        return $this->data['options']['soft_deletes'] ?? false;
    }
}
