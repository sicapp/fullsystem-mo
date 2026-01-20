<?php

namespace App\Services\DbConnections;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserConnections extends BaseConnection
{
    /**
     * Classe do Model associado.
     */
    protected string $modelClass = User::class;

}
