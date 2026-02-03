<?php


namespace App\Services\Functions;

use App\Services\DbConnections\PublicationsConnections;
use Illuminate\Support\Facades\Log;

class MonitoringFunction
{
	
    public function __construct(
       protected PublicationsConnections $publicationsConnections
    ){}

    public function getItemToMonitoring(){
        Log::channel('process')->info('INICIO DO PROCESSO');
        $conditions = [
            'origin' => 'MELI',
            'status' => 'active',
            'fs_item' => 1
        ];
        $publications = $this->publicationsConnections->findAllByConditions($conditions);

        foreach($publications as $dataItem){

            $itemId = $dataItem['item_id'];

            $dataCallback = [
                'origin' => $dataItem['origin'],
                'reference' => "items_prices",
                'reference_id' => "/items/{$itemId}/prices",
                'company_id' => $dataItem['seller_id'],
                'status' => 0,
                'data' => null,
                'data_status' => null,
                'created_at' => dateUtc(),
                'updated_at' => dateUtc()
            ];

            Log::channel('process')->info('1- ITEM: ' . $itemId);

            dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => 0, 'dataCallback' => $dataCallback, 'attempt' => 0], 0, 'service');
        
        }
    }
}