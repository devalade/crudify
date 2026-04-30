<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class MigrationGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $table = Str::plural(Str::snake($model));
        $paths = [];

        $existingMigration = $this->findExistingMigration($table);
        if ($existingMigration !== null) {
            $paths[] = $existingMigration;
        } else {
            $timestamp = now()->format('Y_m_d_His');
            $filename = "{$timestamp}_create_{$table}_table.php";
            $path = database_path("migrations/{$filename}");

            $fields = $this->fieldParser->getFields();
            $columns = [];

            foreach ($this->getRelationships() as $rel) {
                if ($rel['type'] === 'belongsTo') {
                    $foreignKey = $this->relationshipForeignKey($rel);

                    if ($this->hasField($foreignKey)) {
                        continue;
                    }

                    $relatedTable = $this->relationshipTable($rel);
                    $columns[] = "\$table->foreignId('{$foreignKey}')->constrained('{$relatedTable}')->cascadeOnDelete();";
                }
            }

            foreach ($fields as $field) {
                if ($field['name'] === 'id') {
                    continue;
                }

                $type = $this->fieldParser->getMigrationType($field['type'], $field['multiple'] ?? false);
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

            $paths[] = $path;
        }

        return array_merge($paths, $this->generatePivotMigrations($model));
    }

    protected function findExistingMigration(string $table): ?string
    {
        $migrationsPath = database_path('migrations');

        if (! file_exists($migrationsPath)) {
            return null;
        }

        $files = glob($migrationsPath.'/*_create_'.$table.'_table.php');

        if (empty($files)) {
            return null;
        }

        return $files[0];
    }

    /**
     * @return array<string>
     */
    protected function generatePivotMigrations(string $model): array
    {
        $modelTable = Str::plural(Str::snake(class_basename($model)));
        $pivotPaths = [];
        $timestamp = now();

        foreach ($this->getRelationships() as $rel) {
            if (($rel['type'] ?? null) !== 'belongsToMany' || ! is_string($rel['model'] ?? null)) {
                continue;
            }

            $pivotTable = $this->pivotTableName(class_basename($model), $rel['model']);
            $existingMigration = $this->findExistingMigration($pivotTable);

            if ($existingMigration !== null) {
                $pivotPaths[] = $existingMigration;

                continue;
            }

            $relatedTable = Str::plural(Str::snake(class_basename($rel['model'])));
            $currentTimestamp = $timestamp->copy()->addSeconds(count($pivotPaths))->format('Y_m_d_His');
            $filename = "{$currentTimestamp}_create_{$pivotTable}_table.php";
            $path = database_path("migrations/{$filename}");

            $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$pivotTable}', function (Blueprint \$table) {
            \$table->foreignIdFor(\\App\\Models\\{$model}::class)->constrained('{$modelTable}')->cascadeOnDelete();
            \$table->foreignIdFor(\\App\\Models\\{$rel['model']}::class)->constrained('{$relatedTable}')->cascadeOnDelete();
            \$table->primary(['{$this->pivotForeignKey($model)}', '{$this->pivotForeignKey($rel['model'])}']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$pivotTable}');
    }
};
PHP;

            $this->createFile($path, $content);
            $pivotPaths[] = $path;
        }

        return $pivotPaths;
    }

    protected function pivotTableName(string $firstModel, string $secondModel): string
    {
        $tables = [
            Str::snake(Str::singular(class_basename($firstModel))),
            Str::snake(Str::singular(class_basename($secondModel))),
        ];

        sort($tables);

        return implode('_', $tables);
    }

    protected function pivotForeignKey(string $model): string
    {
        return Str::snake(Str::singular(class_basename($model))).'_id';
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['migration'];
    }
}
