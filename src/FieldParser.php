<?php

namespace Crudify;

class FieldParser
{
    /** @var array<int, string> */
    public const TYPES = [
        'string',
        'text',
        'integer',
        'bigint',
        'float',
        'double',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'timestamp',
        'time',
        'json',
        'uuid',
        'email',
        'foreign',
        'image',
        'file',
        'video',
        'array',
        'object',
        'collection',
    ];

    /** @var array<int, string> */
    protected const MODIFIERS = ['nullable', 'unique', 'index', 'foreign', 'multiple'];

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
                if (! in_array($part, self::MODIFIERS, true) && ! in_array($part, self::TYPES, true)) {
                    throw new \InvalidArgumentException("Invalid field token '{$part}' for '{$name}'.");
                }

                match ($part) {
                    'nullable' => $field['nullable'] = true,
                    'unique' => $field['unique'] = true,
                    'index' => $field['index'] = true,
                    'foreign' => $field['type'] = 'foreign',
                    'multiple' => $field['multiple'] = true,
                    default => $field['type'] = $part,
                };
            }

            $this->validateField($field);

            $this->fields[] = $field;
        }

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function setFields(array $fields): self
    {
        foreach ($fields as $field) {
            $this->validateField($field);
        }

        $this->fields = $fields;

        return $this;
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

        if ($field['multiple'] && in_array($type, ['image', 'file', 'video'])) {
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
        if ($multiple && in_array($type, ['image', 'file', 'video'])) {
            return 'json';
        }

        return match ($type) {
            'string', 'image', 'file', 'video' => 'string',
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
            'json', 'array', 'object', 'collection' => 'json',
            'uuid' => 'uuid',
            'foreign' => 'foreignId',
            default => 'string',
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function validateField(array $field): void
    {
        $name = $field['name'] ?? null;
        $type = $field['type'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Field names cannot be empty.');
        }

        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Invalid field type '{$type}' for '{$name}'.");
        }

        if ($type === 'foreign' && (! is_string($field['foreign_table'] ?? null) || $field['foreign_table'] === '')) {
            throw new \InvalidArgumentException("Foreign field '{$name}' must define a foreign table, for example {$name}:foreign:users.");
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getFileFields(): array
    {
        return array_filter($this->fields, fn ($f) => in_array($f['type'], ['image', 'file', 'video']));
    }

    /** @return array<int, array<string, mixed>> */
    public function getSingleFileFields(): array
    {
        return array_filter($this->fields, fn ($f) => in_array($f['type'], ['image', 'file', 'video']) && ! $f['multiple']);
    }

    /** @return array<int, array<string, mixed>> */
    public function getMultipleFileFields(): array
    {
        return array_filter($this->fields, fn ($f) => in_array($f['type'], ['image', 'file', 'video']) && $f['multiple']);
    }
}
