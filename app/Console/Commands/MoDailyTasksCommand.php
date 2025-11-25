<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MoDailyTasksCommand extends Command
{
    protected $signature = 'mo:daily-tasks';

    protected $description = 'Executa as rotinas diárias do MO';

    public function handle()
    {
        info('[MO] Rotinas diárias executadas: ' . now());

        return Command::SUCCESS;
    }
}
