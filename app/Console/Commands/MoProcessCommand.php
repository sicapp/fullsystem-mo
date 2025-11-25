<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MoProcessCommand extends Command
{
    protected $signature = 'mo:process';

    protected $description = 'Processa as rotinas do módulo operacional (MO)';

    public function handle()
    {
        // Aqui vai o que o cron executará
        info('[MO] Cron executado com sucesso.');

        return Command::SUCCESS;
    }
}
