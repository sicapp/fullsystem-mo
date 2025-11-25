<?php

namespace App\Console\Commands;

use App\Services\DbConnections\AuthenticationConnections;
use Illuminate\Console\Command;

class MoProcessCommand extends Command
{
    protected $signature = 'mo:process';

    protected $description = 'Processa as rotinas do módulo operacional (MO)';

    public function handle(AuthenticationConnections $authenticationConnections)
    {
        // Aqui vai o que o cron executará
        info('[MO] Cron executado com sucesso.');
        $auth = $authenticationConnections->getById(7);

        return Command::SUCCESS;
    }
}
