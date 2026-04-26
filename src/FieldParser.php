<?php

namespace Crudify;

class FieldParser
{
    /** @var array<int, array<string, mixed>> */
    protected array $fields = [];

    public function parse(string $fieldsString): self
    {
        $fieldStrings = preg_split('/\s*[|;]\s*/', $fieldsString) ?: [];

        foreach ($fieldStrings as $fieldString) {
            $fieldString = trim($fieldString);

            if (empty($fieldString)) {
                continue;
            }

            $default = null;
            $foreignTable = null;

            // Extract default:value before splitting by colon
            if (preg_match('/default:([^|;]+)/', $fieldString, $matches)) {
                $default = $matches[1];
                $fieldString = str_replace($matches[0], '', $fieldString);
                $fieldString = rtrim($fieldString, ':');
            }

            // Extract foreign:table before splitting by colon
            if (preg_match('/foreign:([^:|;]+)/', $fieldString, $matches)) {
                $foreignTable = $matches[1];
                $fieldString = str_replace($matches[0], '', $fieldString);
                $fieldString = rtrim($fieldString, ':');
            }

            $parts = explode(':', $fieldString);
            $parts = array_values(array_filter($parts));
            $name = array_shift($parts);

            if (empty($name)) {
                continue;
            }

            $field = [
                'name' => $name,
                'type' => $foreignTable ? 'foreign' : 'string',
                'nullable' => false,
                'unique' => false,
                'default' => $default,
                'index' => false,
                'foreign_table' => $foreignTable,
                'multiple' => false,
            ];

            foreach ($parts as $part) {
                match ($part) {
                    'nullable' => $field['nullable'] = true,
                    'unique' => $field['unique'] = true,
                    'index' => $field['index'] = true,
                    'foreign' => $field['type'] = 'foreign',
                    'multiple' => $field['multiple'] = true,
                    default => $field['type'] = $part,
                };
            }

            $this->fields[] = $field;
        }

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getFields(): array
    {
        return $this->fields;
    }

    /** @return array<int, string> */
    public function getFillable(): array
    {
        return array_column($this->fields, 'name');
    }

    /** @return array<string, string> */
    public function getCasts(): array
    {
        $casts = [];

        foreach ($this->fields as $field) {
            $cast = $this->getCastType($field);
            if ($cast) {
                $casts[$field['name']] = $cast;
            }
        }

        return $casts;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function getCastType(array $field): ?string
    {
        $type = $field['type'];

        if ($field['multiple'] && in_array($type, ['image', 'file'])) {
            return 'array';
        }

        return match ($type) {
            'boolean' => 'boolean',
            'integer', 'bigint' => 'integer',
            'float', 'double', 'decimal' => 'float',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'json', 'array' => 'array',
            'object' => 'object',
            'collection' => 'collection',
            default => null,
        };
    }

    public function getMigrationType(string $type, bool $multiple = false): string
    {
        if ($multiple && in_array($type, ['image', 'file'])) {
            return 'json';
        }

        return match ($type) {
            'string', 'image', 'file' => 'string',
            'text' => 'text',
            'integer' => 'integer',
            'bigint' => 'bigInteger',
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'time' => 'time',
            'json' => 'json',
            'uuid' => 'uuid',
            'foreign' => 'foreignId',
            default => 'string',
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function getFileFields(): array
    {
        return array_filter($this->fields, fn ($f) => in_array($f['type'], ['image', 'file']));
    }

    /** @return array<int, array<string, mixed>> */
    public function getSingleFileFields(): array
    {
        return array_filter($this->fields, fn ($f) => in_array($f['type'], ['image', 'file']) && ! $f['multiple']);
    }

    /** @return array<int, array<string, mixed>> */
    public function getMultipleFileFields(): array
    {
        return array_filter($this->fields, fn ($f) => in_array($f['type'], ['image', 'file']) && $f['multiple']);
    }
}
