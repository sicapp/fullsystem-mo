<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MoHourlyTasksCommand extends Command
{
    protected $signature = 'mo:hourly-tasks';

    protected $description = 'Executa as rotinas de hora em hora do MO';

    public function handle()
    {
        info('[MO] Rotinas de hora em hora executadas: ' . now());

        return Command::SUCCESS;
    }
}
