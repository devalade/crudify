<?php

namespace Crudify\Generators;

use Illuminate\Support\Str;

class FormRequestGenerator extends BaseGenerator
{
    public function generate(string $model): array
    {
        $paths = [];
        $namespace = 'App\\Http\\Requests';
        $modelBase = class_basename($model);
        $fields = $this->fieldParser->getFields();

        $storeClass = 'Store' . $modelBase . 'Request';
        $storePath = $this->getPath($namespace, $storeClass);
        $paths[] = $storePath;

        $storeStub = $this->getStub('form-request');
        $storeStub = str_replace('{{ namespace }}', $namespace, $storeStub);
        $storeStub = str_replace('{{ class }}', $storeClass, $storeStub);
        $storeStub = str_replace('{{ rules }}', $this->generateRules($fields, $modelBase, false), $storeStub);
        $this->createFile($storePath, $storeStub);

        $updateClass = 'Update' . $modelBase . 'Request';
        $updatePath = $this->getPath($namespace, $updateClass);
        $paths[] = $updatePath;

        $updateStub = $this->getStub('form-request');
        $updateStub = str_replace('{{ namespace }}', $namespace, $updateStub);
        $updateStub = str_replace('{{ class }}', $updateClass, $updateStub);
        $updateStub = str_replace('{{ rules }}', $this->generateRules($fields, $modelBase, true), $updateStub);
        $this->createFile($updatePath, $updateStub);

        return $paths;
    }

    protected function generateRules(array $fields, string $modelBase, bool $isUpdate): string
    {
        $rules = [];
        $table = Str::snake(Str::plural($modelBase));
        $modelVar = Str::camel($modelBase);

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

            if ($field['unique']) {
                if ($isUpdate) {
                    $ruleSet[] = "Rule::unique('{$table}', '{$field['name']}')->ignore(\$this->route('{$modelVar}'))";
                } else {
                    $ruleSet[] = "Rule::unique('{$table}', '{$field['name']}')";
                }
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

            $rules[] = "'{$field['name']}' => [" . implode(', ', $ruleStrings) . "]";
        }

        return implode(",\n            ", $rules);
    }

    public function types(): array
    {
        return ['form-request'];
    }
}
