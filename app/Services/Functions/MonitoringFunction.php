<?php


namespace App\Services\Functions;

use App\Services\DbConnections\PublicationsConnections;

class MonitoringFunction
{
	
    public function __construct(
       protected PublicationsConnections $publicationsConnections
    ){}

    public function getItemToMonitoring(){
        $conditions = [
            'origin' => 'MELI',
            'status' => 'active',
            'fs_item' => 1
        ];
        $publications = $this->publicationsConnections->findAllByConditions($conditions);

        dd($publications);
    }
}