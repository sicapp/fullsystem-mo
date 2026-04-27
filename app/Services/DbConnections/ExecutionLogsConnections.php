<?php


namespace App\Services\DbConnections;

use App\Models\ExecutionLogs;

class ExecutionLogsConnections extends BaseConnection
{
	/**
     * Classe do Model associado.
     */
    protected string $modelClass = ExecutionLogs::class;
}