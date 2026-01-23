<?php

namespace App\Services\DbConnections;

use App\Models\Authentication;

class AuthenticationConnections extends BaseConnection
{
    /**
     * Classe do Model associado.
     */
    protected string $modelClass = Authentication::class;

    public function getMinimalDataFromChannels(){
        return $this->newQuery()
            ->where("active", 1)
            ->select('id', 'user_id', 'user_channel_id', 'code', 'group', 'token', 'token_valid_at')
            ->get();
    }

    public function getMinimalDataFromEspecificChannel($authId){
        return $this->newQuery()
            ->where([
                ['id' , $authId],
                ["active", 1]
            ])
            ->select('id', 'user_id', 'user_channel_id', 'code', 'group', 'token', 'token_valid_at')
            ->get();
    }

}