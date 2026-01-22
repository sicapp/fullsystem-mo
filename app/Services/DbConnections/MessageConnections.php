<?php

namespace App\Services\DbConnections;

use App\Models\Message;

class MessageConnections extends BaseConnection
{
    /**
     * Classe do Model associado.
     */
    protected string $modelClass = Message::class;
}
