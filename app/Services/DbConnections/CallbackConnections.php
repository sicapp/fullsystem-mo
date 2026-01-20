<?php

namespace App\Services\DbConnections;

use App\Models\Callback;

class CallbackConnections extends BaseConnection
{
    /**
     * Classe do Model associado.
     */
    protected string $modelClass = Callback::class;

    public function updateCallback($id, $data){
        //função usada pelo Helper
        return $this->newQuery()
            ->where('id', $id)
            ->update($data);
    }
}
