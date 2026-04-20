<?php

namespace Crudify\Generators;

interface Generator
{
    public function generate(string $model): array;

    public function types(): array;
}
