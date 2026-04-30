<?php

namespace Crudify;

class RelationshipParser
{
    /** @var array<int, string> */
    protected const TYPES = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];

    /** @var array<int, array<string, mixed>> */
    protected array $relationships = [];

    public function parse(string $relationshipsString): self
    {
        $this->relationships = [];
        $items = preg_split('/\s*[|;]\s*/', $relationshipsString) ?: [];

        foreach ($items as $item) {
            $item = trim($item);

            if (empty($item)) {
                continue;
            }

            $parts = explode(':', $item);

            if (count($parts) < 3) {
                continue;
            }

            if (! in_array($parts[1], self::TYPES, true)) {
                throw new \InvalidArgumentException("Invalid relationship type '{$parts[1]}' for '{$parts[0]}'.");
            }

            $this->relationships[] = [
                'name' => $parts[0],
                'type' => $parts[1],
                'model' => $parts[2],
                'display' => $parts[3] ?? 'name',
                'foreign_key' => $parts[4] ?? null,
            ];
        }

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $relationships
     */
    public function setRelationships(array $relationships): self
    {
        foreach ($relationships as $relationship) {
            $type = $relationship['type'] ?? null;
            $name = $relationship['name'] ?? 'relationship';

            if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
                throw new \InvalidArgumentException("Invalid relationship type '{$type}' for '{$name}'.");
            }
        }

        $this->relationships = $relationships;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getRelationships(): array
    {
        return $this->relationships;
    }
}
