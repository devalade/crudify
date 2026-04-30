<?php

namespace Crudify;

class RelationshipParser
{
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
        $this->relationships = $relationships;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getRelationships(): array
    {
        return $this->relationships;
    }
}
