<?php

namespace Crudify\Support;

use Symfony\Component\Process\Process;

class ExternalCommandRunner
{
    /**
     * @param  array<int, string>  $command
     */
    public function run(array $command, string $workingDirectory, callable $writeOutput): int
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer) use ($writeOutput): void {
            $writeOutput($buffer, $type);
        });

        return $process->getExitCode() ?? 1;
    }
}
