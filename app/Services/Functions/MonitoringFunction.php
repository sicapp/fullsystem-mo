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
        $delay = 0;
        foreach($publications as $dataItem){

            $itemId = $dataItem['item_id'];

            $dataCallback = [
                'origin' => $dataItem['origin'],
                'reference' => "items_prices",
                'reference_id' => "/items/{ $itemId }/prices",
                'company_id' => $dataItem['seller_id'],
                'status' => 0,
                'data' => null,
                'data_status' => null,
                'created_at' => dateUtc(),
                'updated_at' => dateUtc()
            ];

            $delay += 0.5;
            dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => 0, 'dataCallback' => $dataCallback, 'attempt' => 0], $delay, 'service');
        
        }
    }
}