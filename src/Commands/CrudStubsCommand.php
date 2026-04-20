<?php

namespace Crudify\Commands;

use Illuminate\Console\Command;

class CrudStubsCommand extends Command
{
    protected $signature = 'crudify:stubs';

    protected $description = 'Publish all stub files for customization';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'crudify-stubs']);

        $this->info('Stubs published to stubs/crudify/');
        $this->line('You can now customize these stubs to match your project\'s style.');

        return self::SUCCESS;
    }
}
