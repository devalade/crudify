<?php

namespace Crudify\Generators;

interface Generator
{
    /** @return array<string> */
    public function generate(string $model): array;

    /** @return array<string> */
    public function types(): array;
}
