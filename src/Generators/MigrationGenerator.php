<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class MigrationGenerator extends BaseGenerator
{
    public function generate(string $model): array
    {
        $table = Str::plural(Str::snake($model));
        $timestamp = now()->format('Y_m_d_His');
        $filename = "{$timestamp}_create_{$table}_table.php";
        $path = database_path("migrations/{$filename}");

        $fields = $this->fieldParser->getFields();
        $columns = [];

        foreach ($fields as $field) {
            if ($field['name'] === 'id') {
                continue;
            }

            $type = $this->fieldParser->getMigrationType($field['type']);
            $column = "\$table->{$type}('{$field['name']}')";

            if ($field['nullable']) {
                $column .= '->nullable()';
            }

            if ($field['unique']) {
                $column .= '->unique()';
            }

            if ($field['index']) {
                $column .= '->index()';
            }

            if ($field['default'] !== null) {
                $defaultValue = is_numeric($field['default'])
                    ? $field['default']
                    : "'{$field['default']}'";
                $column .= "->default({$defaultValue})";
            }

            if ($field['type'] === 'foreign') {
                $column .= "->constrained('{$field['foreign_table']}')";

                if ($field['nullable']) {
                    $column .= '->nullOnDelete()';
                }
            }

            $column .= ';';
            $columns[] = $column;
        }

        $columnsStr = implode("\n            ", $columns);

        $softDeletes = $this->softDeletes ? '$table->softDeletes();' : '';

        $stub = $this->getStub('migration');
        $stub = str_replace('{{ table }}', $table, $stub);
        $stub = str_replace('{{ columns }}', $columnsStr, $stub);
        $stub = str_replace('{{ softDeletes }}', $softDeletes, $stub);

        $this->createFile($path, $stub);

        return [$path];
    }

    public function types(): array
    {
        return ['migration'];
    }
}
