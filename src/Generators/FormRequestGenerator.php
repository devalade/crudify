<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class FormRequestGenerator extends BaseGenerator
{
    /** @return array<string> */
    public function generate(string $model): array
    {
        $paths = [];
        $namespace = 'App\\Http\\Requests';
        $modelBase = class_basename($model);
        $fields = $this->fieldParser->getFields();

        $storeClass = 'Store'.$modelBase.'Request';
        $storePath = $this->getPath($namespace, $storeClass);
        $paths[] = $storePath;

        $storeStub = $this->getStub('form-request');
        $storeStub = str_replace('{{ namespace }}', $namespace, $storeStub);
        $storeStub = str_replace('{{ class }}', $storeClass, $storeStub);
        $storeStub = str_replace('{{ rules }}', $this->generateRules($fields, $modelBase, false), $storeStub);
        $this->createFile($storePath, $storeStub);

        $updateClass = 'Update'.$modelBase.'Request';
        $updatePath = $this->getPath($namespace, $updateClass);
        $paths[] = $updatePath;

        $updateStub = $this->getStub('form-request');
        $updateStub = str_replace('{{ namespace }}', $namespace, $updateStub);
        $updateStub = str_replace('{{ class }}', $updateClass, $updateStub);
        $updateStub = str_replace('{{ rules }}', $this->generateRules($fields, $modelBase, true), $updateStub);
        $this->createFile($updatePath, $updateStub);

        return $paths;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    protected function generateRules(array $fields, string $modelBase, bool $isUpdate): string
    {
        $rules = [];
        $table = Str::snake(Str::plural($modelBase));
        $modelVar = Str::camel($modelBase);

        foreach ($this->getRelationships() as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $foreignKey = $this->relationshipForeignKey($rel);

                if ($this->hasField($foreignKey)) {
                    continue;
                }

                $relatedTable = $this->relationshipTable($rel);
                $ruleSet = [$isUpdate ? 'sometimes' : 'required', 'integer', "Rule::exists('{$relatedTable}', 'id')"];
                $ruleStrings = [];
                foreach ($ruleSet as $rule) {
                    if (str_starts_with($rule, 'Rule::')) {
                        $ruleStrings[] = $rule;
                    } else {
                        $ruleStrings[] = "'{$rule}'";
                    }
                }
                $rules[] = "'{$foreignKey}' => [".implode(', ', $ruleStrings).']';
            }

            if ($rel['type'] === 'belongsToMany') {
                $property = 'selected'.Str::studly(Str::plural($rel['name'])).'Ids';
                $relatedTable = Str::plural(Str::snake($rel['model']));
                $presenceRule = $isUpdate ? 'sometimes' : 'nullable';

                $rules[] = "'{$property}' => ['{$presenceRule}', 'array']";
                $rules[] = "'{$property}.*' => ['integer', Rule::exists('{$relatedTable}', 'id')]";
            }
        }

        foreach ($fields as $field) {
            if ($field['name'] === 'id') {
                continue;
            }

            $ruleSet = [];

            if ($isUpdate) {
                $ruleSet[] = $field['nullable'] ? 'nullable' : 'sometimes';
            } else {
                $ruleSet[] = $field['nullable'] ? 'nullable' : 'required';
            }

            if ($field['type'] === 'email') {
                $ruleSet[] = 'email';
            }

            if ($field['type'] === 'integer' || $field['type'] === 'bigint') {
                $ruleSet[] = 'integer';
            }

            if ($field['type'] === 'boolean') {
                $ruleSet[] = 'boolean';
            }

            if ($field['type'] === 'date' || $field['type'] === 'datetime') {
                $ruleSet[] = 'date';
            }

            if ($field['type'] === 'image') {
                if ($field['multiple'] ?? false) {
                    $ruleSet[] = 'array';
                } else {
                    $ruleSet[] = 'image';
                    $ruleSet[] = 'mimes:jpeg,png,jpg,gif,webp,svg,avif';
                    $ruleSet[] = 'max:2048';
                }
            }

            if ($field['type'] === 'file') {
                if ($field['multiple'] ?? false) {
                    $ruleSet[] = 'array';
                } else {
                    $ruleSet[] = 'file';
                    $ruleSet[] = 'mimes:pdf,doc,docx,txt,zip,xls,xlsx,csv,ppt,pptx';
                    $ruleSet[] = 'max:2048';
                }
            }

            if ($field['type'] === 'video') {
                if ($field['multiple'] ?? false) {
                    $ruleSet[] = 'array';
                } else {
                    $ruleSet[] = 'file';
                    $ruleSet[] = 'mimes:mp4,mov,avi,webm,mkv';
                    $ruleSet[] = 'max:10240';
                }
            }

            if ($field['unique']) {
                if ($isUpdate) {
                    $ruleSet[] = "Rule::unique('{$table}', '{$field['name']}')->ignore(\$this->route('{$modelVar}'))";
                } else {
                    $ruleSet[] = "Rule::unique('{$table}', '{$field['name']}')";
                }
            }

            if ($field['type'] === 'foreign' && is_string($field['foreign_table'] ?? null)) {
                $ruleSet[] = "Rule::exists('{$field['foreign_table']}', 'id')";
            }

            if ($field['type'] === 'text') {
                $ruleSet[] = 'string';
            }

            $ruleStrings = [];
            foreach ($ruleSet as $rule) {
                if (str_starts_with($rule, 'Rule::')) {
                    $ruleStrings[] = $rule;
                } else {
                    $ruleStrings[] = "'{$rule}'";
                }
            }

            $rules[] = "'{$field['name']}' => [".implode(', ', $ruleStrings).']';

            // Add validation for individual items in multiple file uploads
            if (in_array($field['type'], ['image', 'file', 'video'], true) && ($field['multiple'] ?? false)) {
                if ($field['type'] === 'image') {
                    $rules[] = "'{$field['name']}.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp,svg,avif', 'max:2048']";
                } elseif ($field['type'] === 'video') {
                    $rules[] = "'{$field['name']}.*' => ['file', 'mimes:mp4,mov,avi,webm,mkv', 'max:10240']";
                } else {
                    $rules[] = "'{$field['name']}.*' => ['file', 'mimes:pdf,doc,docx,txt,zip,xls,xlsx,csv,ppt,pptx', 'max:2048']";
                }
            }
        }

        return implode(",\n            ", $rules);
    }

    /** @return array<string> */
    public function types(): array
    {
        return ['form-request'];
    }
}
