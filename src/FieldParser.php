<?php

namespace Crudify;

class FieldParser
{
    protected array $fields = [];

    public function parse(string $fieldsString): self
    {
        $fieldStrings = explode(',', $fieldsString);

        foreach ($fieldStrings as $fieldString) {
            $fieldString = trim($fieldString);

            if (empty($fieldString)) {
                continue;
            }

            $default = null;
            $foreignTable = null;

            // Extract default:value before splitting by colon
            if (preg_match('/default:([^,]+)/', $fieldString, $matches)) {
                $default = $matches[1];
                $fieldString = str_replace($matches[0], '', $fieldString);
                $fieldString = rtrim($fieldString, ':');
            }

            // Extract foreign:table before splitting by colon
            if (preg_match('/foreign:([^,]+)/', $fieldString, $matches)) {
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
            ];

            foreach ($parts as $part) {
                match ($part) {
                    'nullable' => $field['nullable'] = true,
                    'unique' => $field['unique'] = true,
                    'index' => $field['index'] = true,
                    'foreign' => $field['type'] = 'foreign',
                    default => $field['type'] = $part,
                };
            }

            $this->fields[] = $field;
        }

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getFillable(): array
    {
        return array_column($this->fields, 'name');
    }

    public function getCasts(): array
    {
        $casts = [];

        foreach ($this->fields as $field) {
            $cast = $this->getCastType($field['type']);
            if ($cast) {
                $casts[$field['name']] = $cast;
            }
        }

        return $casts;
    }

    protected function getCastType(string $type): ?string
    {
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

    public function getMigrationType(string $type): string
    {
        return match ($type) {
            'string' => 'string',
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
}
